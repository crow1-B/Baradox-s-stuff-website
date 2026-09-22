@php
    /**
     * One grid tile. Shared by the Photos page and an album's page — they render the same
     * board, so the tile only ever knows about the photo and its position in the view.
     *
     * Nothing interactive is nested inside anything else interactive: .ph-tile__open covers
     * the image and is the keyboard-reachable "open this photo", while the checkbox and the
     * action buttons sit above it as siblings.
     */
    $favorite = $photo->albums->contains('id', $favoriteAlbumId);
    $locked = $photo->protection !== 'none';
    $position = $index + 1;
@endphp
<li class="ph-tile @if ($photo->protection === 'encrypted') ph-tile--encrypted @endif"
    data-ph-tile
    data-id="{{ $photo->id }}"
    data-protection="{{ $photo->protection }}"
    data-favorite="{{ $favorite ? '1' : '0' }}">

    {{-- width/height match the tile's aspect-ratio box, so the grid never reflows as
         thumbnails arrive; loading="lazy" keeps a long library off the first paint. --}}
    <img class="ph-tile__img"
         src="{{ route('photo.thumbnail', $photo->id) }}"
         alt=""
         width="300" height="300"
         loading="lazy"
         decoding="async">

    <span class="ph-tile__scrim" aria-hidden="true"></span>

    <button type="button" class="ph-tile__open" data-ph-open
            aria-label="Open photo {{ $position }}{{ $locked ? ' (protected)' : '' }}"></button>

    <input type="checkbox" class="ph-pick" data-ph-pick aria-label="Select photo {{ $position }}">

    @if ($locked)
        {{-- The pixelation and the padlock both read as "something is off with this image";
             the badge is what makes the state unambiguous. --}}
        <span class="ph-badge @if ($photo->protection === 'encrypted') ph-badge--encrypted @endif">
            <i class="fa-solid {{ $photo->protection === 'encrypted' ? 'fa-shield-halved' : 'fa-lock' }}" aria-hidden="true"></i>
            {{ $photo->protection === 'encrypted' ? 'Encrypted' : 'Gated' }}
        </span>
    @endif

    <span class="ph-tile__actions">
        <button type="button" class="ph-act ph-act--fav" data-ph-action="favorite" data-id="{{ $photo->id }}"
                aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                aria-label="{{ $favorite ? 'Remove photo ' . $position . ' from favourites' : 'Add photo ' . $position . ' to favourites' }}">
            <i class="{{ $favorite ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>
        </button>

        @if ($locked)
            <button type="button" class="ph-act" data-ph-action="unlock" data-id="{{ $photo->id }}" aria-label="Unlock photo {{ $position }}">
                <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
            </button>
        @else
            <button type="button" class="ph-act" data-ph-action="lock" data-id="{{ $photo->id }}" aria-label="Lock photo {{ $position }}">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
            </button>
        @endif

        <button type="button" class="ph-act ph-act--danger" data-ph-action="delete" data-id="{{ $photo->id }}" aria-label="Delete photo {{ $position }}">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
    </span>
</li>
