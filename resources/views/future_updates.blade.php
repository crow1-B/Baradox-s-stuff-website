@extends('layouts.app')

@push('head')
    @vite(['resources/css/future-updates.css', 'resources/js/future-updates.js'])
@endpush

@section('content')
@php
    // The edit and delete dialogs are shared and filled in by future-updates.js from this.
    $updateData = $updates->mapWithKeys(fn ($update) => [$update->id => [
        'id' => $update->id,
        'content' => $update->content,
        'project_id' => $update->project_id,
        'orphaned_project_title' => $update->orphaned_project_title,
        'urls' => [
            'update' => route('future-updates.update', $update),
            'destroy' => route('future-updates.destroy', $update),
        ],
    ]]);

    $context = old('_modal');
    // Old input is only put back into the form it was typed in — a failed edit must not
    // leave its text sitting in the add bar.
    $addContent = $context === 'add' ? old('content') : null;
    // Looking at one project's list and adding to it means adding to that project.
    $addProject = $context === 'add' ? old('project_id') : $filter?->id;
@endphp

<div class="fu" id="fu-root" data-view="{{ $view }}" data-done="{{ $showDone ? 'shown' : 'hidden' }}">
    <div class="mx-auto w-full max-w-4xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-1.5">
            <h1>Future Updates</h1>
            <p class="fu-muted">
                What this site and its projects should get next — one list, with a done state.
                Ideas you are only offloading belong in Extra Notes.
            </p>
        </header>

        @if (session('success'))
            <div class="fu-banner fu-banner--success mt-6" role="status" data-fu-dismissable>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <p class="flex-1">{{ session('success') }}</p>
                <button type="button" class="fu-act -my-1" data-fu-dismiss aria-label="Dismiss">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="fu-banner fu-banner--error mt-6" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
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

        {{-- Capture bar. One field, one picker, one button — an idea should cost a sentence
             and a click. It is deliberately not a docked composer: this is a list. --}}
        <form method="POST" action="{{ route('future-updates.store') }}" class="fu-add mt-7" data-fu-add-form>
            @csrf
            <input type="hidden" name="_modal" value="add">

            <input type="text"
                   class="fu-input fu-add__text"
                   id="fu-add-content"
                   name="content"
                   dir="auto"
                   autocomplete="off"
                   maxlength="{{ $maxLength }}"
                   placeholder="Add a planned update…"
                   aria-label="What should be added or changed?"
                   value="{{ $addContent }}">

            @include('partials.fu-project-select', ['id' => 'fu-add-project', 'selected' => $addProject, 'label' => 'Project'])

            <button type="submit" class="fu-btn fu-btn--primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Add
            </button>
        </form>

        @if ($filter)
            <div class="fu-filter mt-4" role="status">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <p class="flex-1">Showing only updates planned for <strong>{{ $filter->title }}</strong>.</p>
                <a class="fu-btn fu-btn--sm" href="{{ route('future-updates') }}">Show all</a>
            </div>
        @endif

        @if ($updates->isNotEmpty())
            <div class="fu-toolbar mt-6">
                <div class="fu-segmented" role="group" aria-label="Layout">
                    <button type="button" class="fu-seg" data-fu-view="grouped" aria-pressed="{{ $view === 'grouped' ? 'true' : 'false' }}">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>Grouped
                    </button>
                    <button type="button" class="fu-seg" data-fu-view="flat" aria-pressed="{{ $view === 'flat' ? 'true' : 'false' }}">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>Flat
                    </button>
                </div>

                <button type="button" class="fu-btn fu-toggle" data-fu-done-toggle aria-pressed="{{ $showDone ? 'true' : 'false' }}">
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    Show done
                    <span class="fu-badge">{{ $doneCount }}</span>
                </button>

                <p class="fu-count" aria-live="polite">
                    {{ $openCount }} open<span class="fu-faint"> · {{ $doneCount }} done</span>
                </p>
            </div>

            {{-- Both layouts are rendered; the root's data-view decides which one is shown
                 (CSS, not the hidden property, so nothing has to be re-shown on Back).
                 Rendering both keeps the toggle instant and server-truthful — the list is
                 small enough that the duplicated rows cost nothing. --}}
            <div class="fu-panel" data-fu-panel="grouped">
                {{-- The heading id is the loop index, not a slug of the group key: Str::slug()
                     returns '' for an Arabic project title, and two of those would collide. --}}
                @foreach ($groups as $group)
                    <section class="fu-group" data-fu-group data-open="{{ $group['open'] }}" aria-labelledby="fu-group-{{ $loop->index }}">
                        <div class="fu-group__head">
                            <h2 class="fu-group__title" id="fu-group-{{ $loop->index }}">
                                @if ($group['kind'] === 'project')
                                    <a class="fu-link" href="{{ $group['link'] }}">
                                        <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>{{ $group['title'] }}
                                    </a>
                                    <span class="fu-status fu-status--{{ $group['status'] }}">{{ $labels[$group['status']] }}</span>
                                @elseif ($group['kind'] === 'orphan')
                                    {{-- Deleted project: never presented as if it had been written sitewide. --}}
                                    <span class="fu-orphan">
                                        <i class="fa-solid fa-link-slash" aria-hidden="true"></i>was: {{ $group['title'] }}
                                    </span>
                                @else
                                    <span class="fu-group__sitewide">
                                        <i class="fa-solid fa-globe" aria-hidden="true"></i>Sitewide
                                    </span>
                                @endif
                            </h2>
                            <p class="fu-count">
                                {{ $group['open'] }} open<span class="fu-faint"> · {{ $group['done'] }} done</span>
                            </p>
                        </div>

                        <ul class="fu-list" role="list">
                            @foreach ($group['updates'] as $update)
                                @include('partials.fu-row', ['update' => $update, 'showProject' => false])
                            @endforeach
                        </ul>

                        {{-- Third empty state: this group still exists, but everything in it
                             is done and done is hidden. --}}
                        <p class="fu-group__empty" data-fu-group-empty @if ($showDone || $group['open'] > 0) hidden @endif>
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            Nothing open here — {{ $group['done'] }} {{ Str::plural('update', $group['done']) }} done.
                        </p>
                    </section>
                @endforeach
            </div>

            <div class="fu-panel" data-fu-panel="flat">
                <ul class="fu-list fu-list--flat" role="list">
                    @foreach ($updates as $update)
                        @include('partials.fu-row', ['update' => $update, 'showProject' => true])
                    @endforeach
                </ul>
            </div>

            {{-- Second empty state: there are updates, but none of them is open and done is
                 hidden, so both layouts render nothing. --}}
            <div class="fu-empty mt-6" data-fu-empty-view @if ($showDone || $openCount > 0) hidden @endif>
                <span class="fu-empty__icon"><i class="fa-solid fa-check-double" aria-hidden="true"></i></span>
                <h2 class="text-base">Nothing left open</h2>
                <p class="fu-muted max-w-sm text-sm">
                    Every update here is done. Turn on <strong>Show done</strong> to read them back.
                </p>
            </div>
        @else
            {{-- First empty state, in its two forms: nothing at all, or nothing for the
                 project being filtered to. --}}
            <div class="fu-empty mt-8">
                <span class="fu-empty__icon"><i class="fa-regular fa-lightbulb" aria-hidden="true"></i></span>
                @if ($filter)
                    <h2 class="text-base">Nothing planned for {{ $filter->title }}</h2>
                    <p class="fu-muted max-w-sm text-sm">
                        Add the first one above — the picker is already set to this project.
                    </p>
                @else
                    <h2 class="text-base">No planned updates yet</h2>
                    <p class="fu-muted max-w-sm text-sm">
                        Write down what the site or a project should get next, instead of leaving it
                        scattered across READMEs. Tick it off when it ships.
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- Edit ---------------------------------------------------------------- --}}
    <dialog class="fu-dialog" id="fu-edit-dialog" aria-labelledby="fu-edit-title">
        <form method="POST" action="" data-fu-edit-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">

            <div class="fu-dialog__head">
                <h2 class="fu-dialog__title" id="fu-edit-title">Edit update</h2>
                <button type="button" class="fu-act" data-fu-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="fu-dialog__body">
                <label class="fu-label" for="fu-edit-content">Update</label>
                <textarea class="fu-input fu-input--area"
                          id="fu-edit-content"
                          name="content"
                          rows="4"
                          dir="auto"
                          maxlength="{{ $maxLength }}"></textarea>

                <label class="fu-label mt-4" for="fu-edit-project">Project</label>
                @include('partials.fu-project-select', ['id' => 'fu-edit-project', 'selected' => null])

                {{-- Without this, an orphaned update would open with "Sitewide" selected —
                     the exact silent relabel the orphaned title exists to prevent. --}}
                <p class="fu-hint fu-hint--warn" data-fu-orphan-note hidden></p>
                <p class="fu-hint" data-fu-plain-note>Sitewide means the whole site rather than one project.</p>

                <ul class="fu-error mt-3" data-fu-errors hidden></ul>
            </div>

            <div class="fu-dialog__foot">
                <button type="button" class="fu-btn" data-fu-close>Cancel</button>
                <button type="submit" class="fu-btn fu-btn--primary">Save changes</button>
            </div>
        </form>
    </dialog>

    {{-- Delete -------------------------------------------------------------- --}}
    <dialog class="fu-dialog fu-dialog--sm" id="fu-delete-dialog" aria-labelledby="fu-delete-title">
        <form method="POST" action="" data-fu-delete-form>
            @csrf
            @method('DELETE')

            <div class="fu-dialog__head">
                <h2 class="fu-dialog__title" id="fu-delete-title">Delete this update?</h2>
                <button type="button" class="fu-act" data-fu-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="fu-dialog__body">
                <p class="fu-preview" data-fu-delete-preview dir="auto"></p>
                <p class="fu-hint">Ticking it off keeps it. Deleting cannot be undone.</p>
            </div>

            <div class="fu-dialog__foot">
                <button type="button" class="fu-btn" data-fu-close autofocus>Cancel</button>
                <button type="submit" class="fu-btn fu-btn--danger">Delete</button>
            </div>
        </form>
    </dialog>

    @php
        $fuData = [
            'updates' => $updateData,
            'context' => $context,
            'old' => collect(session()->getOldInput())->only(['content', 'project_id']),
            'errors' => $errors->all(),
        ];
    @endphp
    <script type="application/json" id="fu-data">@json($fuData)</script>
</div>
@endsection
