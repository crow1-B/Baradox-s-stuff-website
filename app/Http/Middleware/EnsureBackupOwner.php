<?php

namespace App\Http\Middleware;

use App\Models\Backup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// The Backups page and its actions exist only for the owner (config backups.owner). Everyone
// else, Guest included, gets a 404 rather than a 403: a 403 would confirm there is something
// there. Sorted before SubstituteBindings and RejectPreviewWrites, so a bound backup id is never
// looked up and Guest's writes get the same 404 as its reads.
class EnsureBackupOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Backup::isOwner(Auth::user())) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
