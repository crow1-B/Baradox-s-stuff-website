@php
    /**
     * Toolbar + selection bar + grid + empty states. The Photos page and an album's page
     * render exactly this, so the two never drift apart.
     *
     * $context is 'all' or 'album' and only changes the words in the empty states — the
     * "nothing here yet" of an album is a different situation from "no photos at all".
     * $morphId is the photo an album card's cover morphs into (album pages only).
     */
    $context = $context ?? 'all';
    $morphId = $morphId ?? null;
    $total = $photos->count();
@endphp

@if ($photos->isEmpty())
    <div class="nd-empty nd-empty--block ph-empty">
        @if ($context === 'album')
            <p class="nd-empty__line">This album is empty.</p>
            <p>Nothing has been put in “{{ $album->name }}” yet.@if ($canWrite) Select photos on the Photos page and add them to it.@endif</p>
            <a class="nd-btn" href="{{ route('photos') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                All photos
            </a>
        @else
            <p class="nd-empty__line">No photographs yet.</p>
            @if ($canWrite)
                <p>Upload a few and they show up here. You can drag them anywhere onto this page.</p>
                <button type="button" class="nd-btn nd-btn--primary" data-ph-open-dialog="upload">
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload photos
                </button>
            @endif
        @endif
    </div>
@else
    {{-- Filtering is client-side over the tiles already on the page: a personal library is
         small, and a round trip per chip would buy nothing. State lives in ?filter= so it
         survives the back() redirect every mutation comes through. --}}
    <div class="nd-toolbar ph-toolbar" data-ph-toolbar>
        <button type="button" class="nd-chip" data-ph-filter="all" aria-pressed="true">All</button>
        <button type="button" class="nd-chip" data-ph-filter="favorite" aria-pressed="false">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            Favourites
        </button>
        <button type="button" class="nd-chip" data-ph-filter="protected" aria-pressed="false">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Protected
        </button>

        @if ($canWrite)
            <button type="button" class="nd-btn nd-btn--sm nd-btn--ghost" data-ph-select-toggle aria-pressed="false">
                <i class="fa-regular fa-square-check" aria-hidden="true"></i>
                Select
            </button>
        @endif

        <p class="nd-count" data-ph-count aria-live="polite">{{ $total }} {{ Str::plural('photo', $total) }}</p>
    </div>

    @if ($canWrite)
        <div class="ph-selbar" data-ph-selbar hidden>
            <p class="ph-selbar__count" data-ph-selcount>0 selected</p>
            <button type="button" class="nd-btn nd-btn--sm" data-ph-bulk="add">
                <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                Add to album
            </button>
            <button type="button" class="nd-btn nd-btn--sm" data-ph-bulk="remove">
                <i class="fa-solid fa-folder-minus" aria-hidden="true"></i>
                Remove from album
            </button>
            <button type="button" class="nd-btn nd-btn--sm nd-btn--danger" data-ph-bulk="delete">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                Delete
            </button>
            <button type="button" class="nd-btn nd-btn--sm nd-btn--ghost" data-ph-selclear>Cancel</button>
        </div>
    @endif

    <ul class="ph-grid" role="list" data-ph-grid>
        @foreach ($photos as $index => $photo)
            @include('partials.ph-tile', ['photo' => $photo, 'index' => $index, 'favoriteAlbumId' => $favoriteAlbumId, 'morphId' => $morphId])
        @endforeach
    </ul>

    {{-- Distinct from the two states above: there ARE photos here, the chips are just too
         narrow. Rendered by the server and kept correct by JS as the chips change. --}}
    <div class="nd-empty nd-empty--block" data-ph-no-matches hidden>
        <p class="nd-empty__line">Nothing matches.</p>
        <p data-ph-no-matches-note>No photo here matches the current filter.</p>
        <button type="button" class="nd-btn" data-ph-filter="all">Show all photos</button>
    </div>
@endif
