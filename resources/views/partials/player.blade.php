{{--
    Sitewide music player. data-turbo-permanent makes Turbo Drive carry this exact element
    (and the <audio> inside it) across page navigations instead of replacing it, which is
    what keeps playback going. Its markup is only ever rendered once per full page load;
    player.js owns everything inside it after that.
--}}
<div id="bs-player"
     data-turbo-permanent
     data-mode="none"
     data-user="{{ auth()->id() }}"
     data-queue-url="{{ route('music.queue') }}">

    <audio preload="metadata" data-bsp-audio></audio>

    <div class="bsp-pill" role="region" aria-label="Music player" data-bsp-pill hidden>
        <button type="button" class="bsp-toggle" data-bsp-toggle aria-label="Play">
            <svg class="bsp-icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg>
            <svg class="bsp-icon-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6.5" y="5" width="4" height="14" rx="1.2" fill="currentColor"/><rect x="13.5" y="5" width="4" height="14" rx="1.2" fill="currentColor"/></svg>
            <svg class="bsp-icon-loading" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-dasharray="36 60" stroke-linecap="round"/></svg>
        </button>

        <div class="bsp-main">
            <p class="bsp-meta">
                <span class="bsp-title" data-bsp-title></span>
                <span class="bsp-artist" data-bsp-artist></span>
            </p>
            <div class="bsp-seek" data-bsp-seek-row>
                <span class="bsp-time" data-bsp-current aria-hidden="true">0:00</span>
                <input type="range" class="bsp-range" min="0" max="0" step="1" value="0" data-bsp-seek aria-label="Seek">
                <span class="bsp-time" data-bsp-duration aria-hidden="true">0:00</span>
            </div>
            <p class="bsp-error" data-bsp-error role="alert" hidden></p>
        </div>

        <div class="bsp-volume">
            <button type="button" class="bsp-icon-btn" data-bsp-mute aria-label="Mute">
                <svg class="bsp-icon-volume" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.5L12 19V5L7.5 9.5H4Z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg class="bsp-icon-muted" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.5L12 19V5L7.5 9.5H4Z" fill="currentColor"/><path d="m16 9.5 5 5m0-5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
            <input type="range" class="bsp-range bsp-range--volume" min="0" max="1" step="0.05" value="0.8" data-bsp-volume aria-label="Volume">
        </div>

        <button type="button" class="bsp-icon-btn" data-bsp-close aria-label="Close player" aria-haspopup="menu" aria-expanded="false" aria-controls="bsp-menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>

        <div class="bsp-menu" id="bsp-menu" role="menu" aria-label="Close player" data-bsp-menu hidden>
            <button type="button" role="menuitem" data-bsp-stop>
                <span class="bsp-menu__label">Stop and close</span>
                <span class="bsp-menu__hint">Stops the music until you pick a song</span>
            </button>
            <button type="button" role="menuitem" data-bsp-hide>
                <span class="bsp-menu__label">Hide player</span>
                <span class="bsp-menu__hint">Keeps playing in the background</span>
            </button>
        </div>
    </div>

    <button type="button" class="bsp-handle" data-bsp-handle aria-label="Show player" hidden>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17.5V6.2l10-2v11.3" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><circle cx="6.5" cy="17.5" r="2.5" fill="currentColor"/><circle cx="16.5" cy="15.5" r="2.5" fill="currentColor"/></svg>
    </button>
</div>
