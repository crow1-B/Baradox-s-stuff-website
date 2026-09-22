@php
    /**
     * Toolbar + selection bar + grid + empty states. The Photos page and an album's page
     * render exactly this, so the two never drift apart.
     *
     * $context is 'all' or 'album' and only changes the words in the empty states — the
     * "nothing here yet" of an album is a different situation from "no photos at all".
     */
    $context = $context ?? 'all';
    $total = $photos->count();
@endphp

@if ($photos->isEmpty())
    <div class="ph-empty mt-8">
        <span class="ph-empty__icon"><i class="fa-regular fa-images" aria-hidden="true"></i></span>
        @if ($context === 'album')
            <h2 class="text-base">This album is empty</h2>
            <p class="ph-muted max-w-sm text-sm">
                Nothing has been put in “{{ $album->name }}” yet. Select photos on the Photos page and add them to it.
            </p>
            <a class="ph-btn mt-2" href="{{ route('photos') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                All photos
            </a>
        @else
            <h2 class="text-base">No photos yet</h2>
            <p class="ph-muted max-w-sm text-sm">Upload a few and they show up here. You can drag them anywhere onto this page.</p>
            <button type="button" class="ph-btn mt-2" data-ph-open-dialog="upload">
                <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                Upload photos
            </button>
        @endif
    </div>
@else
    {{-- Filtering is client-side over the tiles already on the page: a personal library is
         small, and a round trip per chip would buy nothing. State lives in ?filter= so it
         survives the back() redirect every mutation comes through. --}}
    <div class="ph-toolbar mt-6" data-ph-toolbar>
        <button type="button" class="ph-chip" data-ph-filter="all" aria-pressed="true">All</button>
        <button type="button" class="ph-chip" data-ph-filter="favorite" aria-pressed="false">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            Favourites
        </button>
        <button type="button" class="ph-chip" data-ph-filter="protected" aria-pressed="false">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Protected
        </button>

        <button type="button" class="ph-btn ph-btn--sm" data-ph-select-toggle aria-pressed="false">
            <i class="fa-regular fa-square-check" aria-hidden="true"></i>
            Select
        </button>

        <p class="ph-count" data-ph-count aria-live="polite">{{ $total }} {{ Str::plural('photo', $total) }}</p>
    </div>

    <div class="ph-selbar mt-3" data-ph-selbar hidden>
        <p class="ph-selbar__count" data-ph-selcount>0 selected</p>
        <button type="button" class="ph-btn ph-btn--sm" data-ph-bulk="add">
            <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
            Add to album
        </button>
        <button type="button" class="ph-btn ph-btn--sm" data-ph-bulk="remove">
            <i class="fa-solid fa-folder-minus" aria-hidden="true"></i>
            Remove from album
        </button>
        <button type="button" class="ph-btn ph-btn--sm ph-btn--danger" data-ph-bulk="delete">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
            Delete
        </button>
        <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm" data-ph-selclear>Cancel</button>
    </div>

    <ul class="ph-grid mt-4" role="list" data-ph-grid>
        @foreach ($photos as $index => $photo)
            @include('partials.ph-tile', ['photo' => $photo, 'index' => $index, 'favoriteAlbumId' => $favoriteAlbumId])
        @endforeach
    </ul>

    {{-- Distinct from the two states above: there ARE photos here, the chips are just too
         narrow. Rendered by the server and kept correct by JS as the chips change. --}}
    <div class="ph-empty mt-6" data-ph-no-matches hidden>
        <span class="ph-empty__icon"><i class="fa-solid fa-filter" aria-hidden="true"></i></span>
        <h2 class="text-base">Nothing matches</h2>
        <p class="ph-muted max-w-sm text-sm" data-ph-no-matches-note>No photo here matches the current filter.</p>
        <button type="button" class="ph-btn mt-2" data-ph-filter="all">Show all photos</button>
    </div>
@endif
