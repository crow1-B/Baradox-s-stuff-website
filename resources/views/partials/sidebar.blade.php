@php
    use App\Support\Shell\Navigation;
@endphp

<nav class="app-sidebar" id="app-sidebar" aria-label="Main">
    <div class="app-sidebar__head">
        <a class="app-brand" href="{{ route('homepage') }}">{{ config('app.name') }}</a>
        <button type="button" class="nd-icon-btn app-sidebar__close" data-app-menu-close aria-label="Close menu">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="app-nav" role="list">
        @foreach (Navigation::PINNED as $item)
            @include('partials.sidebar-item', ['item' => $item])
        @endforeach
    </ul>

    @foreach (Navigation::GROUPS as $group => $items)
        @include('partials.sidebar-group')
    @endforeach

    @foreach (config('shell.sidebar', []) as $partial)
        @include($partial)
    @endforeach

    {{-- Owner-only groups sit below the shell.sidebar partials, so those still follow Work. --}}
    @foreach (Navigation::ownerGroups(Auth::user()) as $group => $items)
        @include('partials.sidebar-group')
    @endforeach

    {{-- data-turbo="false": signing out is a full page load, which is what stops the player
         (it lives outside Turbo's body swap) and swaps in the login page's own layout. --}}
    <form class="app-signout" method="POST" action="{{ route('logout') }}" data-turbo="false">
        @csrf
        <p class="app-signout__who">Signed in as <span>{{ Auth::user()->display_name ?: Auth::user()->username }}</span>@if (Auth::user()->isPreview()) <span class="app-signout__preview">read-only</span>@endif</p>
        <button type="submit" class="app-nav__link app-signout__btn">
            <span class="app-nav__icon" aria-hidden="true"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
            <span class="app-nav__label">Sign out</span>
        </button>
    </form>
</nav>
