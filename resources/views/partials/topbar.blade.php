{{-- The top area: the drawer button and brand below lg, and on every width the two things
     reachable from anywhere — jumping somewhere (Ctrl+K) and the light. --}}
<header class="app-topbar">
    <button type="button" class="nd-icon-btn app-topbar__menu" data-app-menu-open aria-controls="app-sidebar" aria-expanded="false" aria-label="Open menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a class="app-brand app-topbar__brand" href="{{ route('homepage') }}">{{ config('app.name') }}</a>

    <div class="app-topbar__tools">
        @auth
            <button type="button" class="app-jump" data-palette-open aria-haspopup="dialog" aria-controls="nd-palette">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="app-jump__label">Jump to a page, memory or song</span>
                <span class="app-jump__keys" aria-hidden="true"><kbd class="nd-kbd" data-mod-key>Ctrl</kbd><kbd class="nd-kbd">K</kbd></span>
            </button>
        @endauth

        {{-- Cycles follow the clock → light → dark. theme.js keeps the icon and label true. --}}
        <button type="button" class="nd-icon-btn app-theme" data-theme-cycle aria-label="Light: following the clock">
            <i class="fa-solid fa-moon" aria-hidden="true" data-theme-icon></i>
            <span class="app-theme__dot" data-theme-pinned hidden></span>
        </button>

        @auth
            <button type="button" class="nd-icon-btn app-topbar__keys" data-shortcuts-open aria-label="Keyboard shortcuts" aria-haspopup="dialog" aria-controls="nd-shortcuts">
                <i class="fa-regular fa-keyboard" aria-hidden="true"></i>
            </button>
        @endauth
    </div>
</header>
