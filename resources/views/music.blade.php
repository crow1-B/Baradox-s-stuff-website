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

<div class="nd-page mu @unless ($canWrite) mu--readonly @endunless" id="mu-root">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Music</h1>
            <p class="nd-page__sub">
                @if ($tracks->isEmpty())
                    Nothing on the shelf yet.
                @else
                    {{ $tracks->count() }} {{ Str::plural('song', $tracks->count()) }}, playing from the dock on every page.
                @endif
            </p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                <button type="button" class="nd-btn nd-btn--primary" data-mu-open="add">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add song
                </button>
            </div>
        @endif
    </header>

    <div class="nd-page__banners">
        @if (session('success'))
            <div class="nd-banner nd-banner--success" role="status" data-mu-dismissable>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <p class="nd-banner__text">{{ session('success') }}</p>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-banner__close" data-mu-dismiss aria-label="Dismiss">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="nd-banner nd-banner--error" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="nd-banner__text">
                    <p>That didn't save.</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    @if ($tracks->isEmpty())
        <div class="nd-empty nd-empty--block">
            <p class="nd-empty__line">A quiet room.</p>
            @if ($canWrite)
                <p>Add an audio file and it shows up here, playable from the dock at the bottom of every page.</p>
                <button type="button" class="nd-btn nd-btn--primary" data-mu-open="add">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add your first song
                </button>
            @endif
        </div>
    @else
        {{-- Filtering and sorting are client-side over the rows already on the page: the
             library is small, and a round trip per keystroke would buy nothing. --}}
        <div class="nd-toolbar mu-toolbar" data-mu-toolbar>
            <div class="nd-search">
                <i class="fa-solid fa-magnifying-glass nd-search__icon" aria-hidden="true"></i>
                <input type="search" class="nd-input" id="mu-search" placeholder="Search title or artist"
                       aria-label="Search title or artist" autocomplete="off" dir="auto" data-mu-search data-page-search>
            </div>

            <button type="button" class="nd-btn mu-favs" data-mu-fav-filter aria-pressed="false">
                <i class="fa-regular fa-star" aria-hidden="true"></i>
                Favourites
            </button>

            <button type="button" class="nd-btn nd-btn--ghost nd-btn--sm" data-mu-clear hidden>Clear</button>

            <p class="nd-count" data-mu-count aria-live="polite">{{ $tracks->count() }} {{ Str::plural('song', $tracks->count()) }}</p>
        </div>

        {{-- The label-only cells still have to occupy their grid tracks, so the visually-hidden
             text sits inside a real cell rather than being the cell. --}}
        <div class="mu-head mu-grid" data-mu-head>
            <span><span class="nd-sr-only">Cover</span></span>
            <span class="mu-head__group">
                <button type="button" class="mu-sort" data-mu-sort="title">Title<i class="fa-solid fa-chevron-down mu-sort__caret" aria-hidden="true"></i></button>
                <span class="mu-head__sep" aria-hidden="true"></span>
                <button type="button" class="mu-sort" data-mu-sort="artist">Artist<i class="fa-solid fa-chevron-down mu-sort__caret" aria-hidden="true"></i></button>
            </span>
            <span class="mu-col--release mu-head__label">Released</span>
            <span>
                <button type="button" class="mu-sort mu-sort--end" data-mu-sort="duration">Length<i class="fa-solid fa-chevron-down mu-sort__caret" aria-hidden="true"></i></button>
            </span>
            <span class="mu-col--added">
                <button type="button" class="mu-sort" data-mu-sort="added">Added<i class="fa-solid fa-chevron-down mu-sort__caret" aria-hidden="true"></i></button>
            </span>
            <span><span class="nd-sr-only">Favourite</span></span>
            @if ($canWrite)
                <span><span class="nd-sr-only">Actions</span></span>
            @endif
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
                            <span class="mu-cover__art mu-cover__art--blank" aria-hidden="true"><i class="fa-solid fa-music"></i></span>
                        @endif
                        <button type="button" class="mu-cover__btn" data-mu-toggle aria-label="Play {{ $track->title }}">
                            <i class="fa-solid fa-play mu-cover__play" aria-hidden="true"></i>
                            <i class="fa-solid fa-pause mu-cover__pause" aria-hidden="true"></i>
                            <span class="mu-eq" aria-hidden="true"><i></i><i></i><i></i></span>
                        </button>
                    </span>

                    <span class="mu-main">
                        <span class="mu-title" dir="auto">{{ $track->title }}</span>
                        <span class="mu-artist" dir="auto">{{ $track->artist_name ?: '—' }}</span>
                    </span>

                    <span class="mu-col--release mu-meta">
                        <span class="nd-sr-only">Released </span>{{ $track->song_date ?: '—' }}
                    </span>

                    <span class="mu-meta mu-meta--end">
                        <span class="nd-sr-only">Length </span>{{ $track->durationForHumans() ?: '—' }}
                    </span>

                    <span class="mu-col--added mu-meta">
                        <span class="nd-sr-only">Added </span>{{ $track->created_at->format('Y-m-d') }}
                    </span>

                    @if ($canWrite)
                        <form method="POST" action="/music/{{ $track->id }}/favorite" class="mu-cell" data-mu-control>
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="nd-icon-btn nd-icon-btn--sm mu-fav"
                                    aria-pressed="{{ $track->favorite ? 'true' : 'false' }}"
                                    aria-label="{{ $track->favorite ? 'Remove ' . $track->title . ' from favourites' : 'Add ' . $track->title . ' to favourites' }}">
                                <i class="fa-regular fa-star" aria-hidden="true"></i>
                            </button>
                        </form>

                        <span class="mu-actions" data-mu-control>
                            <button type="button" class="nd-icon-btn nd-icon-btn--sm"
                                    data-mu-open="edit" data-track="{{ $track->id }}" aria-label="Edit {{ $track->title }}">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-icon-btn--danger"
                                    data-mu-open="delete" data-track="{{ $track->id }}" aria-label="Delete {{ $track->title }}">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </span>
                    @else
                        <span class="mu-cell">
                            @if ($track->favorite)
                                <i class="fa-solid fa-star mu-fav-mark" aria-hidden="true"></i><span class="nd-sr-only">Favourite</span>
                            @endif
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- Distinct from "no songs yet": the library isn't empty, the filters are too narrow. --}}
        <div class="nd-empty nd-empty--block" data-mu-no-matches hidden>
            <p class="nd-empty__line">Nothing matches.</p>
            <p data-mu-no-matches-note>No song here matches what you're looking for.</p>
            <button type="button" class="nd-btn" data-mu-clear>Clear filters</button>
        </div>
    @endif

    @if ($canWrite)
        {{-- Add ---------------------------------------------------------------- --}}
        <dialog class="nd-dialog" id="mu-add-dialog" aria-labelledby="mu-add-dialog-title">
            <form action="{{ route('musicPage') }}" method="POST" enctype="multipart/form-data" novalidate data-mu-add-form>
                @csrf
                <input type="hidden" name="_modal" value="add">

                <div class="nd-dialog__head">
                    <h2 class="nd-dialog__title" id="mu-add-dialog-title">Add song</h2>
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-mu-close aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="nd-dialog__body">
                    <div class="nd-fields">
                        <x-nd-field form="af" name="title" label="Title" dir="auto" full required />
                        <x-nd-field form="af" name="artist_name" label="Artist" dir="auto" full required />
                        <x-nd-field form="af" name="duration" label="Length" placeholder="4:07" hint="Minutes and seconds, as M:SS." required />
                        <x-nd-field form="af" name="song_date" label="Release date" type="date" />
                        <x-nd-field form="af" name="audio_file" label="Audio file" type="file" accept="audio/*" full required
                            hint="mp3, wav or ogg, up to 50 MB. Pick the file again if the form comes back with an error." />

                        <label class="nd-check nd-span-2" for="af-favorite">
                            <input type="checkbox" name="favorite" id="af-favorite" value="1">
                            <span>Mark as a favourite</span>
                        </label>
                    </div>
                </div>

                <div class="nd-dialog__foot">
                    <button type="button" class="nd-btn nd-btn--ghost" data-mu-close>Cancel</button>
                    <button type="submit" class="nd-btn nd-btn--primary">Add song</button>
                </div>
            </form>
        </dialog>

        {{-- Edit --------------------------------------------------------------- --}}
        <dialog class="nd-dialog" id="mu-edit-dialog" aria-labelledby="mu-edit-dialog-title">
            <form method="POST" action="" novalidate data-mu-edit-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="_modal" value="">

                <div class="nd-dialog__head">
                    <h2 class="nd-dialog__title" id="mu-edit-dialog-title">Edit song</h2>
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-mu-close aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="nd-dialog__body">
                    <div class="nd-fields">
                        <x-nd-field form="ef" name="title" label="Title" dir="auto" full required />
                        <x-nd-field form="ef" name="artist_name" label="Artist" dir="auto" full />
                        {{-- Length is hand-typed and unrecoverable, so the form always carries it:
                             a save that left it out used to overwrite the stored value with null. --}}
                        <x-nd-field form="ef" name="duration" label="Length" placeholder="4:07" hint="Minutes and seconds, as M:SS." />
                        <x-nd-field form="ef" name="song_date" label="Release date" type="date" />
                    </div>
                </div>

                <div class="nd-dialog__foot">
                    <button type="button" class="nd-btn nd-btn--ghost" data-mu-close>Cancel</button>
                    <button type="submit" class="nd-btn nd-btn--primary">Save changes</button>
                </div>
            </form>
        </dialog>

        {{-- Delete ------------------------------------------------------------- --}}
        <dialog class="nd-dialog nd-dialog--sm" id="mu-delete-dialog" aria-labelledby="mu-delete-dialog-title" aria-describedby="mu-delete-note">
            <form method="POST" action="" data-mu-delete-form>
                @csrf
                @method('DELETE')
                <div class="nd-dialog__head">
                    <h2 class="nd-dialog__title" id="mu-delete-dialog-title">Delete this song?</h2>
                </div>
                <div class="nd-dialog__body">
                    <p class="nd-hint mu-delete-note" id="mu-delete-note">
                        “<span data-mu-delete-title dir="auto"></span>” and its audio file are removed for good. There is no undo.
                    </p>
                </div>
                <div class="nd-dialog__foot">
                    <button type="button" class="nd-btn nd-btn--ghost" data-mu-close autofocus>Cancel</button>
                    <button type="submit" class="nd-btn nd-btn--danger">Delete</button>
                </div>
            </form>
        </dialog>
    @endif

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
