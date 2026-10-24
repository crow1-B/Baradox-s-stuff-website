<?php

namespace App\Http\Middleware;

use App\Models\Album;
use App\Models\VideoAlbum;
use App\Support\Http\DeferredWrites;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class LogVisit
{
    // Keyed by route name, not path, so a nested route ("video-albums/5") gets a real label.
    // Every route carrying log.visit must have an entry here; LogVisitTest enforces it.
    public const LABELS = [
        'diary' => 'Diary Page.',
        // No title: a sealed entry's history row would otherwise name it.
        'diary.entry' => 'Diary Entry Page.',
        'extra-notes' => 'Extra Notes Page.',
        'future-updates' => 'Future Updates Page.',
        'music' => 'Music Page.',
        'photos' => 'Photos Studio Page.',
        'projects' => 'Projects Page.',
        'videos' => 'Videos Page.',
        'video-albums.show' => 'Video Album Page.',
        'albums.show' => 'Photo Album Page.',
    ];

    // Routes that carry log.visit but are deliberately not recorded.
    public const SKIPPED = ['homepage'];

    public function __construct(private DeferredWrites $writes)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Recording a visit is a write, and the preview account is read-only.
        if (Auth::user() === null || Auth::user()->isPreview()) {
            return $next($request);
        }

        // A Turbo frame loading into a page is part of the page already recorded, not a visit:
        // the Diary reveal's frame follows a failed attempt's redirect back to the entry.
        if ($request->hasHeader('Turbo-Frame')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::SKIPPED, true)) {
            return $next($request);
        }

        // Queued, not written: the row goes in after the response has been sent
        // (FlushDeferredWrites). The label is worked out now, while the route is at hand.
        $now = now();
        $this->writes->insert('visits', [
            'user_id' => Auth::user()->id,
            'page_name' => $this->label($request, $routeName),
            'path' => $request->path(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $next($request);
    }

    private function label(Request $request, ?string $routeName): string
    {
        $album = $request->route('album');

        // Only name an album the visitor owns: the controller 403s on anyone else's,
        // but this runs first and would otherwise write that name into their history.
        if (($album instanceof VideoAlbum || $album instanceof Album) && $album->user_id === Auth::user()->id) {
            $kind = $album instanceof VideoAlbum ? 'Video Album' : 'Photo Album';

            return $kind . ' “' . $album->name . '” Page.';
        }

        // Unmapped routes are a bug the test catches; this only keeps the label readable.
        return self::LABELS[$routeName] ?? 'Unknown Page.';
    }
}
