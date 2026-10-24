@extends('layouts.app')

@push('head')
    @vite(['resources/css/projects.css', 'resources/js/projects.js'])
@endpush

@section('content')
@php
    $empty = [
        'future' => ['No plans yet.', 'Ideas you want to build later live here. Give each one a planned start date.', 'Plan a project'],
        'in_work' => ['Nothing on the bench.', 'Start one of your future projects, or add what you’re working on right now.', 'Add a current project'],
        'done' => ['Nothing finished yet.', 'Completed projects land here, with their dates and line counts.', 'Add a finished project'],
    ];
    $root = rtrim(config('projects.wsl_root'), '/') . '/';
    $rootShort = preg_replace('#^/home/[^/]+/#', '~/', $root);
@endphp

<div class="nd-page pj" id="pj-root">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Projects</h1>
            <p class="nd-page__sub">The past, present and future, all in one place.</p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                <button type="button" class="nd-btn nd-btn--primary" data-pj-open="create" data-status="{{ $tab }}">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    New project
                </button>
            </div>
        @endif
    </header>

    <div class="nd-page__banners">
        @if (session('success'))
            <div class="nd-banner nd-banner--success" role="status" data-pj-dismissable>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <p class="nd-banner__text">{{ session('success') }}</p>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-pj-dismiss aria-label="Dismiss">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="nd-banner nd-banner--error" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="nd-banner__text">
                    <p>That didn’t save.</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <div role="tablist" aria-label="Project status" class="pj-tabs" data-pj-tabs>
        @foreach ($labels as $status => $label)
            <a role="tab"
               id="tab-{{ $status }}"
               href="{{ url('/projects?tab=' . $status) }}"
               aria-controls="panel-{{ $status }}"
               aria-selected="{{ $tab === $status ? 'true' : 'false' }}"
               class="pj-tab"
               data-pj-tab="{{ $status }}">
                <span class="nd-dot nd-dot--{{ $status }}" aria-hidden="true"></span>
                {{ $label }}
                <span class="pj-tab__count">{{ $grouped[$status]->count() }}<span class="nd-sr-only"> {{ Str::plural('project', $grouped[$status]->count()) }}</span></span>
            </a>
        @endforeach
    </div>

    @foreach ($labels as $status => $label)
        <section role="tabpanel" id="panel-{{ $status }}" aria-labelledby="tab-{{ $status }}" class="pj-panel" @if ($tab !== $status) hidden @endif>
            @if ($grouped[$status]->isEmpty())
                <div class="nd-empty nd-empty--block">
                    <p class="nd-empty__line">{{ $empty[$status][0] }}</p>
                    @if ($canWrite)
                        <p>{{ $empty[$status][1] }}</p>
                        <button type="button" class="nd-btn" data-pj-open="create" data-status="{{ $status }}">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            {{ $empty[$status][2] }}
                        </button>
                    @endif
                </div>
            @else
                <div class="pj-grid">
                    @foreach ($grouped[$status] as $project)
                        @include('partials.project-card', ['project' => $project])
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    @if ($canWrite)
    {{-- Create / edit ------------------------------------------------------ --}}
    <dialog class="nd-dialog nd-dialog--wide" id="pj-project-dialog" aria-labelledby="pj-project-dialog-title">
        <form method="POST" action="{{ route('projects.store') }}" novalidate data-pj-project-form>
            @csrf
            <input type="hidden" name="_method" value="POST" disabled>
            <input type="hidden" name="_modal" value="create">

            <div class="nd-dialog__head">
                <h2 class="nd-dialog__title" id="pj-project-dialog-title">New project</h2>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-pj-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="nd-dialog__body">
                <div class="nd-fields">
                    <x-nd-field form="pf" name="title" label="Title" full required="toggle" placeholder="drone sound classifier" />

                    <fieldset class="nd-field nd-field--full pj-status-field" data-field="status" data-pj-create-only>
                        <legend class="nd-label">Status</legend>
                        <div class="nd-segmented">
                            @foreach ($labels as $status => $label)
                                <input type="radio" name="status" id="pf-status-{{ $status }}" value="{{ $status }}">
                                <label for="pf-status-{{ $status }}"><span class="nd-dot nd-dot--{{ $status }}" aria-hidden="true"></span>{{ $label }}</label>
                            @endforeach
                        </div>
                        <p class="nd-error" id="pf-status-error" data-field-error hidden></p>
                    </fieldset>

                    <x-nd-field form="pf" name="description" label="Description" type="textarea" full required="toggle" />
                    <x-nd-field form="pf" name="languages" label="Languages" hint="Comma separated" placeholder="Python, C++" required="toggle" />
                    <x-nd-field form="pf" name="people" label="People" hint="Comma separated" placeholder="Ali, Zahra" required="toggle" />
                    <x-nd-field form="pf" name="start_date" label="Start date" type="date" required="toggle" />
                    <x-nd-field form="pf" name="finish_date" label="Finish date" type="date" required="toggle" />
                    <x-nd-field form="pf" name="root_path" label="Project folder" full placeholder="drone_sound_KNN" required="toggle"
                        :prefix="$rootShort" :prefix-title="$root"
                        hint="Relative to the projects folder. Pasting the full path works too — only the relative part is stored." />
                    <x-nd-field form="pf" name="lines_of_code" label="Lines of code" type="number" placeholder="4200" required="toggle" />
                    <x-nd-field form="pf" name="github_link" label="GitHub link" type="url" placeholder="https://github.com/…" required="toggle" />
                </div>
            </div>

            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-pj-close>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--primary" data-pj-submit>Create project</button>
            </div>
        </form>
    </dialog>

    {{-- Status transition -------------------------------------------------- --}}
    <dialog class="nd-dialog nd-dialog--sm" id="pj-status-dialog" aria-labelledby="pj-status-dialog-title" aria-describedby="pj-status-note">
        <form method="POST" action="" novalidate data-pj-status-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">
            <input type="hidden" name="status" value="">

            <div class="nd-dialog__head">
                <h2 class="nd-dialog__title" id="pj-status-dialog-title">Move project</h2>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-pj-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="nd-dialog__body pj-status-body">
                <p class="pj-dialog-note" id="pj-status-note"></p>
                <p class="nd-error" id="sf-status-error" data-field-error hidden></p>
                <div class="nd-fields">
                    <x-nd-field form="sf" name="start_date" label="Start date" type="date" required="toggle" />
                    <x-nd-field form="sf" name="finish_date" label="Finish date" type="date" required="toggle" />
                    <x-nd-field form="sf" name="root_path" label="Project folder" full placeholder="drone_sound_KNN" required="toggle"
                        :prefix="$rootShort" :prefix-title="$root" />
                    <x-nd-field form="sf" name="lines_of_code" label="Lines of code" type="number" placeholder="4200" required="toggle" />
                </div>
                {{-- Errors on fields the move doesn't show (e.g. a missing description) land here. --}}
                <ul class="nd-error pj-other-errors" data-pj-other-errors hidden></ul>
            </div>

            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-pj-close>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--primary" data-pj-submit>Move</button>
            </div>
        </form>
    </dialog>

    {{-- Delete ------------------------------------------------------------- --}}
    <dialog class="nd-dialog nd-dialog--sm" id="pj-delete-dialog" aria-labelledby="pj-delete-dialog-title" aria-describedby="pj-delete-note">
        <form method="POST" action="" data-pj-delete-form>
            @csrf
            @method('DELETE')
            <div class="nd-dialog__head">
                <h2 class="nd-dialog__title" id="pj-delete-dialog-title">Delete project?</h2>
            </div>
            <div class="nd-dialog__body">
                <p class="pj-dialog-note" id="pj-delete-note">
                    “<span data-pj-delete-title></span>” will be removed from this site. Its folder on disk is never touched — this page only reads project folders.
                </p>
            </div>
            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-pj-close autofocus>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--danger">Delete</button>
            </div>
        </form>
    </dialog>

    @endif

    {{-- Icons cloned by the file tree (names are inserted with textContent, never HTML). --}}
    <template id="pj-icon-folder"><i class="fa-solid fa-folder pj-tree__folder-icon" aria-hidden="true"></i></template>
    <template id="pj-icon-file"><i class="fa-regular fa-file" aria-hidden="true"></i></template>
    <template id="pj-icon-chevron"><i class="fa-solid fa-chevron-right pj-tree__chevron" aria-hidden="true"></i></template>
    <template id="pj-icon-code"><i class="fa-solid fa-code" aria-hidden="true"></i></template>
    <template id="pj-icon-copy"><i class="fa-regular fa-copy" aria-hidden="true"></i></template>

    <div class="pj-toast" role="status" aria-live="polite" data-pj-toast></div>

    @php
        $pjData = [
            'projects' => $projectsData,
            'required' => $required,
            'labels' => $labels,
            'context' => old('_modal'),
            'old' => collect(session()->getOldInput())->except(['_token', '_method']),
            'errors' => $errors->getMessages(),
            'storeUrl' => route('projects.store'),
        ];
    @endphp
    <script type="application/json" id="pj-data">@json($pjData)</script>
</div>
@endsection
