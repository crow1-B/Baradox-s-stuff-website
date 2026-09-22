<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class LogVisit
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user() === null) {
            return $next($request);
        }
        if ($request->path() === "homepage") {
            return $next($request);
        }
        $ID= Auth::user()->id;
        $url = $request->path();
        $PageName=null;
        if($request->path() === "diary"){
            $PageName = "Diary Page.";
        }
        else if ($request->path() === "future_updates") {
            $PageName = "Future Updates Page.";
        }
        else if ($request->path() === "extra_notes") {
            $PageName = "Extra Notes Page.";
        }
        else if ($request->path() === "photos") {
            $PageName = "Photos Studio Page.";
        }
        else if ($request->path() === "projects") {
            $PageName = "Projects Page.";
        }
        else if ($request->path() === "videos") {
            $PageName = "Videos Page.";
        }
        else {
            $PageName = ucfirst($url) . " Page.";
        }
        Visit::create([
            'user_id' => $ID,
            'page_name' => $PageName,
            'path' => $url
        ]);
        return $next($request);
    }
}
