@php
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
            <div class="vd-gone">
                <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                <p class="vd-gone__title">File not found on the drive</p>
                <p class="vd-gone__name" dir="auto">{{ $video->filename }}</p>
            </div>
        @else
            {{-- The poster grows into the player when an unprotected video is opened. --}}
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
        <h3 class="vd-card__title" dir="auto">{{ $video->title }}</h3>

        @if ($video->description)
            <p class="vd-card__desc" dir="auto">{{ $video->description }}</p>
        @endif

        @if ($chips->isNotEmpty())
            <ul class="vd-chips" role="list">
                @foreach ($chips as $chip)
                    <li><a class="nd-chip" href="{{ route('video-albums.show', $chip->id) }}"><i class="fa-regular fa-folder" aria-hidden="true"></i>{{ $chip->name }}</a></li>
                @endforeach
            </ul>
        @endif

        <div class="vd-link">
            <code class="vd-link__url">{{ $watchUrl }}</code>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm vd-link__copy"
                    data-vd-copy data-url="{{ $watchUrl }}"
                    aria-label="Copy the link to {{ $video->title }}">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="vd-card__foot">
        <button type="button" class="nd-btn nd-btn--sm" data-vd-watch data-id="{{ $video->id }}" @disabled($missing)>
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            Watch
        </button>

        @if ($canWrite)
            <span class="vd-card__acts">
                <button type="button" class="nd-icon-btn nd-icon-btn--sm vd-fav" data-vd-action="favorite" data-id="{{ $video->id }}"
                        aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                        aria-label="{{ $favorite ? 'Remove ' . $video->title . ' from favourites' : 'Add ' . $video->title . ' to favourites' }}">
                    <i class="{{ $favorite ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>
                </button>

                @if ($gated)
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-action="unlock" data-id="{{ $video->id }}" aria-label="Unlock {{ $video->title }}">
                        <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                    </button>
                @else
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-action="lock" data-id="{{ $video->id }}" aria-label="Gate {{ $video->title }}">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    </button>
                @endif

                <button type="button" class="nd-icon-btn nd-icon-btn--sm nd-icon-btn--danger" data-vd-action="delete" data-id="{{ $video->id }}" aria-label="Remove {{ $video->title }}">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                </button>
            </span>
        @elseif ($favorite)
            <span class="vd-fav-mark"><i class="fa-solid fa-star" aria-hidden="true"></i><span class="nd-sr-only">Favourite</span></span>
        @endif
    </div>
</li>
