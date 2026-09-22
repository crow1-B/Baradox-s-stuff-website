@php
    /**
     * One card in the grid. Shared by the Videos page and an album's page.
     *
     * The single most important thing here is what is NOT rendered: there is no <video>.
     * The old page gave every card its own <video preload="metadata">, so opening it fired
     * a metadata request per video — against multi-GB files on an external SSD. A card is a
     * still image and text; a player is only ever built when the watch dialog opens.
     *
     * Nothing interactive nests inside anything else interactive: the poster's play button
     * covers the tile and is the keyboard-reachable "watch this", and the footer buttons are
     * siblings beside it.
     */
    $favorite = $video->albums->contains('id', $favoriteAlbumId);
    $gated = $video->protection === 'gated';
    $missing = $missingIds->contains($video->id);
    $length = \App\Http\Controllers\VideosController::durationLabel($video->duration);
    $chips = $video->albums->where('is_system', false);
    $watchUrl = url('/videos/watch/' . $video->slug);
@endphp
<li class="vd-card @if ($missing) vd-card--missing @endif"
    id="video-{{ $video->id }}"
    data-vd-card
    data-id="{{ $video->id }}"
    data-protection="{{ $video->protection }}"
    data-favorite="{{ $favorite ? '1' : '0' }}"
    data-missing="{{ $missing ? '1' : '0' }}">

    <div class="vd-card__tile">
        @if ($missing)
            {{-- Its own state, deliberately not an empty player: the row is fine, the file
                 it points at is not on the drive any more. Renaming a file in Windows is
                 all it takes, and "file not found" is the only useful thing to say. --}}
            <div class="vd-gone">
                <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                <p class="vd-gone__title">File not found on the drive</p>
                <p class="vd-gone__name" dir="auto">{{ $video->filename }}</p>
            </div>
        @else
            {{-- width/height match the tile's aspect-ratio box so the grid is laid out before
                 a single poster has arrived; loading="lazy" keeps a long library off the
                 first paint. The poster is a small cached JPEG, not the video. --}}
            <img class="vd-card__poster"
                 src="{{ route('video.poster', $video->id) }}"
                 alt=""
                 width="640" height="360"
                 loading="lazy"
                 decoding="async"
                 data-vd-poster>

            <span class="vd-card__blank" aria-hidden="true" data-vd-blank hidden>
                <i class="fa-regular fa-file-video"></i>
            </span>

            <button type="button" class="vd-card__play" data-vd-watch data-id="{{ $video->id }}"
                    aria-label="Watch {{ $video->title }}{{ $gated ? ' (gated)' : '' }}">
                <span class="vd-card__play-dot" aria-hidden="true">
                    <i class="fa-solid {{ $gated ? 'fa-lock' : 'fa-play' }}"></i>
                </span>
            </button>
        @endif

        @if ($length)
            <span class="vd-card__len">{{ $length }}</span>
        @endif

        @if ($gated)
            <span class="vd-badge">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Gated
            </span>
        @endif
    </div>

    <div class="vd-card__body">
        {{-- dir="auto" so an Arabic title reads right-to-left while still starting at the
             same edge as every other card's; the alignment is pinned in CSS. --}}
        <h3 class="vd-card__title" dir="auto">{{ $video->title }}</h3>

        @if ($video->description)
            <p class="vd-card__desc" dir="auto">{{ $video->description }}</p>
        @endif

        @if ($chips->isNotEmpty())
            <ul class="vd-chips" role="list">
                @foreach ($chips as $chip)
                    <li><a class="vd-chip" href="{{ route('video-albums.show', $chip->id) }}">{{ $chip->name }}</a></li>
                @endforeach
            </ul>
        @endif

        {{-- The whole URL, visible, because it gets pasted into Diary and Extra Notes and
             you should be able to see what you are about to copy. It wraps rather than
             ellipsing for the same reason. --}}
        <div class="vd-link">
            <code class="vd-link__url">{{ $watchUrl }}</code>
            <button type="button" class="vd-btn vd-btn--sm vd-btn--icon vd-link__copy"
                    data-vd-copy data-url="{{ $watchUrl }}"
                    aria-label="Copy the link to {{ $video->title }}">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="vd-card__foot">
        <button type="button" class="vd-btn vd-btn--sm vd-btn--primary" data-vd-watch data-id="{{ $video->id }}" @disabled($missing)>
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            Watch
        </button>

        <span class="vd-card__acts">
            <button type="button" class="vd-act vd-act--fav" data-vd-action="favorite" data-id="{{ $video->id }}"
                    aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                    aria-label="{{ $favorite ? 'Remove ' . $video->title . ' from favourites' : 'Add ' . $video->title . ' to favourites' }}">
                <i class="{{ $favorite ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>
            </button>

            @if ($gated)
                <button type="button" class="vd-act" data-vd-action="unlock" data-id="{{ $video->id }}" aria-label="Unlock {{ $video->title }}">
                    <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                </button>
            @else
                <button type="button" class="vd-act" data-vd-action="lock" data-id="{{ $video->id }}" aria-label="Gate {{ $video->title }}">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                </button>
            @endif

            <button type="button" class="vd-act vd-act--danger" data-vd-action="delete" data-id="{{ $video->id }}" aria-label="Remove {{ $video->title }}">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
        </span>
    </div>
</li>
