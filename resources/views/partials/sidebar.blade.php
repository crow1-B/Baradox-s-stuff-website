@php
    // [label, route name, route-name patterns that mark it active]
    // Active state comes from the current route, so sub-pages (albums, the visit history)
    // highlight their section without anything being hardcoded per page.
    $home = ['Home', 'homepage', ['homepage', 'visits.all']];
    $groups = [
        'Media' => [
            ['Music', 'music', ['music']],
            ['Photos', 'photos', ['photos', 'albums.show']],
            ['Videos', 'videos', ['videos', 'video-albums.show']],
        ],
        'Writing' => [
            ['Diary', 'diary', ['diary']],
            ['Extra Notes', 'extra-notes', ['extra-notes']],
        ],
        'Work' => [
            ['Projects', 'projects', ['projects']],
            ['Future Updates', 'future-updates', ['future-updates']],
        ],
    ];
@endphp

<nav class="app-sidebar" id="app-sidebar" aria-label="Main">
    <div class="app-sidebar__head">
        <a class="app-brand" href="{{ route('homepage') }}">{{ config('app.name') }}</a>
        <button type="button" class="app-icon-btn app-sidebar__close" data-app-menu-close aria-label="Close menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
    </div>

    <ul class="app-nav" role="list">
        @include('partials.sidebar-item', ['item' => $home])
    </ul>

    @foreach ($groups as $group => $items)
        <div class="app-nav-group" role="group" aria-labelledby="nav-group-{{ Str::slug($group) }}">
            <p class="app-nav-group__label" id="nav-group-{{ Str::slug($group) }}">{{ $group }}</p>
            <ul class="app-nav" role="list">
                @foreach ($items as $item)
                    @include('partials.sidebar-item', ['item' => $item])
                @endforeach
            </ul>
        </div>
    @endforeach

    {{-- data-turbo="false": signing out is a full page load, which is what stops the player
         (it lives outside Turbo's body swap) and swaps in the login page's own layout. --}}
    <form class="app-signout" method="POST" action="{{ route('logout') }}" data-turbo="false">
        @csrf
        <p class="app-signout__who">Signed in as <span>{{ Auth::user()->display_name ?: Auth::user()->username }}</span></p>
        <button type="submit" class="app-nav__link app-signout__btn">
            <span class="app-nav__icon" aria-hidden="true"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
            <span class="app-nav__label">Sign out</span>
        </button>
    </form>
</nav>
