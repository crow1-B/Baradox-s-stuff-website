@php
    $moves = [
        'future' => [['in_work', 'Start work', 'fa-arrow-right'], ['done', 'Mark done', 'fa-check']],
        'in_work' => [['done', 'Mark done', 'fa-check'], ['future', 'Back to future', 'fa-rotate-left']],
        'done' => [['in_work', 'Reopen', 'fa-rotate-left'], ['future', 'Back to future', 'fa-rotate-left']],
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
    <div class="pj-card__head">
        <h3 class="pj-card__title" id="{{ $titleId }}">{{ $project->title }}</h3>
        @if ($canWrite)
            <div class="pj-card__tools">
                <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-pj-open="edit" data-project="{{ $project->id }}" aria-label="Edit {{ $project->title }}" title="Edit">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </button>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-icon-btn--danger" data-pj-open="delete" data-project="{{ $project->id }}" aria-label="Delete {{ $project->title }}" title="Delete">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                </button>
            </div>
        @endif
    </div>

    @if ($project->description)
        <p class="pj-card__desc">{{ $project->description }}</p>
    @endif

    @if ($project->languages->isNotEmpty() || $project->people->isNotEmpty())
        <div class="pj-card__stack">
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
        <div class="pj-card__stack">
            @if ($github)
                <div class="pj-resource">
                    <i class="fa-brands fa-github" aria-hidden="true"></i>
                    <a class="nd-link pj-resource__text" href="{{ $project->github_link }}" target="_blank" rel="noopener noreferrer">
                        {{ $github }}<span class="nd-sr-only"> on GitHub (opens in a new tab)</span>
                    </a>
                </div>
            @endif
            @if ($project->root_path)
                <div class="pj-resource">
                    <i class="fa-regular fa-folder" aria-hidden="true"></i>
                    <span class="pj-resource__text pj-mono" title="{{ $project->wslPath() }}">{{ $project->wslPath() }}</span>
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-pj-copy="{{ $project->wslPath() }}" aria-label="Copy folder path" title="Copy path">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    </button>
                    <a class="nd-icon-btn nd-icon-btn--sm" href="{{ $project->vscodeUrl() }}" aria-label="Open folder in VS Code" title="Open in VS Code">
                        <i class="fa-solid fa-code" aria-hidden="true"></i>
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if ($project->root_path === null)
        <div class="pj-empty-inline">
            <i class="fa-regular fa-folder" aria-hidden="true"></i>
            @if ($canWrite)
                <span>No project folder set.
                    <button type="button" class="pj-linkbtn" data-pj-open="edit" data-project="{{ $project->id }}">Add one</button>
                    to browse its files.</span>
            @else
                <span>No project folder set.</span>
            @endif
        </div>
    @elseif (!($folderStates[$project->id] ?? false))
        <div class="nd-banner nd-banner--warn" role="note">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div class="nd-banner__text">
                <p><strong>Folder not found or unreadable.</strong></p>
                <p>Either the folder was moved or renamed, or the projects mount isn’t available. Check that the path above exists.</p>
            </div>
        </div>
    @else
        <div class="pj-tree" data-pj-tree data-url="{{ route('projects.files', $project) }}">
            <button type="button" class="nd-btn nd-btn--sm nd-btn--ghost pj-tree__root" data-pj-tree-root aria-expanded="false" aria-controls="tree-{{ $project->id }}">
                <i class="fa-solid fa-chevron-right pj-tree__chevron" aria-hidden="true"></i>
                <span>Files</span>
            </button>
            <div id="tree-{{ $project->id }}" class="pj-tree__region" hidden></div>
        </div>
    @endif

    @php $openUpdateCount = $openUpdates[$project->id] ?? 0; @endphp
    @if ($openUpdateCount > 0)
        {{-- The other direction of the Future Updates link: you are looking at a project and
             want to know what you planned for it. --}}
        <a class="nd-link pj-updates" href="{{ route('future-updates') }}?project={{ $project->id }}">
            <i class="fa-regular fa-lightbulb" aria-hidden="true"></i>
            {{ $openUpdateCount }} planned {{ Str::plural('update', $openUpdateCount) }}
        </a>
    @endif

    @if ($canWrite)
        <div class="pj-card__footer">
            @foreach ($moves as $i => [$target, $label, $icon])
                <button type="button" class="nd-btn nd-btn--sm {{ $i === 0 ? 'nd-btn--primary' : 'nd-btn--ghost' }}" data-pj-open="status" data-project="{{ $project->id }}" data-target="{{ $target }}">
                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif
</article>
