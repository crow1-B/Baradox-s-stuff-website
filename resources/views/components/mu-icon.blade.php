@props(['name'])
{{-- Music page glyphs. Same shape as x-pj-icon (24px box, currentColor stroke) but its own
     set, so trimming one page's icons can never quietly change the other's. --}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
@switch($name)
    @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @case('pencil')<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>@break
    @case('trash')<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>@break
    @case('x')<path d="M18 6 6 18M6 6l12 12"/>@break
    @case('check')<path d="m5 12 5 5L20 7"/>@break
    @case('alert')<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>@break
    @case('search')<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>@break
    @case('caret')<path d="m6 9 6 6 6-6"/>@break
    {{-- note: the 40px cover placeholder, and the "no tracks yet" mark --}}
    @case('note')<path d="M9 18V5.5l11-2V16"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>@break
    @case('star')<path d="m12 3.6 2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.9l-5.2 2.7 1-5.75-4.2-4.1 5.8-.85L12 3.6Z"/>@break
    @case('play')<path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor" stroke="none"/>@break
    @case('pause')<rect x="6.5" y="5" width="4" height="14" rx="1.2" fill="currentColor" stroke="none"/><rect x="13.5" y="5" width="4" height="14" rx="1.2" fill="currentColor" stroke="none"/>@break
@endswitch
</svg>
