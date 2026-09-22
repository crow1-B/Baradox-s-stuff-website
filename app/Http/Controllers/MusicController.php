<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MusicController extends Controller
{

    private function tracksForUser()
    {
        return \App\Models\Track::where('user_id', Auth::user()->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function viewMusicPage()
    {
        return view('music', ['tracks' => $this->tracksForUser()]);
    }


    private function durationRule(): callable
    {
        return function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $parts)) {
                $fail('The duration must look like M:SS, for example 4:07.');
                return;
            }
            if ((int) $parts[1] > 59) {
                $fail('Invalid duration: minutes must be between 0 and 59.');
            }
            if ((int) $parts[2] > 59) {
                $fail('Invalid duration: seconds must be between 0 and 59.');
            }
        };
    }
    private function getSecondes(string $time) : int
    {

        $time_ar = explode(':', $time);
        $seconds = (int) $time_ar[1];
        $minutes = (int) $time_ar[0];
        if ($seconds > 59) {
            abort(422, 'Invalid duration: seconds must be between 0 and 59.');
        }
        if ($minutes > 59) {
            abort(422, 'Invalid duration: minutes must be between 0 and 59.');
        }

        return ($minutes * 60) + $seconds;
    }
    public function storeMusic(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'audio_file' => 'required|file|mimes:mp3,wav,ogg|max:51200',
            'duration' => ['required', $this->durationRule()],
            'song_date' => ['nullable', function ($attribute, $value, $fail) {
                if ($value && !preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value)) {
                    $fail('The release date format is invalid.');
                }
            }]
        ]);

        $duration = $this->getSecondes($request->input('duration'));
        $path = $request->file('audio_file')->store('tracks', 'public');

        $title = $request->input('title');
        $id = Auth::user()->id;
        $artist_name = $request->input('artist_name');
        $song_date = $request->input('song_date');
        $favorite = $request->boolean('favorite');

        \App\Models\Track::create([
            'title' => $title,
            'artist_name' => $artist_name,
            'path' => $path,
            'user_id' => $id,
            'duration' => $duration,
            'song_date' => $song_date,
            'favorite' => $favorite,
        ]);

        return redirect('/music')->with('success', 'Track uploaded successfully.');
    }
    public function updateMusic(Request $request, \App\Models\Track $track)
    {
        if ($track->user_id !== Auth::user()->id) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string',
            'artist_name' => 'nullable|string',
            'duration' => ['nullable', $this->durationRule()],
            'song_date' => ['nullable', function ($attribute, $value, $fail) {
                if ($value && !preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value)) {
                    $fail('The release date format is invalid.');
                }
            }],
        ]);

        $attributes = [
            'title' => $request->input('title'),
            'artist_name' => $request->input('artist_name'),
            'song_date' => $request->input('song_date'),
        ];


        if ($request->filled('duration')) {
            $attributes['duration'] = $this->getSecondes($request->input('duration'));
        }

        $track->update($attributes);

        return back()->with('success', 'Track updated successfully.');
    }

    public function destroyMusic(\App\Models\Track $track)
    {
        if ($track->user_id !== Auth::user()->id) {
            abort(403);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($track->path);
        $track->delete();

        return back()->with('success', 'Track deleted successfully.');
    }

    public function toggleFavorite(\App\Models\Track $track)
    {
        if ($track->user_id !== Auth::user()->id) {
            abort(403);
        }

        $track->favorite = !$track->favorite;
        $track->save();

        return back();
    }

    public function queue()
    {
        return response()->json($this->tracksForUser()->map->playerPayload()->values());
    }

    
    public function streamTrack(\App\Models\Track $track)
    {
        if ($track->user_id !== Auth::user()->id) {
            abort(403);
        }

        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($track->path);

        if (!is_file($path)) {
            abort(404, 'Audio file not found.');
        }

        return response()->file($path);
    }
}
