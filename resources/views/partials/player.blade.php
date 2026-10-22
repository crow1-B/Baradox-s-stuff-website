<div id="bs-player"
     data-turbo-permanent
     data-mode="none"
     data-user="{{ auth()->id() }}"
     data-queue-url="{{ route('music.queue') }}"
     data-play-url="{{ url('/music/__TRACK__/play') }}"
     data-can-write="{{ auth()->user()->isPreview() ? 'false' : 'true' }}">

    <audio preload="metadata" data-bsp-audio></audio>

    {{-- The dock. data-bsp-pill is its historic hook name; player.js and the pages that read
         html[data-bs-player] still use it. --}}
    <div class="bsp-dock" role="region" aria-label="Music player" data-bsp-pill hidden>
        <div class="bsp-art" aria-hidden="true">
            <i class="fa-solid fa-music bsp-art__note"></i>
            <span class="bsp-eq"><span></span><span></span><span></span></span>
        </div>

        <p class="bsp-meta">
            <span class="bsp-title" data-bsp-title dir="auto"></span>
            <span class="bsp-artist" data-bsp-artist dir="auto"></span>
        </p>

        <div class="bsp-transport">
            <button type="button" class="bsp-icon-btn bsp-skip" data-bsp-prev aria-label="Previous song">
                <i class="fa-solid fa-backward-step" aria-hidden="true"></i>
            </button>
            <button type="button" class="bsp-toggle" data-bsp-toggle aria-label="Play">
                <i class="fa-solid fa-play bsp-icon-play" aria-hidden="true"></i>
                <i class="fa-solid fa-pause bsp-icon-pause" aria-hidden="true"></i>
                <svg class="bsp-icon-loading" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-dasharray="36 60" stroke-linecap="round"/></svg>
            </button>
            <button type="button" class="bsp-icon-btn bsp-skip" data-bsp-next aria-label="Next song">
                <i class="fa-solid fa-forward-step" aria-hidden="true"></i>
            </button>
        </div>

        <div class="bsp-seek" data-bsp-seek-row>
            <span class="bsp-time" data-bsp-current aria-hidden="true">0:00</span>
            <input type="range" class="bsp-range" min="0" max="0" step="1" value="0" data-bsp-seek aria-label="Seek">
            <span class="bsp-time" data-bsp-duration aria-hidden="true">0:00</span>
        </div>
        <p class="bsp-error" data-bsp-error role="alert" hidden></p>

        <div class="bsp-volume">
            <button type="button" class="bsp-icon-btn" data-bsp-mute aria-label="Mute">
                <i class="fa-solid fa-volume-high bsp-icon-volume" aria-hidden="true"></i>
                <i class="fa-solid fa-volume-xmark bsp-icon-muted" aria-hidden="true"></i>
            </button>
            <input type="range" class="bsp-range bsp-range--volume" min="0" max="1" step="0.05" value="0.8" data-bsp-volume aria-label="Volume">
        </div>

        <button type="button" class="bsp-icon-btn" data-bsp-close aria-label="Close player" aria-haspopup="menu" aria-expanded="false" aria-controls="bsp-menu">
            <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
        </button>

        <div class="bsp-menu" id="bsp-menu" role="menu" aria-label="Close player" data-bsp-menu hidden>
            <button type="button" role="menuitem" data-bsp-hide>
                <span class="bsp-menu__label">Hide the dock</span>
                <span class="bsp-menu__hint">Keeps playing; a small handle stays in the corner</span>
            </button>
            <button type="button" role="menuitem" data-bsp-stop>
                <span class="bsp-menu__label">Stop and close</span>
                <span class="bsp-menu__hint">Stops the music until you pick a song</span>
            </button>
        </div>
    </div>

    <button type="button" class="bsp-handle" data-bsp-handle aria-label="Show player" hidden>
        <span class="bsp-eq" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>
</div>
