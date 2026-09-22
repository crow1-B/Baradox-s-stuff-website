@php
    [$label, $route, $patterns] = $item;
    $active = request()->routeIs(...$patterns);
@endphp
<li>
    <a href="{{ route($route) }}" class="app-nav__link" @if ($active) aria-current="page" @endif>
        {{-- Icon slot: 20px, intentionally empty for now. Put an <svg> inside to add an icon;
             the placeholder dot hides itself as soon as the span has content. --}}
        <span class="app-nav__icon" aria-hidden="true"></span>
        <span class="app-nav__label">{{ $label }}</span>
    </a>
</li>
