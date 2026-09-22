@extends('layouts.app')

@push('head')
    @vite(['resources/css/music.css', 'resources/js/music.js'])
@endpush

@section('content')
@php
    // The player's queue endpoint serves this same order, so "next" means "the row below"
    // — client-side sorting reorders what you see, not what plays next.
    $trackData = $tracks->mapWithKeys(fn ($track) => [$track->id => [
        'id' => $track->id,
        'title' => $track->title,
        'artist_name' => $track->artist_name,
        'duration' => $track->durationForHumans(),
        'song_date' => $track->song_date,
        'urls' => [
            'update' => url('/music/' . $track->id),
            'destroy' => url('/music/' . $track->id),
        ],
    ]]);
@endphp

<div class="mu" id="mu-root">
    <div class="mx-auto w-full max-w-5xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <h1>Music</h1>
                <p class="mu-muted">Everything you've put here, in one list.</p>
            </div>
            <button type="button" class="mu-btn mu-btn--primary self-start sm:self-auto" data-mu-open="add">
                <x-mu-icon name="plus" />
                Add song
            </button>
        </header>

        @if (session('success'))
            <div class="mu-banner mu-banner--success mt-6" role="status" data-mu-dismissable>
                <x-mu-icon name="check" />
                <p class="flex-1">{{ session('success') }}</p>
                <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon -my-1" data-mu-dismiss aria-label="Dismiss">
                    <x-mu-icon name="x" />
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="mu-banner mu-banner--error mt-6" role="alert">
                <x-mu-icon name="alert" />
                <div>
                    <p class="font-medium">That didn't save.</p>
                    <ul class="mt-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($tracks->isEmpty())
            <div class="mu-empty mt-8">
                <span class="mu-empty__icon"><x-mu-icon name="note" /></span>
                <h2 class="text-base">No songs yet</h2>
                <p class="mu-muted max-w-sm text-sm">Add an audio file and it shows up here, playable from the player at the bottom of every page.</p>
                <button type="button" class="mu-btn mt-2" data-mu-open="add">
                    <x-mu-icon name="plus" />
                    Add your first song
                </button>
            </div>
        @else
            {{-- Filtering and sorting are client-side over the rows already on the page: the
                 library is small, and a round trip per keystroke would buy nothing. --}}
            <div class="mu-toolbar mt-7" data-mu-toolbar>
                <div class="mu-search">
                    <x-mu-icon name="search" class="mu-search__icon" />
                    <input type="search"
                           class="mu-input mu-search__input"
                           id="mu-search"
                           placeholder="Search title or artist"
                           aria-label="Search title or artist"
                           autocomplete="off"
                           dir="auto"
                           data-mu-search>
                </div>

                <button type="button" class="mu-btn mu-toggle" data-mu-fav-filter aria-pressed="false">
                    <x-mu-icon name="star" class="mu-toggle__star" />
                    Favourites
                </button>

                <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm" data-mu-clear hidden>Clear</button>

                <p class="mu-count" data-mu-count aria-live="polite">{{ $tracks->count() }} {{ Str::plural('song', $tracks->count()) }}</p>
            </div>

            {{-- The three label-only cells still have to occupy their grid tracks, so the
                 visually-hidden text sits inside a real cell rather than being the cell. --}}
            <div class="mu-head mu-grid" data-mu-head>
                <span><span class="mu-sr">Cover</span></span>
                <span class="mu-head__group">
                    <button type="button" class="mu-sort" data-mu-sort="title">Title<x-mu-icon name="caret" class="mu-sort__caret" /></button>
                    <span class="mu-head__sep" aria-hidden="true"></span>
                    <button type="button" class="mu-sort" data-mu-sort="artist">Artist<x-mu-icon name="caret" class="mu-sort__caret" /></button>
                </span>
                <span class="mu-col--release mu-head__label">Released</span>
                <span class="mu-col--duration">
                    <button type="button" class="mu-sort mu-sort--end" data-mu-sort="duration">Length<x-mu-icon name="caret" class="mu-sort__caret" /></button>
                </span>
                <span class="mu-col--added">
                    <button type="button" class="mu-sort" data-mu-sort="added">Added<x-mu-icon name="caret" class="mu-sort__caret" /></button>
                </span>
                <span><span class="mu-sr">Favourite</span></span>
                <span><span class="mu-sr">Actions</span></span>
            </div>

            <ul class="mu-list" role="list" data-mu-list>
                @foreach ($tracks as $track)
                    @php $payload = $track->playerPayload(); @endphp
                    {{-- The row is the play target (delegated in player.js). The cover-slot
                         button is the keyboard-reachable equivalent — the row itself is not
                         focusable, so no interactive element ends up nested in another. --}}
                    <li class="mu-row mu-grid"
                        data-mu-row
                        data-track-id="{{ $track->id }}"
                        data-title="{{ $track->title }}"
                        data-artist="{{ $track->artist_name }}"
                        data-duration="{{ $track->duration ?? '' }}"
                        data-added="{{ $track->created_at->getTimestamp() }}"
                        data-favorite="{{ $track->favorite ? '1' : '0' }}"
                        data-player-play="{{ json_encode($payload) }}">

                        <span class="mu-cover">
                            @if (!empty($payload['cover']))
                                <img class="mu-cover__art" src="{{ $payload['cover'] }}" alt="" width="40" height="40" loading="lazy">
                            @else
                                {{-- Drop-in point for cover art (§8): once playerPayload() carries a
                                     `cover` URL the <img> above takes this slot, same 40px box. --}}
                                <span class="mu-cover__art mu-cover__art--blank" aria-hidden="true"><x-mu-icon name="note" /></span>
                            @endif
                            <button type="button" class="mu-cover__btn" data-mu-toggle aria-label="Play {{ $track->title }}">
                                <x-mu-icon name="play" class="mu-cover__play" />
                                <x-mu-icon name="pause" class="mu-cover__pause" />
                                <span class="mu-eq" aria-hidden="true"><i></i><i></i><i></i></span>
                            </button>
                        </span>

                        <span class="mu-main">
                            <span class="mu-title" dir="auto">{{ $track->title }}</span>
                            <span class="mu-artist" dir="auto">{{ $track->artist_name ?: '—' }}</span>
                        </span>

                        <span class="mu-col--release mu-meta">
                            <span class="mu-sr">Released </span>{{ $track->song_date ?: '—' }}
                        </span>

                        <span class="mu-col--duration mu-meta mu-meta--end">
                            <span class="mu-sr">Length </span>{{ $track->durationForHumans() ?: '—' }}
                        </span>

                        <span class="mu-col--added mu-meta">
                            <span class="mu-sr">Added </span>{{ $track->created_at->format('Y-m-d') }}
                        </span>

                        <form method="POST" action="/music/{{ $track->id }}/favorite" class="mu-cell" data-mu-control>
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon mu-fav"
                                    aria-pressed="{{ $track->favorite ? 'true' : 'false' }}"
                                    aria-label="{{ $track->favorite ? 'Remove ' . $track->title . ' from favourites' : 'Add ' . $track->title . ' to favourites' }}">
                                <x-mu-icon name="star" />
                            </button>
                        </form>

                        <span class="mu-actions" data-mu-control>
                            <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon"
                                    data-mu-open="edit" data-track="{{ $track->id }}" aria-label="Edit {{ $track->title }}">
                                <x-mu-icon name="pencil" />
                            </button>
                            <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon mu-danger"
                                    data-mu-open="delete" data-track="{{ $track->id }}" aria-label="Delete {{ $track->title }}">
                                <x-mu-icon name="trash" />
                            </button>
                        </span>
                    </li>
                @endforeach
            </ul>

            {{-- Distinct from "no songs yet": the library isn't empty, the filters are too narrow. --}}
            <div class="mu-empty mt-6" data-mu-no-matches hidden>
                <span class="mu-empty__icon"><x-mu-icon name="search" /></span>
                <h2 class="text-base">Nothing matches</h2>
                <p class="mu-muted max-w-sm text-sm" data-mu-no-matches-note>No song here matches what you're looking for.</p>
                <button type="button" class="mu-btn mt-2" data-mu-clear>Clear filters</button>
            </div>
        @endif
    </div>

    {{-- Add ---------------------------------------------------------------- --}}
    <dialog class="mu-dialog" id="mu-add-dialog" aria-labelledby="mu-add-dialog-title">
        <form action="{{ route('musicPage') }}" method="POST" enctype="multipart/form-data" novalidate data-mu-add-form>
            @csrf
            <input type="hidden" name="_modal" value="add">

            <div class="mu-dialog__head">
                <h2 class="mu-dialog__title" id="mu-add-dialog-title">Add song</h2>
                <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon" data-mu-close aria-label="Close">
                    <x-mu-icon name="x" />
                </button>
            </div>

            <div class="mu-dialog__body">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-mu-field form="af" name="title" label="Title" dir="auto" full required />
                    <x-mu-field form="af" name="artist_name" label="Artist" dir="auto" full required />
                    <x-mu-field form="af" name="duration" label="Length" placeholder="4:07" hint="Minutes and seconds, as M:SS." required />
                    <x-mu-field form="af" name="song_date" label="Release date" type="date" />
                    <x-mu-field form="af" name="audio_file" label="Audio file" type="file" accept="audio/*" full required
                        hint="mp3, wav or ogg, up to 50 MB. Pick the file again if the form comes back with an error." />

                    <label class="mu-check sm:col-span-2" for="af-favorite">
                        <input type="checkbox" name="favorite" id="af-favorite" value="1">
                        <span>Mark as a favourite</span>
                    </label>
                </div>
            </div>

            <div class="mu-dialog__foot">
                <button type="button" class="mu-btn" data-mu-close>Cancel</button>
                <button type="submit" class="mu-btn mu-btn--primary">Add song</button>
            </div>
        </form>
    </dialog>

    {{-- Edit --------------------------------------------------------------- --}}
    <dialog class="mu-dialog" id="mu-edit-dialog" aria-labelledby="mu-edit-dialog-title">
        <form method="POST" action="" novalidate data-mu-edit-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="">

            <div class="mu-dialog__head">
                <h2 class="mu-dialog__title" id="mu-edit-dialog-title">Edit song</h2>
                <button type="button" class="mu-btn mu-btn--ghost mu-btn--sm mu-btn--icon" data-mu-close aria-label="Close">
                    <x-mu-icon name="x" />
                </button>
            </div>

            <div class="mu-dialog__body">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-mu-field form="ef" name="title" label="Title" dir="auto" full required />
                    <x-mu-field form="ef" name="artist_name" label="Artist" dir="auto" full />
                    {{-- Length is hand-typed and unrecoverable, so the form always carries it:
                         a save that left it out used to overwrite the stored value with null. --}}
                    <x-mu-field form="ef" name="duration" label="Length" placeholder="4:07" hint="Minutes and seconds, as M:SS." />
                    <x-mu-field form="ef" name="song_date" label="Release date" type="date" />
                </div>
            </div>

            <div class="mu-dialog__foot">
                <button type="button" class="mu-btn" data-mu-close>Cancel</button>
                <button type="submit" class="mu-btn mu-btn--primary">Save changes</button>
            </div>
        </form>
    </dialog>

    {{-- Delete ------------------------------------------------------------- --}}
    <dialog class="mu-dialog mu-dialog--sm" id="mu-delete-dialog" aria-labelledby="mu-delete-dialog-title" aria-describedby="mu-delete-note">
        <form method="POST" action="" data-mu-delete-form>
            @csrf
            @method('DELETE')
            <div class="mu-dialog__head">
                <h2 class="mu-dialog__title" id="mu-delete-dialog-title">Delete this song?</h2>
            </div>
            <div class="mu-dialog__body">
                <p class="mu-muted text-sm" id="mu-delete-note">
                    “<span data-mu-delete-title dir="auto"></span>” and its audio file are removed for good. There is no undo.
                </p>
            </div>
            <div class="mu-dialog__foot">
                <button type="button" class="mu-btn" data-mu-close autofocus>Cancel</button>
                <button type="submit" class="mu-btn mu-btn--danger">Delete</button>
            </div>
        </form>
    </dialog>

    @php
        $muData = [
            'tracks' => $trackData,
            'context' => old('_modal'),
            'old' => collect(session()->getOldInput())->except(['_token', '_method']),
            'errors' => $errors->getMessages(),
        ];
    @endphp
    <script type="application/json" id="mu-data">@json($muData)</script>
</div>
@endsection
