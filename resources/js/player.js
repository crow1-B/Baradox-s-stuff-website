const root = document.getElementById('bs-player');

if (root) {
    const $ = (selector) => root.querySelector(selector);

    const audio = $('[data-bsp-audio]');
    const pill = $('[data-bsp-pill]');
    const toggleButton = $('[data-bsp-toggle]');
    const titleEl = $('[data-bsp-title]');
    const artistEl = $('[data-bsp-artist]');
    const seekRow = $('[data-bsp-seek-row]');
    const seek = $('[data-bsp-seek]');
    const currentEl = $('[data-bsp-current]');
    const durationEl = $('[data-bsp-duration]');
    const errorEl = $('[data-bsp-error]');
    const muteButton = $('[data-bsp-mute]');
    const volume = $('[data-bsp-volume]');
    const closeButton = $('[data-bsp-close]');
    const menu = $('[data-bsp-menu]');
    const menuItems = [...menu.querySelectorAll('[role="menuitem"]')];
    const handle = $('[data-bsp-handle]');

    const STORAGE_KEY = `bs-player:${root.dataset.user}`;
    const RESTART_THRESHOLD = 3; // seconds in: "previous" restarts the track instead
    const SAVE_INTERVAL = 2000;

    // ------------------------------------------------------------- persisted state

    const readState = () => {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? {};
        } catch {
            return {};
        }
    };

    const state = {
        trackId: null,
        time: 0,
        playing: false,
        volume: 0.8,
        muted: false,
        closed: false, // "Stop and close": nothing renders until a song is picked
        hidden: false, // "Hide player": pill hidden, handle shown while playing
        ...readState(),
    };

    const save = () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch {
            // private mode / storage full: the player still works, it just won't remember
        }
    };

    // ------------------------------------------------------------------- queue

    let queue = null;
    let queueRequest = null;

    const loadQueue = (refresh = false) => {
        if (queueRequest && !refresh) return queueRequest;

        // X-Requested-With keeps this background fetch out of Laravel's "previous URL"
        // (session), which back() falls back to when a request has no Referer.
        queueRequest = fetch(root.dataset.queueUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok || !(response.headers.get('content-type') ?? '').includes('json')) {
                    throw new Error('queue unavailable');
                }
                return response.json();
            })
            .then((list) => (queue = list))
            .catch(() => {
                queueRequest = null;
                return queue ?? [];
            });

        return queueRequest;
    };

    // ------------------------------------------------------------ playback state

    let current = null;
    let loading = false;
    let errorMessage = null;
    let scrubbing = false;
    let pendingSeek = null;
    let lastSave = 0;

    const formatTime = (seconds) => {
        if (!Number.isFinite(seconds) || seconds < 0) seconds = 0;
        const s = Math.floor(seconds % 60);
        const m = Math.floor(seconds / 60) % 60;
        const h = Math.floor(seconds / 3600);
        return h ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${m}:${String(s).padStart(2, '0')}`;
    };

    const duration = () => (Number.isFinite(audio.duration) ? audio.duration : (current?.duration ?? 0));

    const paintSeek = (time) => {
        const total = duration();
        seek.max = String(Math.max(total, 0));
        seek.value = String(Math.min(time, total));
        seek.style.setProperty('--bsp-fill', total > 0 ? `${(Math.min(time, total) / total) * 100}%` : '0%');
        seek.setAttribute('aria-valuetext', `${formatTime(time)} of ${formatTime(total)}`);
        currentEl.textContent = formatTime(time);
        durationEl.textContent = formatTime(total);
    };

    const paintVolume = () => {
        volume.value = String(state.volume);
        volume.style.setProperty('--bsp-fill', `${(state.muted ? 0 : state.volume) * 100}%`);
        muteButton.setAttribute('aria-label', state.muted ? 'Unmute' : 'Mute');
        root.dataset.muted = String(state.muted);
    };

    // ------------------------------------------------------------- page channel

    const snapshot = () => ({
        trackId: current?.id ?? null,
        title: current?.title ?? null,
        artist: current?.artist ?? null,
        playing: Boolean(current) && !audio.paused,
        loading: loading && !errorMessage,
        error: errorMessage,
    });

    let announced = '';

    const announce = () => {
        const detail = snapshot();
        // render() runs on every timeupdate-adjacent state touch; only real changes ship.
        const signature = `${detail.trackId}|${detail.playing}|${detail.loading}|${detail.error}`;
        if (signature === announced) return;
        announced = signature;
        document.dispatchEvent(new CustomEvent('bs-player:change', { detail }));
    };

    window.bsPlayer = {
        state: snapshot,
        toggle: () => togglePlay(),
    };

    const render = () => {
        const playing = Boolean(current) && !audio.paused;
        const mode = !current || state.closed ? 'none' : state.hidden ? (playing ? 'handle' : 'none') : 'full';

        root.dataset.mode = mode;
        root.dataset.playing = String(playing);
        root.dataset.loading = String(loading && !errorMessage);
        pill.hidden = mode !== 'full';
        handle.hidden = mode !== 'handle';
        document.documentElement.dataset.bsPlayer = mode;

        toggleButton.setAttribute('aria-label', playing ? 'Pause' : 'Play');
        toggleButton.setAttribute('aria-busy', String(loading && !errorMessage));
        handle.setAttribute('aria-label', current ? `Show player (playing ${current.title})` : 'Show player');

        errorEl.hidden = !errorMessage;
        errorEl.textContent = errorMessage ?? '';
        errorEl.title = errorMessage ?? '';
        seekRow.hidden = Boolean(errorMessage);

        if (mode !== 'full') closeMenu();
        if (mediaSession) mediaSession.playbackState = current ? (playing ? 'playing' : 'paused') : 'none';
        announce();
    };

    const describeError = () => {
        const code = audio.error?.code;
        if (code === MediaError.MEDIA_ERR_NETWORK) return 'Connection lost while loading.';
        if (code === MediaError.MEDIA_ERR_DECODE) return 'This file is damaged.';
        return 'This file is missing or can’t be played.';
    };

    // ------------------------------------------------------------- media session

    const mediaSession = 'mediaSession' in navigator ? navigator.mediaSession : null;

    const setMediaMetadata = (track) => {
        if (!mediaSession || !('MediaMetadata' in window)) return;
        const artwork = track.cover ? [{ src: track.cover, sizes: '512x512' }] : [];
        mediaSession.metadata = new MediaMetadata({ title: track.title, artist: track.artist ?? '', artwork });
    };

    const updatePositionState = () => {
        if (!mediaSession?.setPositionState || !Number.isFinite(audio.duration)) return;
        try {
            mediaSession.setPositionState({
                duration: audio.duration,
                playbackRate: audio.playbackRate,
                position: Math.min(audio.currentTime, audio.duration),
            });
        } catch {
            // some browsers reject position updates mid-load
        }
    };

    // -------------------------------------------------------------------- actions

    const load = (track, { autoplay = false, startAt = 0 } = {}) => {
        current = track;
        errorMessage = null;
        loading = autoplay;
        pendingSeek = startAt > 0 ? startAt : null;

        state.trackId = track.id;
        state.time = startAt;
        save();

        titleEl.textContent = track.title;
        titleEl.title = track.title;
        artistEl.textContent = track.artist ?? '';
        artistEl.title = track.artist ?? '';

        audio.src = track.src;
        paintSeek(startAt);
        setMediaMetadata(track);
        render();

        if (autoplay) play();
    };

    const play = async () => {
        if (!current) return;
        if (audio.readyState < HTMLMediaElement.HAVE_FUTURE_DATA) {
            loading = true;
            render();
        }
        try {
            await audio.play();
        } catch (error) {
            loading = false;
            state.playing = false;
            save();
            render();
        }
    };

    const togglePlay = () => (audio.paused ? play() : audio.pause());

    const indexOfCurrent = (list) => list.findIndex((track) => track.id === current?.id);

    const next = async ({ automatic = false } = {}) => {
        if (!current) return;
        const list = await loadQueue();
        const index = indexOfCurrent(list);

        if (index !== -1 && index + 1 < list.length) {
            load(list[index + 1], { autoplay: true });
        } else if (automatic) {
            // End of the list: stop rather than loop.
            state.playing = false;
            state.time = 0;
            save();
            render();
        }
    };

    const previous = async () => {
        if (!current) return;
        const list = await loadQueue();
        const index = indexOfCurrent(list);

        if (audio.currentTime > RESTART_THRESHOLD || index <= 0) {
            audio.currentTime = 0;
            return;
        }
        load(list[index - 1], { autoplay: true });
    };

    const stopAndClose = () => {
        audio.pause();
        audio.removeAttribute('src');
        audio.load();

        current = null;
        errorMessage = null;
        loading = false;
        Object.assign(state, { trackId: null, time: 0, playing: false, closed: true, hidden: false });
        save();

        if (mediaSession) mediaSession.metadata = null;
        render();
    };

    const hidePlayer = () => {
        state.hidden = true;
        save();
        render();
        if (!handle.hidden) handle.focus();
    };

    // Called by every [data-player-play] button on any page.
    const pick = (track) => {
        state.closed = false;
        state.hidden = false;
        load(track, { autoplay: true });
        if (!queue?.some((item) => item.id === track.id)) loadQueue(true);
    };

    // ------------------------------------------------------------------------ menu

    const openMenu = () => {
        menu.hidden = false;
        closeButton.setAttribute('aria-expanded', 'true');
        menuItems[0].focus();
    };

    function closeMenu({ returnFocus = false } = {}) {
        if (menu.hidden) return;
        menu.hidden = true;
        closeButton.setAttribute('aria-expanded', 'false');
        if (returnFocus) closeButton.focus();
    }

    closeButton.addEventListener('click', () => (menu.hidden ? openMenu() : closeMenu()));

    menu.addEventListener('keydown', (event) => {
        const index = menuItems.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            menuItems[(index + step + menuItems.length) % menuItems.length].focus();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu({ returnFocus: true });
        } else if (event.key === 'Tab') {
            closeMenu();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!menu.hidden && !menu.contains(event.target) && !closeButton.contains(event.target)) closeMenu();
    });

    $('[data-bsp-stop]').addEventListener('click', stopAndClose);
    $('[data-bsp-hide]').addEventListener('click', hidePlayer);

    // -------------------------------------------------------------------- controls

    toggleButton.addEventListener('click', togglePlay);

    handle.addEventListener('click', () => {
        state.hidden = false;
        save();
        render();
        toggleButton.focus();
    });

    seek.addEventListener('input', () => {
        scrubbing = true;
        paintSeek(Number(seek.value));
    });

    seek.addEventListener('change', () => {
        scrubbing = false;
        if (current && Number.isFinite(audio.duration)) audio.currentTime = Number(seek.value);
    });

    volume.addEventListener('input', () => {
        audio.volume = Number(volume.value);
        audio.muted = audio.volume === 0;
    });

    muteButton.addEventListener('click', () => {
        audio.muted = !audio.muted;
        if (!audio.muted && audio.volume === 0) audio.volume = 0.5;
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-player-play]');
        if (!button) return;
        event.preventDefault();
        try {
            pick(JSON.parse(button.dataset.playerPlay));
        } catch {
            // malformed payload: nothing sensible to play
        }
    });

    // ---------------------------------------------------------------- audio events

    audio.addEventListener('play', () => {
        state.playing = true;
        save();
        render();
    });

    audio.addEventListener('playing', () => {
        loading = false;
        render();
    });

    audio.addEventListener('waiting', () => {
        loading = true;
        render();
    });

    audio.addEventListener('canplay', () => {
        if (audio.paused) {
            loading = false;
            render();
        }
    });

    audio.addEventListener('pause', () => {
        if (current) {
            state.time = audio.currentTime;
            state.playing = false;
            save();
        }
        render();
    });

    audio.addEventListener('ended', () => next({ automatic: true }));

    audio.addEventListener('loadedmetadata', () => {
        if (pendingSeek !== null) {
            audio.currentTime = Math.min(pendingSeek, audio.duration || pendingSeek);
            pendingSeek = null;
        }
        paintSeek(audio.currentTime);
        updatePositionState();
    });

    audio.addEventListener('durationchange', () => paintSeek(audio.currentTime));

    audio.addEventListener('timeupdate', () => {
        if (!scrubbing) paintSeek(audio.currentTime);
        if (current && Date.now() - lastSave > SAVE_INTERVAL) {
            lastSave = Date.now();
            state.time = audio.currentTime;
            save();
            updatePositionState();
        }
    });

    audio.addEventListener('error', () => {
        if (!current || !audio.getAttribute('src')) return;
        loading = false;
        errorMessage = describeError();
        state.playing = false;
        save();
        render();
    });

    audio.addEventListener('volumechange', () => {
        state.volume = audio.volume;
        state.muted = audio.muted;
        save();
        paintVolume();
    });

    window.addEventListener('pagehide', () => {
        if (current) {
            state.time = audio.currentTime;
            save();
        }
    });

    // ---------------------------------------------------------------- media keys

    if (mediaSession) {
        const handlers = {
            play: () => play(),
            pause: () => audio.pause(),
            previoustrack: () => previous(),
            nexttrack: () => next(),
            seekto: (details) => {
                if (Number.isFinite(details.seekTime)) audio.currentTime = details.seekTime;
            },
        };
        for (const [action, handler] of Object.entries(handlers)) {
            try {
                mediaSession.setActionHandler(action, handler);
            } catch {
                // action not supported by this browser
            }
        }
    }

    // ---------------------------------------------------------- Turbo navigation

    let resumeAfterRender = false;

    document.addEventListener('turbo:before-render', () => {
        resumeAfterRender = Boolean(current) && !audio.paused;
    });

    document.addEventListener('turbo:render', () => {
        document.documentElement.dataset.bsPlayer = root.dataset.mode;
        if (resumeAfterRender && audio.paused && root.isConnected) {
            root.dataset.resumed = String(Number(root.dataset.resumed ?? 0) + 1);
            audio.play().catch(() => {});
        }
        resumeAfterRender = false;
    });

    // ------------------------------------------------------------------------ boot

    audio.volume = state.volume;
    audio.muted = state.muted;
    paintVolume();
    render();

    if (!state.closed) {
        loadQueue().then((list) => {
            if (current) return; // a song was picked while the queue was loading
            const stored = list.find((track) => track.id === state.trackId);
            const track = stored ?? list[0];
            if (!track) return;
            load(track, { autoplay: Boolean(stored) && state.playing, startAt: stored ? state.time : 0 });
        });
    }
}
