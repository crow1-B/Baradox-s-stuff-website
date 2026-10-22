<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// The preview account is read-only: every non-GET request it makes is refused here, in one
// place, rather than per controller. Appended to the web group and sorted after
// SubstituteBindings, so anything a route guards with its own middleware (e.g. a 404 for a
// page that must not admit it exists) still answers first.
//
// config('shell.preview_handled') is an explicit list of route names, pushed by local
// providers, for routes that already refuse the preview account themselves and must keep
// answering it in their own way. Exact names only; PreviewHandledRoutesTest holds each one
// to rejecting or doing nothing on its own.
class RejectPreviewWrites
{
    public const MESSAGE = 'The preview account is read-only.';

    private const READS = ['GET', 'HEAD', 'OPTIONS'];

    // Leaving is not a write; a preview account that can't sign out is a trap.
    private const ALLOWED = ['logout'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null
            && $user->isPreview()
            && ! in_array($request->method(), self::READS, true)
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)
            && ! in_array($request->route()?->getName(), config('shell.preview_handled', []), true)) {
            abort(403, self::MESSAGE);
        }

        return $next($request);
    }
}
