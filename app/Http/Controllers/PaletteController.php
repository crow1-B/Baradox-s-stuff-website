<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\DiaryEntry;
use App\Models\Project;
use App\Models\Track;
use App\Models\Video;
use App\Models\VideoAlbum;
use App\Support\Shell\Navigation;
use Illuminate\Support\Facades\Auth;

// Everything the command palette can jump to. Built from the tracked tables only: anything
// local-only stays out of it by construction, not by a filter someone could forget.
class PaletteController extends Controller
{
    public function items()
    {
        $userId = Auth::user()->id;

        $entries = DiaryEntry::where('user_id', $userId)
            ->orderByRaw('COALESCE(event_date, created_at) DESC')
            ->get(['id', 'title', 'event_date', 'created_at', 'key_hash'])
            ->map(fn ($entry) => [
                'kind' => 'entry',
                'label' => $entry->title,
                'hint' => $entry->day()->format('j M Y'),
                'icon' => $entry->isLocked() ? 'fa-lock' : 'fa-feather',
                'url' => route('diary.entry', $entry->id),
            ]);

        $tracks = Track::where('user_id', $userId)->orderBy('created_at', 'desc')->get()
            ->map(fn ($track) => [
                'kind' => 'track',
                'label' => $track->title,
                'hint' => $track->artist_name,
                'icon' => 'fa-play',
                'play' => $track->playerPayload(),
            ]);

        $projects = Project::where('user_id', $userId)->orderBy('title')->get(['id', 'title', 'status'])
            ->map(fn ($project) => [
                'kind' => 'project',
                'label' => $project->title,
                'hint' => str_replace('_', ' ', $project->status),
                'icon' => 'fa-diagram-project',
                'url' => route('projects', ['tab' => $project->status, 'highlight' => $project->id]),
            ]);

        $albums = Album::where('user_id', $userId)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($album) => [
                'kind' => 'album',
                'label' => $album->name,
                'hint' => 'Photo album',
                'icon' => 'fa-images',
                'url' => route('albums.show', $album->id),
            ]);

        $videoAlbums = VideoAlbum::where('user_id', $userId)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($album) => [
                'kind' => 'album',
                'label' => $album->name,
                'hint' => 'Video album',
                'icon' => 'fa-photo-film',
                'url' => route('video-albums.show', $album->id),
            ]);

        $videos = Video::where('user_id', $userId)->orderBy('title')->get(['id', 'title', 'slug'])
            ->map(fn ($video) => [
                'kind' => 'video',
                'label' => $video->title,
                'hint' => 'Video',
                'icon' => 'fa-film',
                'url' => route('videos', ['highlight' => $video->slug]),
            ]);

        return response()->json([
            'pages' => Navigation::pages(),
            'items' => $entries->concat($tracks)->concat($projects)->concat($albums)
                ->concat($videoAlbums)->concat($videos)->values(),
        ]);
    }
}
