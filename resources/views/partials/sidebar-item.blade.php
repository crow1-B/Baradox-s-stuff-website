@php
    [$label, $route, $patterns, $icon] = $item;
    $active = request()->routeIs(...$patterns);
@endphp
<li>
    <a href="{{ route($route) }}" class="app-nav__link" @if ($active) aria-current="page" @endif>
        <span class="app-nav__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
        <span class="app-nav__label">{{ $label }}</span>
    </a>
</li>
