@php
    $moves = [
        'future' => [['in_work', 'Start work', 'arrow'], ['done', 'Mark done', 'check']],
        'in_work' => [['done', 'Mark done', 'check'], ['future', 'Back to future', 'undo']],
        'done' => [['in_work', 'Reopen', 'undo'], ['future', 'Back to future', 'undo']],
    ][$project->status];

    $dateLabels = [
        'future' => ['Planned start', 'Target finish'],
        'in_work' => ['Started', 'Expected finish'],
        'done' => ['Started', 'Finished'],
    ][$project->status];

    $overdue = $project->status === 'in_work' && $project->finish_date?->lt(today());

    $github = null;
    if ($project->github_link) {
        $host = parse_url($project->github_link, PHP_URL_HOST);
        $path = trim((string) parse_url($project->github_link, PHP_URL_PATH), '/');
        $github = $host === 'github.com' && $path !== '' ? $path : trim($host . '/' . $path, '/');
    }

    $titleId = 'project-' . $project->id . '-title';
@endphp

<article class="pj-card" id="project-{{ $project->id }}" aria-labelledby="{{ $titleId }}">
    <div class="flex items-start justify-between gap-3">
        <h3 class="pj-card__title" id="{{ $titleId }}">{{ $project->title }}</h3>
        <div class="-mr-1.5 -mt-1 flex shrink-0 gap-0.5">
            <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" data-pj-open="edit" data-project="{{ $project->id }}" aria-label="Edit {{ $project->title }}" title="Edit">
                <x-pj-icon name="pencil" />
            </button>
            <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" data-pj-open="delete" data-project="{{ $project->id }}" aria-label="Delete {{ $project->title }}" title="Delete">
                <x-pj-icon name="trash" />
            </button>
        </div>
    </div>

    @if ($project->description)
        <p class="pj-card__desc">{{ $project->description }}</p>
    @endif

    @if ($project->languages->isNotEmpty() || $project->people->isNotEmpty())
        <div class="flex flex-col gap-2">
            @if ($project->languages->isNotEmpty())
                <ul class="pj-chips" aria-label="Languages">
                    @foreach ($project->languages as $language)
                        <li class="pj-chip pj-chip--lang">{{ $language->name }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($project->people->isNotEmpty())
                <ul class="pj-chips" aria-label="People">
                    @foreach ($project->people as $person)
                        <li class="pj-chip"><span class="pj-chip__avatar" aria-hidden="true">{{ mb_substr($person->name, 0, 1) }}</span>{{ $person->name }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <dl class="pj-meta">
        <div>
            <dt>{{ $dateLabels[0] }}</dt>
            <dd>
                @if ($project->start_date)
                    <time datetime="{{ $project->start_date->format('Y-m-d') }}">{{ $project->start_date->format('j M Y') }}</time>
                @else
                    <span class="pj-faint">—</span>
                @endif
            </dd>
        </div>
        @if ($project->status !== 'future' || $project->finish_date)
            <div>
                <dt>{{ $dateLabels[1] }}</dt>
                <dd>
                    @if ($project->finish_date)
                        <time datetime="{{ $project->finish_date->format('Y-m-d') }}">{{ $project->finish_date->format('j M Y') }}</time>
                        @if ($overdue)
                            <span class="pj-overdue">overdue</span>
                        @endif
                    @else
                        <span class="pj-faint">—</span>
                    @endif
                </dd>
            </div>
        @endif
        @if ($project->status === 'done')
            @if ($project->start_date && $project->finish_date)
                <div>
                    <dt>Took</dt>
                    <dd>{{ $project->start_date->diffForHumans($project->finish_date, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}</dd>
                </div>
            @endif
            <div>
                <dt>Lines of code</dt>
                <dd>{{ $project->lines_of_code !== null ? number_format($project->lines_of_code) : '—' }}</dd>
            </div>
        @endif
    </dl>

    @if ($github || $project->root_path)
        <div class="flex flex-col gap-2">
            @if ($github)
                <div class="pj-resource">
                    <x-pj-icon name="github" />
                    <a class="pj-link pj-resource__text" href="{{ $project->github_link }}" target="_blank" rel="noopener noreferrer">
                        {{ $github }}<span class="pj-sr-only"> on GitHub (opens in a new tab)</span>
                    </a>
                </div>
            @endif
            @if ($project->root_path)
                <div class="pj-resource">
                    <x-pj-icon name="folder" />
                    <span class="pj-resource__text pj-mono" title="{{ $project->wslPath() }}">{{ $project->wslPath() }}</span>
                    <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" data-pj-copy="{{ $project->wslPath() }}" aria-label="Copy folder path" title="Copy path">
                        <x-pj-icon name="copy" />
                    </button>
                    <a class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" href="{{ $project->vscodeUrl() }}" aria-label="Open folder in VS Code" title="Open in VS Code">
                        <x-pj-icon name="code" />
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if ($project->root_path === null)
        <div class="pj-empty-inline">
            <x-pj-icon name="folder" />
            <span>No project folder set.
                <button type="button" class="pj-linkbtn" data-pj-open="edit" data-project="{{ $project->id }}">Add one</button>
                to browse its files.</span>
        </div>
    @elseif (!($folderStates[$project->id] ?? false))
        <div class="pj-banner pj-banner--warn" role="note">
            <x-pj-icon name="alert" />
            <div>
                <p class="font-medium">Folder not found or unreadable.</p>
                <p class="text-sm opacity-80">Either the folder was moved or renamed, or the projects mount isn’t available. Check that the path above exists.</p>
            </div>
        </div>
    @else
        <div class="pj-tree" data-pj-tree data-url="{{ route('projects.files', $project) }}">
            <button type="button" class="pj-btn pj-btn--sm self-start" data-pj-tree-root aria-expanded="false" aria-controls="tree-{{ $project->id }}">
                <x-pj-icon name="chevron" class="pj-tree__chevron" />
                <span>Files</span>
            </button>
            <div id="tree-{{ $project->id }}" class="mt-2" hidden></div>
        </div>
    @endif

    @php $openUpdateCount = $openUpdates[$project->id] ?? 0; @endphp
    @if ($openUpdateCount > 0)
        {{-- The other direction of the Future Updates link: you are looking at a project and
             want to know what you planned for it. --}}
        <a class="pj-link pj-updates" href="{{ route('future-updates') }}?project={{ $project->id }}">
            <x-pj-icon name="bulb" />
            {{ $openUpdateCount }} planned {{ Str::plural('update', $openUpdateCount) }}
        </a>
    @endif

    <div class="pj-card__footer">
        @foreach ($moves as $i => [$target, $label, $icon])
            <button type="button" class="pj-btn pj-btn--sm {{ $i === 0 ? 'pj-btn--primary' : '' }}" data-pj-open="status" data-project="{{ $project->id }}" data-target="{{ $target }}">
                <x-pj-icon :name="$icon" />
                {{ $label }}
            </button>
        @endforeach
    </div>
</article>
