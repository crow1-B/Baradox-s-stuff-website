@props(['name'])
{{-- Diary page glyphs. Its own set, like x-mu-icon: trimming one page's icons must never
     quietly change another page's. --}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
@switch($name)
    @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @case('pencil')<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>@break
    @case('trash')<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>@break
    @case('x')<path d="M18 6 6 18M6 6l12 12"/>@break
    @case('check')<path d="m5 12 5 5L20 7"/>@break
    @case('alert')<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>@break
    @case('search')<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>@break
    @case('lock')<rect x="4" y="10.5" width="16" height="10.5" rx="2.2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/><circle cx="12" cy="15.8" r="1.1" fill="currentColor" stroke="none"/>@break
    @case('unlock')<rect x="4" y="10.5" width="16" height="10.5" rx="2.2"/><path d="M8 10.5V7a4 4 0 0 1 7.5-1.9"/><circle cx="12" cy="15.8" r="1.1" fill="currentColor" stroke="none"/>@break
    @case('eye')<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>@break
    @case('eye-off')<path d="M4 4.5 20 20M9.6 9.7A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"/><path d="M6.3 6.7C4 8.4 2.5 12 2.5 12S6 18.5 12 18.5c1.6 0 3-.4 4.2-1M9.9 5.8A8.9 8.9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.7 3.6"/>@break
    @case('key')<circle cx="8" cy="12" r="4"/><path d="M12 12h9M18 12v3.5M15 12v2.5"/>@break
    @case('copy')<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h8"/>@break
    @case('user')<circle cx="12" cy="8.5" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>@break
    @case('image')<rect x="3" y="4.5" width="18" height="15" rx="2.2"/><circle cx="8.6" cy="10" r="1.6"/><path d="m4 17 5-4.5 4.5 4 3-2.5L21 18"/>@break
    @case('book')<path d="M4 5.2A2.2 2.2 0 0 1 6.2 3H19v15H6.2A2.2 2.2 0 0 0 4 20.2V5.2Z"/><path d="M4 20.2A2.2 2.2 0 0 1 6.2 18H19v3H6.2A2.2 2.2 0 0 1 4 20.2Z"/>@break
@endswitch
</svg>
