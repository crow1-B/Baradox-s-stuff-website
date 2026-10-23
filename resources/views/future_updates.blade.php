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

<div class="nd-page nd-page--narrow fu" id="fu-root" data-view="{{ $view }}" data-done="{{ $showDone ? 'shown' : 'hidden' }}">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Future Updates</h1>
            <p class="nd-page__sub">
                What this site and its projects should get next — one list, with a done state.
                Ideas you are only offloading belong in Extra Notes.
            </p>
        </div>
    </header>

    <div class="nd-page__banners">
        @if (session('success'))
            <div class="nd-banner nd-banner--success" role="status" data-fu-dismissable>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <p class="nd-banner__text">{{ session('success') }}</p>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-fu-dismiss aria-label="Dismiss">
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

    @if ($canWrite)
        {{-- Capture bar. One field, one picker, one button — an idea should cost a sentence
             and a click. It is deliberately not a docked composer: this is a list. --}}
        <form method="POST" action="{{ route('future-updates.store') }}" class="fu-add" data-fu-add-form>
            @csrf
            <input type="hidden" name="_modal" value="add">

            <input type="text"
                   class="nd-input nd-written fu-add__text"
                   id="fu-add-content"
                   name="content"
                   dir="auto"
                   autocomplete="off"
                   maxlength="{{ $maxLength }}"
                   placeholder="Add a planned update…"
                   aria-label="What should be added or changed?"
                   value="{{ $addContent }}">

            @include('partials.fu-project-select', ['id' => 'fu-add-project', 'selected' => $addProject, 'label' => 'Project'])

            <button type="submit" class="nd-btn nd-btn--primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Add
            </button>
        </form>
    @endif

    @if ($filter)
        <div class="fu-filter" role="status">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <p>Showing only updates planned for <strong>{{ $filter->title }}</strong>.</p>
            <a class="nd-btn nd-btn--sm" href="{{ route('future-updates') }}">Show all</a>
        </div>
    @endif

    @if ($updates->isNotEmpty())
        <div class="nd-toolbar fu-toolbar">
            <div class="fu-segmented" role="group" aria-label="Layout">
                <button type="button" class="fu-seg" data-fu-view="grouped" aria-pressed="{{ $view === 'grouped' ? 'true' : 'false' }}">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>Grouped
                </button>
                <button type="button" class="fu-seg" data-fu-view="flat" aria-pressed="{{ $view === 'flat' ? 'true' : 'false' }}">
                    <i class="fa-solid fa-list" aria-hidden="true"></i>Flat
                </button>
            </div>

            <button type="button" class="nd-btn nd-btn--sm" data-fu-done-toggle aria-pressed="{{ $showDone ? 'true' : 'false' }}">
                <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                Show done
                <span class="nd-badge">{{ $doneCount }}</span>
            </button>

            <p class="nd-count fu-count" aria-live="polite">{{ $openCount }} open · {{ $doneCount }} done</p>
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
                                <a class="nd-link fu-link" href="{{ $group['link'] }}">
                                    <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>{{ $group['title'] }}
                                </a>
                                <span class="fu-status"><span class="nd-dot nd-dot--{{ $group['status'] }}" aria-hidden="true"></span>{{ $labels[$group['status']] }}</span>
                            @elseif ($group['kind'] === 'orphan')
                                {{-- Deleted project: never presented as if it had been written sitewide. --}}
                                <span class="fu-orphan">
                                    <i class="fa-solid fa-link-slash" aria-hidden="true"></i>was: {{ $group['title'] }}
                                </span>
                            @else
                                <span class="fu-sitewide">
                                    <i class="fa-solid fa-globe" aria-hidden="true"></i>Sitewide
                                </span>
                            @endif
                        </h2>
                        <p class="nd-count">{{ $group['open'] }} open · {{ $group['done'] }} done</p>
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
        <div class="nd-empty nd-empty--block" data-fu-empty-view @if ($showDone || $openCount > 0) hidden @endif>
            <p class="nd-empty__line">Nothing left open.</p>
            <p>Every update here is done. Turn on <strong>Show done</strong> to read them back.</p>
        </div>
    @else
        {{-- First empty state, in its two forms: nothing at all, or nothing for the
             project being filtered to. --}}
        <div class="nd-empty nd-empty--block">
            @if ($filter)
                <p class="nd-empty__line">Nothing planned for {{ $filter->title }}.</p>
                @if ($canWrite)
                    <p>Add the first one above — the picker is already set to this project.</p>
                @endif
            @else
                <p class="nd-empty__line">No plans written down yet.</p>
                @if ($canWrite)
                    <p>
                        Write down what the site or a project should get next, instead of leaving it
                        scattered across READMEs. Tick it off when it ships.
                    </p>
                @endif
            @endif
        </div>
    @endif

    @if ($canWrite)
        {{-- Edit ---------------------------------------------------------------- --}}
        <dialog class="nd-dialog" id="fu-edit-dialog" aria-labelledby="fu-edit-title">
            <form method="POST" action="" data-fu-edit-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="_modal" value="">

                <div class="nd-dialog__head">
                    <h2 class="nd-dialog__title" id="fu-edit-title">Edit update</h2>
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-fu-close aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="nd-dialog__body fu-edit">
                    <div class="nd-field">
                        <label class="nd-label" for="fu-edit-content">Update</label>
                        <textarea class="nd-input fu-edit-area"
                                  id="fu-edit-content"
                                  name="content"
                                  rows="4"
                                  dir="auto"
                                  maxlength="{{ $maxLength }}"></textarea>
                    </div>

                    <div class="nd-field">
                        <label class="nd-label" for="fu-edit-project">Project</label>
                        @include('partials.fu-project-select', ['id' => 'fu-edit-project', 'selected' => null])

                        {{-- Without this, an orphaned update would open with "Sitewide" selected —
                             the exact silent relabel the orphaned title exists to prevent. --}}
                        <p class="nd-note nd-note--warn" data-fu-orphan-note hidden></p>
                        <p class="nd-hint" data-fu-plain-note>Sitewide means the whole site rather than one project.</p>
                    </div>

                    <ul class="nd-error fu-errors" data-fu-errors hidden></ul>
                </div>

                <div class="nd-dialog__foot">
                    <button type="button" class="nd-btn nd-btn--ghost" data-fu-close>Cancel</button>
                    <button type="submit" class="nd-btn nd-btn--primary">Save changes</button>
                </div>
            </form>
        </dialog>

        {{-- Delete -------------------------------------------------------------- --}}
        <dialog class="nd-dialog nd-dialog--sm" id="fu-delete-dialog" aria-labelledby="fu-delete-title">
            <form method="POST" action="" data-fu-delete-form>
                @csrf
                @method('DELETE')

                <div class="nd-dialog__head">
                    <h2 class="nd-dialog__title" id="fu-delete-title">Delete this update?</h2>
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-fu-close aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="nd-dialog__body">
                    <p class="fu-preview nd-written" data-fu-delete-preview dir="auto"></p>
                    <p class="nd-hint">Ticking it off keeps it. Deleting cannot be undone.</p>
                </div>

                <div class="nd-dialog__foot">
                    <button type="button" class="nd-btn nd-btn--ghost" data-fu-close autofocus>Cancel</button>
                    <button type="submit" class="nd-btn nd-btn--danger">Delete</button>
                </div>
            </form>
        </dialog>
    @endif

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
