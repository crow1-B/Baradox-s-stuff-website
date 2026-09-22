@extends('layouts.app')

@push('head')
    @vite(['resources/css/projects.css', 'resources/js/projects.js'])
@endpush

@section('content')
@php
    $empty = [
        'future' => ['No plans yet', 'Ideas you want to build later live here. Give each one a planned start date.', 'Plan a project'],
        'in_work' => ['Nothing in progress', 'Start one of your future projects, or add what you’re working on right now.', 'Add a current project'],
        'done' => ['Nothing finished yet', 'Completed projects land here, with their dates and line counts.', 'Add a finished project'],
    ];
@endphp

<div class="pj" id="pj-root">
    <div class="mx-auto w-full max-w-5xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <h1>Projects</h1>
                <p class="pj-muted">The past, present and future, all in one place.</p>
            </div>
            <button type="button" class="pj-btn pj-btn--primary self-start sm:self-auto" data-pj-open="create" data-status="{{ $tab }}">
                <x-pj-icon name="plus" />
                New project
            </button>
        </header>

        @if (session('success'))
            <div class="pj-banner pj-banner--success mt-6" role="status" data-pj-dismissable>
                <x-pj-icon name="check" />
                <p class="flex-1">{{ session('success') }}</p>
                <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon -my-1" data-pj-dismiss aria-label="Dismiss">
                    <x-pj-icon name="x" />
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="pj-banner pj-banner--error mt-6" role="alert">
                <x-pj-icon name="alert" />
                <div>
                    <p class="font-medium">That didn’t save.</p>
                    <ul class="mt-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div role="tablist" aria-label="Project status" class="pj-tabs mt-8" data-pj-tabs>
            @foreach ($labels as $status => $label)
                <a role="tab"
                   id="tab-{{ $status }}"
                   href="{{ url('/projects?tab=' . $status) }}"
                   aria-controls="panel-{{ $status }}"
                   aria-selected="{{ $tab === $status ? 'true' : 'false' }}"
                   class="pj-tab"
                   data-pj-tab="{{ $status }}">
                    <span class="pj-dot pj-dot--{{ $status }}" aria-hidden="true"></span>
                    {{ $label }}
                    <span class="pj-tab__count">{{ $grouped[$status]->count() }}<span class="pj-sr-only"> {{ Str::plural('project', $grouped[$status]->count()) }}</span></span>
                </a>
            @endforeach
        </div>

        @foreach ($labels as $status => $label)
            <section role="tabpanel" id="panel-{{ $status }}" aria-labelledby="tab-{{ $status }}" class="mt-6" @if ($tab !== $status) hidden @endif>
                @if ($grouped[$status]->isEmpty())
                    <div class="pj-empty">
                        <span class="pj-empty__icon"><x-pj-icon name="layers" /></span>
                        <h2 class="text-base">{{ $empty[$status][0] }}</h2>
                        <p class="pj-muted max-w-sm text-sm">{{ $empty[$status][1] }}</p>
                        <button type="button" class="pj-btn mt-2" data-pj-open="create" data-status="{{ $status }}">
                            <x-pj-icon name="plus" />
                            {{ $empty[$status][2] }}
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
                        @foreach ($grouped[$status] as $project)
                            @include('partials.project-card', ['project' => $project])
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    {{-- Create / edit ------------------------------------------------------ --}}
    <dialog class="pj-dialog" id="pj-project-dialog" aria-labelledby="pj-project-dialog-title">
        <form method="POST" action="{{ route('projects.store') }}" novalidate data-pj-project-form>
            @csrf
            <input type="hidden" name="_method" value="POST" disabled>
            <input type="hidden" name="_modal" value="create">

            <div class="pj-dialog__head">
                <h2 class="pj-dialog__title" id="pj-project-dialog-title">New project</h2>
                <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" data-pj-close aria-label="Close">
                    <x-pj-icon name="x" />
                </button>
            </div>

            <div class="pj-dialog__body">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-pj-field form="pf" name="title" label="Title" full placeholder="drone sound classifier" />

                    <fieldset class="pj-field sm:col-span-2" data-pj-field="status" data-pj-create-only>
                        <legend class="pj-label mb-1.5">Status</legend>
                        <div class="pj-segmented">
                            @foreach ($labels as $status => $label)
                                <input type="radio" name="status" id="pf-status-{{ $status }}" value="{{ $status }}">
                                <label for="pf-status-{{ $status }}"><span class="pj-dot pj-dot--{{ $status }}" aria-hidden="true"></span>{{ $label }}</label>
                            @endforeach
                        </div>
                        <p class="pj-error" id="pf-status-error" data-pj-error hidden></p>
                    </fieldset>

                    <x-pj-field form="pf" name="description" label="Description" type="textarea" full />
                    <x-pj-field form="pf" name="languages" label="Languages" hint="Comma separated" placeholder="Python, C++" />
                    <x-pj-field form="pf" name="people" label="People" hint="Comma separated" placeholder="Ali, Zahra" />
                    <x-pj-field form="pf" name="start_date" label="Start date" type="date" />
                    <x-pj-field form="pf" name="finish_date" label="Finish date" type="date" />
                    <x-pj-field form="pf" name="root_path" label="Project folder" type="path" full placeholder="drone_sound_KNN"
                        hint="Relative to the projects folder. Pasting the full path works too — only the relative part is stored." />
                    <x-pj-field form="pf" name="lines_of_code" label="Lines of code" type="number" placeholder="4200" />
                    <x-pj-field form="pf" name="github_link" label="GitHub link" type="url" placeholder="https://github.com/…" />
                </div>
            </div>

            <div class="pj-dialog__foot">
                <button type="button" class="pj-btn" data-pj-close>Cancel</button>
                <button type="submit" class="pj-btn pj-btn--primary" data-pj-submit>Create project</button>
            </div>
        </form>
    </dialog>

    {{-- Status transition -------------------------------------------------- --}}
    <dialog class="pj-dialog pj-dialog--sm" id="pj-status-dialog" aria-labelledby="pj-status-dialog-title" aria-describedby="pj-status-note">
        <form method="POST" action="" novalidate data-pj-status-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">
            <input type="hidden" name="status" value="">

            <div class="pj-dialog__head">
                <h2 class="pj-dialog__title" id="pj-status-dialog-title">Move project</h2>
                <button type="button" class="pj-btn pj-btn--ghost pj-btn--sm pj-btn--icon" data-pj-close aria-label="Close">
                    <x-pj-icon name="x" />
                </button>
            </div>

            <div class="pj-dialog__body flex flex-col gap-4">
                <p class="pj-muted text-sm" id="pj-status-note"></p>
                <p class="pj-error" id="sf-status-error" data-pj-error hidden></p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-pj-field form="sf" name="start_date" label="Start date" type="date" />
                    <x-pj-field form="sf" name="finish_date" label="Finish date" type="date" />
                    <x-pj-field form="sf" name="root_path" label="Project folder" type="path" full placeholder="drone_sound_KNN" />
                    <x-pj-field form="sf" name="lines_of_code" label="Lines of code" type="number" placeholder="4200" />
                </div>
                {{-- Errors on fields the move doesn't show (e.g. a missing description) land here. --}}
                <ul class="pj-error" data-pj-other-errors hidden></ul>
            </div>

            <div class="pj-dialog__foot">
                <button type="button" class="pj-btn" data-pj-close>Cancel</button>
                <button type="submit" class="pj-btn pj-btn--primary" data-pj-submit>Move</button>
            </div>
        </form>
    </dialog>

    {{-- Delete ------------------------------------------------------------- --}}
    <dialog class="pj-dialog pj-dialog--sm" id="pj-delete-dialog" aria-labelledby="pj-delete-dialog-title" aria-describedby="pj-delete-note">
        <form method="POST" action="" data-pj-delete-form>
            @csrf
            @method('DELETE')
            <div class="pj-dialog__head">
                <h2 class="pj-dialog__title" id="pj-delete-dialog-title">Delete project?</h2>
            </div>
            <div class="pj-dialog__body">
                <p class="pj-muted text-sm" id="pj-delete-note">
                    “<span data-pj-delete-title></span>” will be removed from this site. Its folder on disk is never touched — this page only reads project folders.
                </p>
            </div>
            <div class="pj-dialog__foot">
                <button type="button" class="pj-btn" data-pj-close autofocus>Cancel</button>
                <button type="submit" class="pj-btn pj-btn--danger">Delete</button>
            </div>
        </form>
    </dialog>

    {{-- Icons cloned by the file tree (names are inserted with textContent, never HTML). --}}
    <template id="pj-icon-folder"><x-pj-icon name="folder" class="pj-tree__folder-icon" /></template>
    <template id="pj-icon-file"><x-pj-icon name="file" /></template>
    <template id="pj-icon-chevron"><x-pj-icon name="chevron" class="pj-tree__chevron" /></template>
    <template id="pj-icon-code"><x-pj-icon name="code" /></template>
    <template id="pj-icon-copy"><x-pj-icon name="copy" /></template>

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
