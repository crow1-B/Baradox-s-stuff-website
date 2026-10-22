<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use \App\Models\DiaryEntry;
use \App\Models\ExtraNote;
use \App\Models\Photo;
use \App\Models\Visit;
use \App\Models\Track;
use \App\Models\TrackPlay;
use \App\Models\Video;

class HomeController extends Controller
{
    private const ACTIVITY = 8;

    // The day a diary entry is about, as SQL: its event date, or the day it was written.
    private const ENTRY_DAY = 'COALESCE(event_date, DATE(created_at))';

    // Same calendar day in earlier years. Locked entries come back too, but the view only
    // ever says that one exists: no title, no mood, no content.
    private function onThisDay(Carbon $today): array
    {
        $userId = Auth::user()->id;
        $monthDay = $today->format('m-d');

        $entries = DiaryEntry::where('user_id', $userId)
            ->whereRaw("DATE_FORMAT(" . self::ENTRY_DAY . ", '%m-%d') = ?", [$monthDay])
            ->whereRaw('YEAR(' . self::ENTRY_DAY . ') < ?', [$today->year])
            ->orderByRaw(self::ENTRY_DAY . ' DESC')
            ->get();

        // Upload date stands in for the day a photo was taken; there is no EXIF column.
        // Protected photos stay out: a memory card is not the place to ask for a key.
        $photos = Photo::where('user_id', $userId)
            ->where('protection', 'none')
            ->whereRaw("DATE_FORMAT(created_at, '%m-%d') = ?", [$monthDay])
            ->whereYear('created_at', '<', $today->year)
            ->orderBy('created_at', 'desc')
            ->take(12)
            ->get();

        return ['entries' => $entries, 'photos' => $photos];
    }

    private function todaysEntry(Carbon $today): ?DiaryEntry
    {
        return DiaryEntry::where('user_id', Auth::user()->id)
            ->whereRaw(self::ENTRY_DAY . ' = ?', [$today->toDateString()])
            ->orderBy('id', 'desc')
            ->first();
    }

    // A small merged timeline of things added across the site. One capped query per source,
    // newest first; photos are grouped per day so a batch upload reads as one event.
    private function recentActivity(): \Illuminate\Support\Collection
    {
        $userId = Auth::user()->id;

        $entries = DiaryEntry::where('user_id', $userId)->latest()->take(self::ACTIVITY)->get()
            ->map(fn ($entry) => [
                'when' => $entry->created_at,
                'icon' => $entry->isLocked() ? 'fa-lock' : 'fa-feather',
                'text' => $entry->isLocked() ? 'Wrote a sealed entry' : 'Wrote “' . $entry->title . '”',
                'url' => route('diary.entry', $entry->id),
            ]);

        $photos = Photo::where('user_id', $userId)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('day')
            ->orderBy('day', 'desc')
            ->take(self::ACTIVITY)
            ->get()
            ->map(fn ($row) => [
                'when' => Carbon::parse($row->last_at),
                'icon' => 'fa-image',
                'text' => 'Added ' . $row->total . ' ' . ($row->total == 1 ? 'photo' : 'photos'),
                'url' => route('photos'),
            ]);

        $tracks = Track::where('user_id', $userId)->latest()->take(self::ACTIVITY)->get()
            ->map(fn ($track) => [
                'when' => $track->created_at,
                'icon' => 'fa-music',
                'text' => 'Added “' . $track->title . '”',
                'url' => route('music'),
            ]);

        $videos = Video::where('user_id', $userId)->latest()->take(self::ACTIVITY)->get()
            ->map(fn ($video) => [
                'when' => $video->created_at,
                'icon' => 'fa-film',
                'text' => 'Registered “' . $video->title . '”',
                'url' => route('videos', ['highlight' => $video->slug]),
            ]);

        $notes = ExtraNote::where('user_id', $userId)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('day')
            ->orderBy('day', 'desc')
            ->take(self::ACTIVITY)
            ->get()
            ->map(fn ($row) => [
                'when' => Carbon::parse($row->last_at),
                'icon' => 'fa-comment-dots',
                'text' => 'Captured ' . $row->total . ' ' . ($row->total == 1 ? 'note' : 'notes'),
                'url' => route('extra-notes'),
            ]);

        return $entries->concat($photos)->concat($tracks)->concat($videos)->concat($notes)
            ->sortByDesc('when')
            ->take(self::ACTIVITY)
            ->values();
    }

    // Today's most-played song when there is one, else the newest.
    private function songOfTheMoment(): array
    {
        $userId = Auth::user()->id;

        $topId = DB::table('track_plays')
            ->where('user_id', $userId)
            ->where('play_date', TrackPlay::today())
            ->orderBy('count', 'desc')
            ->orderBy('track_id', 'desc')
            ->value('track_id');

        if ($topId !== null) {
            return [Track::where('user_id', $userId)->find($topId), 'played'];
        }

        return [Track::where('user_id', $userId)->orderBy('created_at', 'desc')->first(), 'added'];
    }

    private function homeData(): array
    {
        $today = now()->startOfDay();
        [$track, $trackReason] = $this->songOfTheMoment();

        return [
            'name' => Auth::user()->display_name ?: Auth::user()->username,
            'now' => now(),
            'onThisDay' => $this->onThisDay($today),
            'todaysEntry' => $this->todaysEntry($today),
            'activity' => $this->recentActivity(),
            'recentVisits' => Visit::where('user_id', Auth::user()->id)
                ->orderBy('created_at', 'desc')
                ->take(4)
                ->get(),
            'track' => $track,
            'trackReason' => $trackReason,
            'canWrite' => !Auth::user()->isPreview(),
        ];
    }

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
