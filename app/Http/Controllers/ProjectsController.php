<?php

namespace App\Http\Controllers;

use App\Models\FutureUpdate;
use App\Models\Language;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectsController extends Controller
{
    private const DEFAULT_TAB = 'in_work';
    private const MAX_ENTRIES = 500;

    public const REQUIRED = [
        'future' => ['title', 'description', 'people', 'languages', 'start_date'],
        'in_work' => ['title', 'description', 'people', 'languages', 'start_date', 'finish_date'],
        'done' => ['title', 'description', 'people', 'languages', 'start_date', 'finish_date', 'root_path', 'lines_of_code'],
    ];

    public const LABELS = ['future' => 'Future', 'in_work' => 'In work', 'done' => 'Done'];

    private const TRANSITION_FIELDS = ['start_date', 'finish_date', 'root_path', 'lines_of_code'];

    public function showProjectsPage(Request $request)
    {
        $tab = in_array($request->query('tab'), Project::STATUSES, true)
            ? $request->query('tab')
            : self::DEFAULT_TAB;

        $projects = Project::where('user_id', Auth::user()->id)
            ->with(['people', 'languages'])
            ->get();

        $grouped = [];
        foreach (Project::STATUSES as $status) {
            $grouped[$status] = $this->sortForTab($projects->where('status', $status), $status);
        }

        $openUpdates = FutureUpdate::where('user_id', Auth::user()->id)
            ->where('is_done', false)
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->selectRaw('project_id, COUNT(*) as total')
            ->pluck('total', 'project_id');

        $folderStates = [];
        foreach ($projects as $project) {
            if ($project->root_path !== null) {
                $folderStates[$project->id] = $this->projectDir($project) !== null;
            }
        }

        $projectsData = $projects->mapWithKeys(fn (Project $p) => [$p->id => [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'status' => $p->status,
            'github_link' => $p->github_link,
            'root_path' => $p->root_path,
            'start_date' => $p->start_date?->format('Y-m-d'),
            'finish_date' => $p->finish_date?->format('Y-m-d'),
            'lines_of_code' => $p->lines_of_code,
            'people' => $p->people->pluck('name')->implode(', '),
            'languages' => $p->languages->pluck('name')->implode(', '),
            'urls' => [
                'update' => route('projects.update', $p),
                'status' => route('projects.status', $p),
                'destroy' => route('projects.destroy', $p),
            ],
        ]]);

        return view('projects', [
            'tab' => $tab,
            'labels' => self::LABELS,
            'grouped' => $grouped,
            'folderStates' => $folderStates,
            'openUpdates' => $openUpdates,
            'required' => self::REQUIRED,
            'projectsData' => $projectsData,
        ]);
    }

    public function storeProject(Request $request)
    {
        $status = in_array($request->input('status'), Project::STATUSES, true) ? $request->input('status') : 'future';
        $validator = $this->projectValidator($request->all(), $status, withStatus: true);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $project = Project::create([
            'user_id' => Auth::user()->id,
            'status' => $status,
        ] + $this->projectAttributes($data));

        $this->syncTags($project, $data);

        return redirect('/projects?tab=' . $status)->with('success', "Added “{$project->title}”.");
    }

    public function updateProject(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::user()->id) {
            abort(403);
        }

        $validator = $this->projectValidator($request->all(), $project->status);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $project->update($this->projectAttributes($data));
        $this->syncTags($project, $data);

        return back()->with('success', "Saved “{$project->title}”.");
    }

    public function updateStatus(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::user()->id) {
            abort(403);
        }

        $target = $request->input('status');

        if (!in_array($target, Project::STATUSES, true)) {
            return back()->withErrors(['status' => 'Unknown status.'])->withInput();
        }

        if ($target === $project->status) {
            return back()->withErrors(['status' => 'The project is already in that status.'])->withInput();
        }

        $merged = [
            'title' => $project->title,
            'description' => $project->description,
            'people' => $project->people->pluck('name')->implode(', '),
            'languages' => $project->languages->pluck('name')->implode(', '),
            'start_date' => $project->start_date?->format('Y-m-d'),
            'finish_date' => $project->finish_date?->format('Y-m-d'),
            'root_path' => $project->root_path,
            'lines_of_code' => $project->lines_of_code,
            'github_link' => $project->github_link,
        ];

        foreach (self::TRANSITION_FIELDS as $field) {
            if ($request->exists($field)) {
                $merged[$field] = $request->input($field);
            }
        }

        $validator = $this->projectValidator($merged, $target);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $changes = ['status' => $target];

        foreach (self::TRANSITION_FIELDS as $field) {
            $changes[$field] = $data[$field] ?? null;
        }

        $project->update($changes);

        return redirect('/projects?tab=' . $target)
            ->with('success', "Moved “{$project->title}” to " . self::LABELS[$target] . '.');
    }

    public function destroyProject(Project $project)
    {
        if ($project->user_id !== Auth::user()->id) {
            abort(403);
        }

        $title = $project->title;
        $project->delete();

        return back()->with('success', "Deleted “{$title}”.");
    }

    public function listFiles(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($project->root_path === null) {
            return response()->json(['state' => 'no_root', 'message' => 'No folder is set for this project.'], 404);
        }

        $root = $this->projectDir($project);

        if ($root === null) {
            return response()->json([
                'state' => 'missing',
                'message' => 'The folder ' . $project->wslPath() . ' is missing or unreadable.',
            ], 404);
        }

        $sub = trim((string) $request->query('path', ''), '/');

        if ($sub !== '' && (!$this->isSafeRelative($sub) || $this->hasIgnoredSegment($sub))) {
            return response()->json(['state' => 'forbidden', 'message' => 'That path is not allowed.'], 403);
        }

        $target = $sub === '' ? $root : realpath($root . '/' . $sub);

        if ($target === false || !is_dir($target)) {
            return response()->json(['state' => 'missing', 'message' => 'This folder no longer exists.'], 404);
        }

        if (!$this->isInside($target, $root)) {
            return response()->json(['state' => 'forbidden', 'message' => 'That path is outside the project.'], 403);
        }

        $names = @scandir($target);

        if ($names === false) {
            return response()->json(['state' => 'missing', 'message' => 'This folder could not be read.'], 404);
        }

        $entries = [];

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $relative = $sub === '' ? $name : $sub . '/' . $name;
            $full = $target . '/' . $name;

            if (!file_exists($full)) {
                continue; // broken symlink
            }

            $isDir = is_dir($full);

            if ($this->isIgnored($name, $relative, $isDir)) {
                continue;
            }

            $entry = ['name' => $name, 'type' => $isDir ? 'dir' : 'file', 'path' => $relative];

            if (!$isDir) {
                $entry['wsl'] = $project->wslPath($relative);
                $entry['vscode'] = $project->vscodeUrl($relative);
            }

            $entries[] = $entry;
        }

        usort($entries, fn ($a, $b) => [$a['type'] === 'file', strtolower($a['name'])] <=> [$b['type'] === 'file', strtolower($b['name'])]);

        $truncated = count($entries) > self::MAX_ENTRIES;

        return response()->json([
            'state' => $entries === [] ? 'empty' : 'ok',
            'entries' => array_slice($entries, 0, self::MAX_ENTRIES),
            'truncated' => $truncated,
        ]);
    }

    private function projectValidator(array $input, string $status, bool $withStatus = false)
    {
        $input['root_path'] = $this->normalizeRootPath($input['root_path'] ?? null);
        $required = self::REQUIRED[$status];

        $rules = [
            'title' => ['string', 'max:255'],
            'description' => ['string', 'max:10000'],
            'people' => ['string', 'max:1000'],
            'languages' => ['string', 'max:1000'],
            'start_date' => ['date'],
            'finish_date' => ['date'],
            'root_path' => ['string', 'max:255', function ($attribute, $value, $fail) {
                if (!$this->isSafeRelative($value)) {
                    $fail('The folder must be a path relative to ' . config('projects.wsl_root') . ', e.g. drone_sound_KNN — no leading slash, backslashes, drive letters, "." or "..".');
                }
            }],
            'lines_of_code' => ['integer', 'min:0', 'max:4294967295'],
            'github_link' => ['url', 'max:255'],
        ];

        if (!empty($input['start_date'])) {
            $rules['finish_date'][] = 'after_or_equal:start_date';
        }

        foreach ($rules as $field => $fieldRules) {
            array_unshift($fieldRules, in_array($field, $required, true) ? 'required' : 'nullable');
            $rules[$field] = $fieldRules;
        }

        if ($withStatus) {
            $rules['status'] = ['required', Rule::in(Project::STATUSES)];
        }

        $validator = Validator::make($input, $rules, [
            'required' => ':Attribute is required for ' . strtolower(self::LABELS[$status]) . ' projects.',
            'finish_date.after_or_equal' => 'The finish date can’t be before the start date.',
        ], [
            'start_date' => 'start date',
            'finish_date' => 'finish date',
            'root_path' => 'folder',
            'lines_of_code' => 'lines of code',
            'github_link' => 'GitHub link',
        ]);

        $validator->after(function ($validator) use ($input, $required) {
            foreach (['people' => 'person', 'languages' => 'language'] as $field => $noun) {
                if (in_array($field, $required, true)
                    && !$validator->errors()->has($field)
                    && Person::parseList($input[$field] ?? '') === []) {
                    $validator->errors()->add($field, "Add at least one {$noun}.");
                }
            }
        });

        return $validator;
    }

    private function projectAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'github_link' => $data['github_link'] ?? null,
            'root_path' => $data['root_path'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'finish_date' => $data['finish_date'] ?? null,
            'lines_of_code' => $data['lines_of_code'] ?? null,
        ];
    }

    private function syncTags(Project $project, array $data): void
    {
        $project->people()->sync(Person::idsFromList(Auth::user()->id, $data['people'] ?? null));
        $project->languages()->sync(Language::idsFromList($data['languages'] ?? null));
    }

    private function sortForTab($projects, string $status)
    {
        $key = $status === 'done' ? 'finish_date' : 'start_date';
        $ascending = $status === 'future';

        return $projects->sort(function ($a, $b) use ($key, $ascending) {
            if ($a->$key === null || $b->$key === null) {
                return ($a->$key === null) <=> ($b->$key === null);
            }

            return $ascending ? $a->$key <=> $b->$key : $b->$key <=> $a->$key;
        })->values();
    }
    private function normalizeRootPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);

        foreach ([config('projects.wsl_root'), config('projects.container_root')] as $prefix) {
            $prefix = rtrim($prefix, '/') . '/';

            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        $path = rtrim($path, '/');

        return $path === '' ? null : $path;
    }

    private function projectDir(Project $project): ?string
    {
        $base = realpath(config('projects.container_root'));

        if ($base === false || !$this->isSafeRelative($project->root_path)) {
            return null;
        }

        $dir = realpath($base . '/' . $project->root_path);

        if ($dir === false || !is_dir($dir) || !is_readable($dir) || !$this->isInside($dir, $base)) {
            return null;
        }

        return $dir;
    }

    private function isSafeRelative(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || strpbrk($path, "\\:\0") !== false) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isInside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    private function isIgnored(string $name, string $relative, bool $isDir): bool
    {
        foreach (config('projects.ignore') as $pattern) {
            if (str_contains($pattern, '/')) {
                if ($relative === $pattern || str_ends_with($relative, '/' . $pattern)) {
                    return true;
                }
            } elseif ($name === $pattern) {
                return true;
            }
        }

        if (!$isDir) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            return $extension !== '' && in_array($extension, config('projects.ignore_extensions'), true);
        }

        return false;
    }

    private function hasIgnoredSegment(string $sub): bool
    {
        $relative = '';

        foreach (explode('/', $sub) as $segment) {
            $relative = $relative === '' ? $segment : $relative . '/' . $segment;

            if ($this->isIgnored($segment, $relative, true)) {
                return true;
            }
        }

        return false;
    }
}
