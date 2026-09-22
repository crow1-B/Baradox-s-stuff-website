<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use \App\Models\Visit;
use \App\Models\Track;

class HomeController extends Controller
{
    private function greeting(): array
    {
        $name = Auth::user()->display_name ?: Auth::user()->username;
        $hour = now()->hour;

        if ($hour < 5) {
            return ["Welcome {$name}", 'some late night work ?'];
        }
        if ($hour < 12) {
            return ["Good Morning {$name}", "An early bird can't miss the worm !"];
        }
        if ($hour < 18) {
            return ["Good afternoon {$name}", "lame hours aren't they?"];
        }
        return ["Good evening {$name}", 'enjoy it while it lasts.'];
    }

    private function homeData(): array
    {
        [$headline, $subline] = $this->greeting();

        return [
            'headline' => $headline,
            'subline' => $subline,
            'now' => now(),
            'recentVisits' => Visit::where('user_id', Auth::user()->id)
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get(),
            'lastTrack' => Track::where('user_id', Auth::user()->id)
                ->orderBy('created_at', 'desc')
                ->first(),
        ];
    }

    //? The home page welcome messages.
    public function showHomePage()
    {
        return view('homepage', $this->homeData());
    }

    public function showAllVisits()
    {
        return view('homepage', $this->homeData() + [
            'allVisits' => Visit::where('user_id', Auth::user()->id)
                ->orderBy('created_at', 'desc')
                ->get(),
            'showAllVisits' => true,
        ]);
    }

    public function deleteVisit(Visit $visit)
    {
        if ($visit->user_id !== Auth::user()->id) {
            abort(403);
        }

        $visit->delete();

        return back();
    }

    public function deleteAllVisits()
    {
        Visit::where('user_id', Auth::user()->id)->delete();

        return back();
    }
}
