<?php

namespace App\Http\Controllers;

use App\Models\FutureUpdate;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;


class FutureUpdatesController extends Controller
{
    public const MAX_LENGTH = 2000;

    private const KIND_ORDER = ['sitewide' => 0, 'project' => 1, 'orphan' => 2];

    private const STATUS_ORDER = ['in_work' => 0, 'future' => 1, 'done' => 2];

    public function showFutureUpdatesPage(Request $request)
    {
        $userId = Auth::user()->id;
        $projects = Project::where('user_id', $userId)->orderBy('title')->get();

        // ?project= arrives from a project card on the Projects page, as a real navigation,
        $filter = null;
        if ($request->filled('project')) {
            $filter = $projects->firstWhere('id', (int) $request->query('project'));

            if ($filter === null) {
                abort(404);
            }
        }

        // Newest first
        $updates = FutureUpdate::where('user_id', $userId)
            ->when($filter, fn ($query) => $query->where('project_id', $filter->id))
            ->with('project')
            ->orderBy('id', 'desc')
            ->get();

        $openCount = $updates->where('is_done', false)->count();

        return view('future_updates', [
            'updates' => $updates,
            'groups' => $this->groupUpdates($updates),
            'projects' => $projects,
            'filter' => $filter,
            'labels' => ProjectsController::LABELS,
            'pickerOrder' => array_keys(self::STATUS_ORDER),
            'view' => $request->query('view') === 'flat' ? 'flat' : 'grouped',
            'showDone' => $request->query('done') === '1',
            'openCount' => $openCount,
            'doneCount' => $updates->count() - $openCount,
            'maxLength' => self::MAX_LENGTH,
        ]);
    }

    public function storeUpdate(Request $request)
    {
        $data = $this->validateUpdate($request);

        FutureUpdate::create([
            'user_id' => Auth::user()->id,
            'content' => $data['content'],
            'project_id' => $data['project_id'] ?? null,
        ]);

        return back()->with('success', 'Added to the list.');
    }

    public function editUpdate(Request $request, FutureUpdate $update)
    {
        if ($update->user_id !== Auth::user()->id) {
            abort(403);
        }

        $data = $this->validateUpdate($request);

        $update->update([
            'content' => $data['content'],
            'project_id' => $data['project_id'] ?? null,
            'orphaned_project_title' => null,
        ]);

        return back()->with('success', 'Saved.');
    }

    public function toggleDone(Request $request, FutureUpdate $update)
    {
        if ($update->user_id !== Auth::user()->id) {
            abort(403);
        }

        $done = $request->boolean('is_done');
        $update->update(['is_done' => $done]);

        return back()->with('success', $done ? 'Marked done.' : 'Moved back to open.');
    }

    public function destroyUpdate(FutureUpdate $update)
    {
        if ($update->user_id !== Auth::user()->id) {
            abort(403);
        }

        $update->delete();

        return back()->with('success', 'Deleted.');
    }

    private function validateUpdate(Request $request): array
    {
        return $request->validate([
            'content' => ['required', 'string', 'max:' . self::MAX_LENGTH],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', Auth::user()->id),
            ],
        ], [
            'content.required' => 'Write the update first.',
            'project_id.exists' => 'That project no longer exists.',
        ], [
            'project_id' => 'project',
        ]);
    }

    private function groupUpdates($updates): array
    {
        $groups = [];

        foreach ($updates as $update) {
            $project = $update->project;

            if ($project !== null) {
                $key = 'project:' . $project->id;
                $groups[$key] ??= [
                    'kind' => 'project',
                    'title' => $project->title,
                    'status' => $project->status,
                    'link' => $this->projectLink($project),
                ];
            } elseif ($update->isOrphaned()) {
                $key = 'orphan:' . $update->orphaned_project_title;
                $groups[$key] ??= [
                    'kind' => 'orphan',
                    'title' => $update->orphaned_project_title,
                    'status' => null,
                    'link' => null,
                ];
            } else {
                $key = 'sitewide';
                $groups[$key] ??= [
                    'kind' => 'sitewide',
                    'title' => 'Sitewide',
                    'status' => null,
                    'link' => null,
                ];
            }

            $groups[$key]['updates'][] = $update;
            $groups[$key][$update->is_done ? 'done' : 'open'] = ($groups[$key][$update->is_done ? 'done' : 'open'] ?? 0) + 1;
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['open'] ??= 0;
            $groups[$key]['done'] ??= 0;
        }

        uasort($groups, fn ($a, $b) => $this->groupOrder($a) <=> $this->groupOrder($b));

        return $groups;
    }

    private function groupOrder(array $group): array
    {
        return [
            self::KIND_ORDER[$group['kind']],
            self::STATUS_ORDER[$group['status']] ?? 9,
            mb_strtolower($group['title']),
        ];
    }

    private function projectLink(Project $project): string
    {
        return route('projects') . '?tab=' . $project->status . '&highlight=' . $project->id;
    }
}
