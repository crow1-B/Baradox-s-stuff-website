<?php

namespace App\Support\Shell;

// The site's pages, in one place: the sidebar renders them and the command palette lists
// them. Local-only pages (config('shell.sidebar')) are deliberately not here, so nothing
// the palette offers can name them.
class Navigation
{
    // [label, route name, route-name patterns that mark it active, Font Awesome icon, shortcut key after "g"]
    public const HOME = ['Home', 'homepage', ['homepage', 'visits.all'], 'fa-house', 'h'];

    public const CALENDAR = ['Calendar', 'calendar', ['calendar'], 'fa-calendar-days', 'c'];

    // Above the groups: Home, and the one page that reads across all of them.
    public const PINNED = [self::HOME, self::CALENDAR];

    public const GROUPS = [
        'Media' => [
            ['Music', 'music', ['music'], 'fa-music', 'm'],
            ['Photos', 'photos', ['photos', 'albums.show'], 'fa-image', 'p'],
            ['Videos', 'videos', ['videos', 'video-albums.show'], 'fa-film', 'v'],
        ],
        'Writing' => [
            ['Diary', 'diary', ['diary'], 'fa-book-open', 'd'],
            ['Extra Notes', 'extra-notes', ['extra-notes'], 'fa-comment-dots', 'n'],
        ],
        'Work' => [
            ['Projects', 'projects', ['projects'], 'fa-diagram-project', 'j'],
            ['Future Updates', 'future-updates', ['future-updates'], 'fa-list-check', 'u'],
        ],
    ];

    public static function pages(): array
    {
        $pages = array_map(fn ($item) => self::page($item, null), self::PINNED);

        foreach (self::GROUPS as $group => $items) {
            foreach ($items as $item) {
                $pages[] = self::page($item, $group);
            }
        }

        return $pages;
    }

    private static function page(array $item, ?string $group): array
    {
        [$label, $route, , $icon, $key] = $item;

        return ['label' => $label, 'url' => route($route), 'icon' => $icon, 'key' => $key, 'group' => $group];
    }
}
