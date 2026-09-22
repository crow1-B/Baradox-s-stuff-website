@extends('layouts.app')

@push('head')
    {{-- Don't keep a Back/Forward snapshot of this page: it can hold decrypted memories. --}}
    <meta name="turbo-cache-control" content="no-cache">
    @vite(['resources/css/diary.css', 'resources/js/diary.js'])
@endpush

@section('content')
@php
    $revealed = $revealedId ?? null;

    $reopen = session('dy_reopen') ?? old('_modal');

    $dateOf = fn ($entry) => $entry->event_date ?? $entry->created_at;
    $grouped = $entries->groupBy(fn ($entry) => $dateOf($entry)->format('Y-m'));

    $entryData = $entries->mapWithKeys(function ($entry) use ($dateOf) {
        $locked = $entry->isLocked();

        return [$entry->id => [
            'id' => $entry->id,
            'title' => $entry->title,
            'locked' => $locked,
            'date' => $dateOf($entry)->format('Y-m-d'),
            'dateLabel' => $dateOf($entry)->format('l, j F Y'),
            'dated' => $entry->event_date !== null,
            'content' => $locked ? null : $entry->content,
            'event_date' => $locked ? null : $entry->event_date?->format('Y-m-d'),
            'people' => $locked ? [] : $entry->people->pluck('name')->values(),
            'photo' => $locked || !$entry->photo ? null : [
                'id' => $entry->photo->id,
                'full' => route('photo.view', $entry->photo->id),
            ],
            'urls' => [
                'update' => url('/diary/' . $entry->id),
                'destroy' => url('/diary/' . $entry->id),
                'lock' => url('/diary/' . $entry->id . '/lock'),
                'unlock' => url('/diary/' . $entry->id . '/unlock'),
            ],
        ]];
    });
@endphp

<div class="dy" id="dy-root">
    <div class="mx-auto w-full max-w-3xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <h1>Diary</h1>
                <p class="dy-muted">{{ $Diary }}</p>
            </div>
            <button type="button" class="dy-btn dy-btn--primary self-start sm:self-auto" data-dy-open="create">
                <x-dy-icon name="plus" />
                New memory
            </button>
        </header>

        @if (session('success'))
            <div class="dy-banner dy-banner--success mt-6" role="status" data-dy-dismissable>
                <x-dy-icon name="check" />
                <p class="flex-1">{{ session('success') }}</p>
                <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon -my-1" data-dy-dismiss aria-label="Dismiss">
                    <x-dy-icon name="x" />
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="dy-banner dy-banner--error mt-6" role="alert">
                <x-dy-icon name="alert" />
                <div>
                    <p class="font-medium">That didn't go through.</p>
                    <ul class="mt-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($entries->isEmpty())
            <div class="dy-empty mt-8">
                <span class="dy-empty__icon"><x-dy-icon name="book" /></span>
                <h2 class="text-base">Nothing written down yet</h2>
                <p class="dy-muted max-w-sm text-sm">
                    Write the first one. You can lock any memory afterwards, with a key only you hold.
                </p>
                <button type="button" class="dy-btn mt-2" data-dy-open="create">
                    <x-dy-icon name="plus" />
                    Write the first memory
                </button>
            </div>
        @else
            {{-- Filtering is client-side over the rendered cards. Nothing here asks the server
                 for anything, so a locked entry is never decrypted to answer a search. --}}
            <div class="dy-toolbar mt-7" data-dy-toolbar>
                <div class="dy-search">
                    <x-dy-icon name="search" class="dy-search__icon" />
                    <input type="search"
                           class="dy-input dy-search__input"
                           id="dy-search"
                           placeholder="Search memories"
                           aria-label="Search memories"
                           aria-describedby="dy-search-scope"
                           autocomplete="off"
                           dir="auto"
                           data-dy-search>
                </div>
                <p class="dy-count" data-dy-count aria-live="polite">{{ $entries->count() }} {{ Str::plural('memory', $entries->count()) }}</p>
            </div>

            {{-- Saying this out loud matters: a search box that quietly skipped locked entries
                 would misrepresent what the diary holds. --}}
            <p class="dy-scope" id="dy-search-scope">
                <x-dy-icon name="lock" class="dy-scope__icon" />
                Searches every title, and the text of unlocked memories. Locked memories are stored
                as ciphertext — only their titles can match, and they list no people to filter by.
            </p>

            <div class="dy-filter" data-dy-filter hidden>
                <span class="dy-filter__label">Showing memories with</span>
                <span class="dy-chip dy-chip--active"><x-dy-icon name="user" /><span data-dy-filter-name dir="auto"></span></span>
                <button type="button" class="dy-btn dy-btn--sm" data-dy-clear-person>
                    <x-dy-icon name="x" />
                    Show everything
                </button>
            </div>

            @foreach ($grouped as $month => $monthEntries)
                <section class="dy-group" data-dy-group>
                    <h2 class="dy-month">
                        <span>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span>
                        <span class="dy-month__count" data-dy-group-count>{{ $monthEntries->count() }}</span>
                    </h2>

                    <div class="dy-cards">
                        @foreach ($monthEntries as $entry)
                            @php
                                $locked = $entry->isLocked();
                                $when = $dateOf($entry);
                            @endphp

                            <article class="dy-card @if ($locked) dy-card--locked @endif"
                                     data-dy-entry
                                     data-entry-id="{{ $entry->id }}"
                                     data-locked="{{ $locked ? '1' : '0' }}">

                                <div class="dy-card__head">
                                    <div class="dy-card__heading">
                                        <h3 class="dy-card__title" dir="auto">{{ $entry->title }}</h3>
                                        <p class="dy-card__meta">
                                            <time datetime="{{ $when->format('Y-m-d') }}">{{ $when->format('j M Y') }}</time>
                                            @unless ($entry->event_date)
                                                <span class="dy-faint">· written then</span>
                                            @endunless
                                        </p>
                                    </div>

                                    @if ($locked)
                                        <span class="dy-badge"><x-dy-icon name="lock" />Locked</span>
                                    @endif
                                </div>

                                @if ($locked)
                                    {{-- Deliberately the end of it. No snippet, no thumbnail, no
                                         people: a locked card must give away nothing but the fact
                                         that it exists. --}}
                                    <p class="dy-card__sealed">
                                        Sealed. Its key and algorithm open it — nothing else about it is shown here.
                                    </p>
                                @else
                                    <div class="dy-card__body">
                                        <p class="dy-card__snippet" dir="auto">{{ Str::limit($entry->content, 300) }}</p>

                                        @if ($entry->photo)
                                            <button type="button" class="dy-card__thumb" data-dy-open="view" data-entry="{{ $entry->id }}"
                                                    aria-label="Open {{ $entry->title }}">
                                                <img src="{{ route('photo.thumbnail', $entry->photo->id) }}" alt="" loading="lazy">
                                            </button>
                                        @endif
                                    </div>

                                    @if ($entry->people->isNotEmpty())
                                        <div class="dy-chips">
                                            @foreach ($entry->people as $person)
                                                <button type="button" class="dy-chip" data-dy-person="{{ $person->name }}">
                                                    <x-dy-icon name="user" />
                                                    <span dir="auto">{{ $person->name }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                <div class="dy-card__actions">
                                    @if ($locked)
                                        <button type="button" class="dy-btn dy-btn--sm" data-dy-open="reveal" data-entry="{{ $entry->id }}">
                                            <x-dy-icon name="eye" />
                                            Open
                                        </button>
                                        {{-- Rendered disabled rather than omitted: a card with fewer
                                             buttons than its neighbours reads as a bug. --}}
                                        <button type="button" class="dy-btn dy-btn--sm" disabled title="Unlock this memory before editing it">
                                            <x-dy-icon name="pencil" />
                                            Edit
                                        </button>
                                        <button type="button" class="dy-btn dy-btn--sm" data-dy-open="unlock" data-entry="{{ $entry->id }}">
                                            <x-dy-icon name="unlock" />
                                            Unlock
                                        </button>
                                    @else
                                        <button type="button" class="dy-btn dy-btn--sm" data-dy-open="view" data-entry="{{ $entry->id }}">
                                            <x-dy-icon name="eye" />
                                            Open
                                        </button>
                                        <button type="button" class="dy-btn dy-btn--sm" data-dy-open="edit" data-entry="{{ $entry->id }}">
                                            <x-dy-icon name="pencil" />
                                            Edit
                                        </button>
                                        <button type="button" class="dy-btn dy-btn--sm" data-dy-open="lock" data-entry="{{ $entry->id }}">
                                            <x-dy-icon name="lock" />
                                            Lock
                                        </button>
                                    @endif

                                    <button type="button" class="dy-btn dy-btn--sm dy-btn--icon dy-danger ml-auto"
                                            data-dy-open="delete" data-entry="{{ $entry->id }}" aria-label="Delete {{ $entry->title }}">
                                        <x-dy-icon name="trash" />
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="dy-empty mt-6" data-dy-no-matches hidden>
                <span class="dy-empty__icon"><x-dy-icon name="search" /></span>
                <h2 class="text-base">Nothing matches</h2>
                <p class="dy-muted max-w-sm text-sm" data-dy-no-matches-note></p>
                <button type="button" class="dy-btn mt-2" data-dy-clear>Clear the filters</button>
            </div>
        @endif
    </div>

    {{-- Write / edit -------------------------------------------------------- --}}
    <dialog class="dy-dialog" id="dy-entry-dialog" aria-labelledby="dy-entry-dialog-title">
        <form method="POST" action="{{ route('diaryPage') }}" enctype="multipart/form-data" novalidate data-dy-entry-form>
            @csrf
            <input type="hidden" name="_method" value="POST" disabled>
            <input type="hidden" name="_modal" value="create">

            <div class="dy-dialog__head">
                <h2 class="dy-dialog__title" id="dy-entry-dialog-title">New memory</h2>
                <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon" data-dy-close aria-label="Close">
                    <x-dy-icon name="x" />
                </button>
            </div>

            <div class="dy-dialog__body">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-dy-field form="ef" name="title" label="Title" dir="auto" full required />
                    <x-dy-field form="ef" name="content" label="The memory" type="textarea" dir="auto" full required />
                    <x-dy-field form="ef" name="event_date" label="When it happened" type="date"
                        hint="Leave it empty and the day you wrote it is used." />
                    <x-dy-field form="ef" name="people" label="People" dir="auto" placeholder="ali, hussein"
                        hint="Comma separated. They become filter chips." />

                    <fieldset class="dy-field sm:col-span-2" data-dy-field="photo_source">
                        <legend class="dy-label mb-1.5">Photo</legend>
                        <div class="dy-segmented" data-dy-photo-modes>
                            <input type="radio" name="photo_source" id="ef-photo-keep" value="keep" data-dy-edit-only>
                            <label for="ef-photo-keep" data-dy-edit-only>Keep</label>

                            <input type="radio" name="photo_source" id="ef-photo-none" value="none" checked>
                            <label for="ef-photo-none">None</label>

                            <input type="radio" name="photo_source" id="ef-photo-existing" value="existing">
                            <label for="ef-photo-existing">Pick one</label>

                            <input type="radio" name="photo_source" id="ef-photo-upload" value="upload">
                            <label for="ef-photo-upload">Upload</label>
                        </div>
                        <p class="dy-error" id="ef-photo_source-error" data-dy-error hidden></p>

                        <div class="dy-picker mt-3" data-dy-photo-pane="existing" hidden>
                            @if ($photos->isEmpty())
                                <p class="dy-hint">No unprotected photos yet — upload one here, or add photos in the Photos page first.</p>
                            @else
                                <div class="dy-picker__grid" role="radiogroup" aria-label="Pick a photo">
                                    @foreach ($photos as $photo)
                                        <label class="dy-picker__item">
                                            <input type="radio" name="photo_id" value="{{ $photo->id }}">
                                            <img src="{{ route('photo.thumbnail', $photo->id) }}" alt="Photo {{ $photo->id }}" loading="lazy">
                                            <span class="dy-picker__tick" aria-hidden="true"><x-dy-icon name="check" /></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <p class="dy-error" id="ef-photo_id-error" data-dy-error hidden></p>
                        </div>

                        <div class="mt-3" data-dy-photo-pane="upload" hidden>
                            <input class="dy-input" type="file" id="ef-photo" name="photo" accept="image/*"
                                   aria-describedby="ef-photo-hint ef-photo-error">
                            <p class="dy-hint" id="ef-photo-hint">jpg, png, webp or gif, up to 20 MB. It becomes a real photo in the Photos page. Pick it again if the form comes back with an error.</p>
                            <p class="dy-error" id="ef-photo-error" data-dy-error hidden></p>
                        </div>

                        <p class="dy-hint mt-2" data-dy-photo-current hidden>Currently attached: <span data-dy-photo-current-name></span></p>
                    </fieldset>
                </div>
            </div>

            <div class="dy-dialog__foot">
                <button type="button" class="dy-btn" data-dy-close>Cancel</button>
                <button type="submit" class="dy-btn dy-btn--primary" data-dy-submit>Save memory</button>
            </div>
        </form>
    </dialog>

    {{-- Read an unlocked memory --------------------------------------------- --}}
    <dialog class="dy-dialog dy-dialog--wide" id="dy-view-dialog" aria-labelledby="dy-view-dialog-title">
        <div class="dy-dialog__head">
            <div>
                <h2 class="dy-dialog__title" id="dy-view-dialog-title" dir="auto"></h2>
                <p class="dy-dialog__sub" data-dy-view-date></p>
            </div>
            <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon" data-dy-close aria-label="Close">
                <x-dy-icon name="x" />
            </button>
        </div>
        <div class="dy-dialog__body">
            <img class="dy-reading__photo" data-dy-view-photo alt="" hidden>
            <p class="dy-reading" data-dy-view-content dir="auto"></p>
            <div class="dy-chips mt-4" data-dy-view-people hidden></div>
        </div>
        <div class="dy-dialog__foot">
            <button type="button" class="dy-btn" data-dy-close>Close</button>
        </div>
    </dialog>

    {{-- Lock ---------------------------------------------------------------- --}}
    <dialog class="dy-dialog dy-dialog--sm" id="dy-lock-dialog" aria-labelledby="dy-lock-dialog-title">
        <form method="POST" action="" novalidate data-dy-lock-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">

            <div class="dy-dialog__head">
                <h2 class="dy-dialog__title" id="dy-lock-dialog-title">Lock this memory</h2>
                <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon" data-dy-close aria-label="Close">
                    <x-dy-icon name="x" />
                </button>
            </div>

            <div class="dy-dialog__body">
                <p class="dy-note dy-note--warn">
                    <x-dy-icon name="alert" />
                    <span>
                        The key is never stored — only a hash of it. Lose it and
                        “<span data-dy-lock-title dir="auto"></span>” is gone for good, with no way back.
                    </span>
                </p>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <x-dy-algorithm form="lf" hint="You will need this algorithm AND the key to open, unlock or delete it." />

                    <div class="dy-field" data-dy-field="key">
                        <label class="dy-label" for="lf-key">Key<span class="dy-req" aria-hidden="true">*</span></label>
                        <input class="dy-input dy-mono" type="password" id="lf-key" name="key" minlength="8"
                               autocomplete="new-password" aria-describedby="lf-key-hint lf-key-error" data-dy-lock-key>
                        <p class="dy-hint" id="lf-key-hint">At least 8 characters.</p>
                        <p class="dy-error" id="lf-key-error" data-dy-error hidden></p>
                    </div>

                    <label class="dy-check" for="lf-generate">
                        <input type="checkbox" name="generate_key" id="lf-generate" value="1" data-dy-lock-generate>
                        <span>Generate a strong key for me instead</span>
                    </label>
                </div>
            </div>

            <div class="dy-dialog__foot">
                <button type="button" class="dy-btn" data-dy-close>Cancel</button>
                <button type="submit" class="dy-btn dy-btn--primary" data-dy-submit>Lock it</button>
            </div>
        </form>
    </dialog>

    {{-- Unlock permanently -------------------------------------------------- --}}
    <dialog class="dy-dialog dy-dialog--sm" id="dy-unlock-dialog" aria-labelledby="dy-unlock-dialog-title">
        <form method="POST" action="" novalidate data-dy-unlock-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">

            <div class="dy-dialog__head">
                <h2 class="dy-dialog__title" id="dy-unlock-dialog-title">Unlock permanently</h2>
                <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon" data-dy-close aria-label="Close">
                    <x-dy-icon name="x" />
                </button>
            </div>

            <div class="dy-dialog__body">
                <p class="dy-muted text-sm">
                    This writes “<span data-dy-unlock-title dir="auto"></span>” back as plain text and throws its key away.
                    To read it just once instead, use <strong>Open</strong>.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <x-dy-algorithm form="uf" placeholder />
                    <div class="dy-field" data-dy-field="key">
                        <label class="dy-label" for="uf-key">Key<span class="dy-req" aria-hidden="true">*</span></label>
                        <input class="dy-input dy-mono" type="password" id="uf-key" name="key" required aria-required="true"
                               autocomplete="off" aria-describedby="uf-key-error">
                        <p class="dy-error" id="uf-key-error" data-dy-error hidden></p>
                    </div>
                </div>
            </div>

            <div class="dy-dialog__foot">
                <button type="button" class="dy-btn" data-dy-close>Cancel</button>
                <button type="submit" class="dy-btn dy-btn--primary" data-dy-submit>Unlock</button>
            </div>
        </form>
    </dialog>

    {{-- Delete -------------------------------------------------------------- --}}
    <dialog class="dy-dialog dy-dialog--sm" id="dy-delete-dialog" aria-labelledby="dy-delete-dialog-title">
        <form method="POST" action="" novalidate data-dy-delete-form>
            @csrf
            @method('DELETE')
            <input type="hidden" name="_modal" value="">

            <div class="dy-dialog__head">
                <h2 class="dy-dialog__title" id="dy-delete-dialog-title">Delete this memory?</h2>
            </div>

            <div class="dy-dialog__body">
                <p class="dy-muted text-sm">
                    “<span data-dy-delete-title dir="auto"></span>” will be gone for good. There is no undo.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-4" data-dy-delete-credentials hidden>
                    <p class="dy-note dy-note--warn">
                        <x-dy-icon name="lock" />
                        <span>This memory is locked, so deleting it needs its key and algorithm too.</span>
                    </p>
                    <x-dy-algorithm form="df" placeholder />
                    <div class="dy-field" data-dy-field="key">
                        <label class="dy-label" for="df-key">Key<span class="dy-req" aria-hidden="true">*</span></label>
                        <input class="dy-input dy-mono" type="password" id="df-key" name="key"
                               autocomplete="off" aria-describedby="df-key-error">
                        <p class="dy-error" id="df-key-error" data-dy-error hidden></p>
                    </div>
                </div>
            </div>

            <div class="dy-dialog__foot">
                <button type="button" class="dy-btn" data-dy-close autofocus>Cancel</button>
                <button type="submit" class="dy-btn dy-btn--danger" data-dy-submit>Delete</button>
            </div>
        </form>
    </dialog>

    {{-- Generated key — shown once, and only once ---------------------------- --}}
    @php $generatedKey = session('generated_key'); @endphp
    @if ($generatedKey)
        <dialog class="dy-dialog dy-dialog--sm" id="dy-key-dialog" aria-labelledby="dy-key-dialog-title" data-dy-key-dialog>
            <div class="dy-dialog__head">
                <h2 class="dy-dialog__title" id="dy-key-dialog-title">
                    <x-dy-icon name="key" class="dy-dialog__titleicon" />
                    Your key
                </h2>
            </div>

            <div class="dy-dialog__body">
                <p class="dy-note dy-note--warn">
                    <x-dy-icon name="alert" />
                    <span>
                        This is the only time this key is ever shown. It is not stored anywhere — only a
                        hash of it is. Without it that memory can never be opened, unlocked or deleted.
                    </span>
                </p>

                {{-- Big and monospace on purpose: this is meant to be read character by character
                     and copied correctly, not glanced at. --}}
                <p class="dy-key" data-dy-key-value>{{ $generatedKey }}</p>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="dy-btn" data-dy-copy-key>
                        <x-dy-icon name="copy" />
                        Copy the key
                    </button>
                    <span class="dy-copied" data-dy-copied role="status" aria-live="polite"></span>
                </div>

                <label class="dy-check dy-check--gate mt-5" for="dy-key-saved">
                    <input type="checkbox" id="dy-key-saved" data-dy-key-gate>
                    <span>I have saved this key somewhere I will still have it later.</span>
                </label>
            </div>

            <div class="dy-dialog__foot">
                {{-- No close button, no Esc, no backdrop click. This is the one place in the
                     project where friction is the correct design. --}}
                <button type="button" class="dy-btn dy-btn--primary" data-dy-key-confirm disabled>Done — close this</button>
            </div>
        </dialog>
    @endif

    {{-- Reveal — one dialog per locked entry ---------------------------------
         Each needs its own <turbo-frame> id, because revealEntry() answers with a whole
         rendered page (200, never a redirect, so the plaintext never touches the session)
         and Turbo swaps in only the frame whose id matches. --}}
    @foreach ($entries as $entry)
        @continue(!$entry->isLocked())

        <dialog class="dy-dialog dy-dialog--wide" id="dy-reveal-{{ $entry->id }}"
                aria-labelledby="dy-reveal-title-{{ $entry->id }}" data-dy-reveal-dialog>
            <div class="dy-dialog__head">
                <div>
                    <h2 class="dy-dialog__title" id="dy-reveal-title-{{ $entry->id }}" dir="auto">{{ $entry->title }}</h2>
                    <p class="dy-dialog__sub">Locked memory</p>
                </div>
                <button type="button" class="dy-btn dy-btn--ghost dy-btn--sm dy-btn--icon" data-dy-close aria-label="Close">
                    <x-dy-icon name="x" />
                </button>
            </div>

            <div class="dy-dialog__body">
                <turbo-frame id="dy-reveal-frame-{{ $entry->id }}">
                    @if ($revealed === $entry->id)
                        @if ($entry->photo)
                            <img class="dy-reading__photo" src="{{ route('photo.view', $entry->photo->id) }}" alt="">
                        @endif
                        <p class="dy-reading" dir="auto">{{ $revealedContent }}</p>

                        <div class="dy-reveal__foot">
                            <p class="dy-hint">Open on screen only. Nothing was written back, and this page is never kept for Back.</p>
                            <button type="button" class="dy-btn" data-dy-reveal-hide>
                                <x-dy-icon name="eye-off" />
                                Hide it again
                            </button>
                        </div>
                    @else
                        @include('partials.diary-reveal-form', ['entry' => $entry, 'pristine' => false])
                    @endif
                </turbo-frame>

                {{-- Sits OUTSIDE the frame, so a reveal never swaps it away. "Hide it again",
                     and every other way of closing this dialog, puts this copy back — reopening
                     always starts at the key prompt, never at leftover plaintext. --}}
                <template data-dy-reveal-template>
                    @include('partials.diary-reveal-form', ['entry' => $entry, 'pristine' => true])
                </template>
            </div>
        </dialog>
    @endforeach

    @php
        $dyData = [
            'entries' => $entryData,
            'storeUrl' => route('diaryPage'),
            'revealedId' => $revealed,
            'context' => $reopen,
            // key and algorithm are stripped on the way out: a typed key must never reach the
            // client in a payload, even one it typed itself a moment ago.
            'old' => collect(session()->getOldInput())->except(['_token', '_method', 'key', 'algorithm']),
            'errors' => $errors->getMessages(),
        ];
    @endphp
    <script type="application/json" id="dy-data">@json($dyData)</script>
</div>
@endsection
