<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Track;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// A read-only view over dates the other sections already store. Nothing here writes, and
// every query is scoped to the user and bounded to what the page can show: the month grid's
// visible weeks, or one year for the heatmap.
class CalendarController extends Controller
{
    // The week runs Saturday to Friday, as it does here. Carbon's numbering (Sunday = 0) is
    // the same as JS getUTCDay(), which calendar.js reads from data-week-start.
    public const WEEK_START = Carbon::SATURDAY;

    public const WEEK_END = Carbon::FRIDAY;

    public const LAYERS = ['diary', 'projects', 'photos', 'videos', 'tracks', 'listening'];

    public const DEFAULT_LAYERS = ['diary', 'projects'];

    // calendar.js mirrors the layer choice here ("{user id}:diary-projects") so the page is
    // rendered with it and doesn't flash the defaults first. Not encrypted (bootstrap/app.php):
    // the script writes it. Only names from LAYERS are ever read back out of it.
    public const LAYER_COOKIE = 'nd_cal_layers';

    // Bars per kind per week row; anything past it is still listed in the day's panel.
    private const LANES = 3;

    // Thumbnails per day in the panel; the rest are a count and a link to Photos.
    private const DAY_PHOTOS = 8;

    // The day a diary entry is about, as SQL: the same expression Home uses.
    private const ENTRY_DAY = 'COALESCE(event_date, DATE(created_at))';

    public function showCalendarPage(Request $request)
    {
        $month = $this->month($request->query('month'));
        $gridStart = $month->copy()->startOfWeek(self::WEEK_START);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(self::WEEK_END)->startOfDay();
        $today = now()->startOfDay();

        $selected = $this->selectedDay($request->query('day'), $month, $today);

        // Guest sees the page and nothing on it, whatever rows it may own.
        $empty = Auth::user()->isPreview();
        $userId = Auth::user()->id;

        $days = $this->blankDays($gridStart, $gridEnd);
        $spans = [];
        $projects = collect();

        if (!$empty) {
            $spans = $this->collectEntries($userId, $gridStart, $gridEnd, $days);
            $projects = $this->collectProjects($userId, $gridStart, $gridEnd, $days, $spans);
            $this->collectPhotos($userId, $gridStart, $gridEnd, $days);
            $this->collectVideos($userId, $gridStart, $gridEnd, $days);
            $this->collectTracks($userId, $gridStart, $gridEnd, $days);
            $this->collectListening($userId, $gridStart, $gridEnd, $days);
        }

        return view('calendar', [
            'month' => $month,
            'today' => $today,
            'selected' => $selected,
            'view' => $request->query('view') === 'year' ? 'year' : 'month',
            'weeks' => $this->weeks($days, $spans, $gridStart),
            'days' => $days,
            'monthProjects' => $projects,
            'heatmap' => $empty ? [] : $this->heatmap($userId, $month->year),
            'layers' => self::LAYERS,
            'activeLayers' => $this->activeLayers($request),
            'photoLimit' => self::DAY_PHOTOS,
            'isEmpty' => $empty || ($projects->isEmpty() && !$this->hasAnything($days)),
        ]);
    }

    // The cookie's layers when it is this account's and well formed, else the defaults.
    private function activeLayers(Request $request): array
    {
        $raw = $request->cookie(self::LAYER_COOKIE);

        if (!is_string($raw) || !preg_match('/^(\d+):([a-z-]*)$/', $raw, $m) || (int) $m[1] !== Auth::user()->id) {
            return self::DEFAULT_LAYERS;
        }

        return array_values(array_intersect(self::LAYERS, $m[2] === '' ? [] : explode('-', $m[2])));
    }

    // A malformed month is a 404, not a quiet jump to today: a link that names a month
    // should show that month or say it can't.
    private function month(?string $value): Carbon
    {
        if ($value === null) {
            return now()->startOfMonth();
        }

        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) || (int) $m[1] < 1900 || (int) $m[1] > 2200) {
            abort(404);
        }

        return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
    }

    // The day the panel opens on: ?day= when it falls in the month, else today when today
    // does, else the 1st.
    private function selectedDay(?string $value, Carbon $month, Carbon $today): Carbon
    {
        if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $day = Carbon::createFromFormat('!Y-m-d', $value);
            if ($day !== false && $day->format('Y-m-d') === $value && $day->isSameMonth($month)) {
                return $day;
            }
        }

        return $today->isSameMonth($month) ? $today->copy() : $month->copy();
    }

    private function blankDays(Carbon $start, Carbon $end): array
    {
        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[$day->toDateString()] = [
                'date' => $day->copy(),
                'entries' => [],
                'written' => [],
                'projects' => [],
                'photos' => [],
                'lockedPhotos' => [],
                'videos' => [],
                'tracks' => [],
                'listening' => null,
            ];
        }

        return $days;
    }

    // Diary entries whose event or writing falls in the grid. An open entry gets a marker on
    // each of the two days and, when they differ, a span between them; a sealed one gets one
    // marker on its day and nothing else — not even the day it was written.
    private function collectEntries(int $userId, Carbon $start, Carbon $end, array &$days): array
    {
        $entries = DiaryEntry::where('user_id', $userId)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(fn ($q) => $q->where('created_at', '>=', $start)->where('created_at', '<', $end->copy()->addDay()));
            })
            ->orderBy('created_at')
            ->get(['id', 'title', 'mood', 'event_date', 'created_at', 'key_hash']);

        $spans = [];

        foreach ($entries as $entry) {
            $url = route('diary.entry', $entry->id);

            if ($entry->isLocked()) {
                $key = $entry->day()->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['entries'][] = ['id' => $entry->id, 'locked' => true, 'url' => $url];
                }
                continue;
            }

            $written = $entry->created_at->copy()->startOfDay();
            $event = $entry->event_date?->copy()->startOfDay() ?? $written;
            // Days between the event and the writing; negative when written ahead of it.
            $gap = (int) round($event->diffInDays($written));

            $item = [
                'id' => $entry->id,
                'locked' => false,
                'title' => $entry->title,
                'mood' => $entry->moodDetails() ? $entry->mood : null,
                'moodLabel' => $entry->moodDetails()['label'] ?? null,
                'url' => $url,
                'event' => $event,
                'written' => $written,
                'gap' => $gap,
            ];

            if (isset($days[$event->toDateString()])) {
                $days[$event->toDateString()]['entries'][] = $item;
            }

            if ($gap !== 0) {
                if (isset($days[$written->toDateString()])) {
                    $days[$written->toDateString()]['written'][] = $item;
                }
                $spans[] = ['kind' => 'gap', 'from' => $event->copy()->min($written), 'to' => $event->copy()->max($written), 'item' => $item];
            }
        }

        return $spans;
    }

    // Projects whose start..finish overlaps the grid. A project with no finish date yet is
    // a one-day bar on its start, marked open-ended.
    private function collectProjects(int $userId, Carbon $start, Carbon $end, array &$days, array &$spans): Collection
    {
        $projects = Project::where('user_id', $userId)
            ->where('start_date', '<=', $end->toDateString())
            ->whereRaw('COALESCE(finish_date, start_date) >= ?', [$start->toDateString()])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'title', 'status', 'start_date', 'finish_date']);

        $projectSpans = [];

        $list = $projects->map(function ($project) use (&$days, &$projectSpans) {
            $item = [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status,
                'statusLabel' => str_replace('_', ' ', $project->status),
                'start' => $project->start_date,
                'finish' => $project->finish_date,
                'url' => route('projects', ['tab' => $project->status, 'highlight' => $project->id]),
            ];

            foreach (['start' => $project->start_date, 'finish' => $project->finish_date] as $edge => $date) {
                if ($date !== null && isset($days[$date->toDateString()])) {
                    $days[$date->toDateString()]['projects'][] = $item + ['edge' => $edge];
                }
            }

            $projectSpans[] = ['kind' => 'project', 'from' => $project->start_date, 'to' => $project->finish_date ?? $project->start_date, 'item' => $item];

            return $item;
        });

        // Projects take the top lanes, so they go first.
        $spans = array_merge($projectSpans, $spans);

        return $list;
    }

    // Upload dates. A protected photo is a count and a padlock: no thumbnail URL of any kind
    // leaves this method for it.
    private function collectPhotos(int $userId, Carbon $start, Carbon $end, array &$days): void
    {
        $photos = Photo::where('user_id', $userId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->copy()->addDay())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'protection', 'created_at', 'updated_at']);

        foreach ($photos as $photo) {
            $key = $photo->created_at->toDateString();

            // A locked photo links to its key prompt (Photos' ?photo=) and carries nothing else.
            if ($photo->isProtected()) {
                $days[$key]['lockedPhotos'][] = ['url' => route('photos', ['photo' => $photo->id])];
            } else {
                $days[$key]['photos'][] = ['thumb' => $photo->thumbnailUrl(), 'url' => route('photos', ['photo' => $photo->id])];
            }
        }
    }

    // Registration dates. No posters at all — a gated one must not have one, and an ungated
    // one would make the first visit shell out to ffmpeg per video. A gated video is not
    // named either: its slug carries its title, so it links to the Videos page plain.
    private function collectVideos(int $userId, Carbon $start, Carbon $end, array &$days): void
    {
        $videos = Video::where('user_id', $userId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->copy()->addDay())
            ->orderBy('created_at')
            ->get(['id', 'title', 'slug', 'protection', 'created_at']);

        foreach ($videos as $video) {
            $days[$video->created_at->toDateString()]['videos'][] = $video->isProtected()
                ? ['locked' => true, 'url' => route('videos')]
                : ['locked' => false, 'title' => $video->title, 'url' => route('videos', ['highlight' => $video->slug])];
        }
    }

    private function collectTracks(int $userId, Carbon $start, Carbon $end, array &$days): void
    {
        $tracks = Track::where('user_id', $userId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->copy()->addDay())
            ->orderBy('created_at')
            ->get();

        foreach ($tracks as $track) {
            $days[$track->created_at->toDateString()]['tracks'][] = [
                'title' => $track->title,
                'artist' => $track->artist_name,
                'play' => $track->playerPayload(),
            ];
        }
    }

    // track_plays per day: total plays and that day's top three songs. Both sides of the
    // join are scoped, so a row can never pull in someone else's track.
    private function collectListening(int $userId, Carbon $start, Carbon $end, array &$days): void
    {
        $rows = DB::table('track_plays')
            ->join('tracks', 'tracks.id', '=', 'track_plays.track_id')
            ->where('track_plays.user_id', $userId)
            ->where('tracks.user_id', $userId)
            ->whereBetween('track_plays.play_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('track_plays.play_date')
            ->orderBy('track_plays.count', 'desc')
            ->orderBy('track_plays.track_id', 'desc')
            ->get(['track_plays.play_date', 'track_plays.count', 'tracks.id', 'tracks.title', 'tracks.artist_name', 'tracks.duration']);

        $tracks = Track::where('user_id', $userId)->whereIn('id', $rows->pluck('id')->unique())->get()->keyBy('id');

        foreach ($rows->groupBy('play_date') as $date => $plays) {
            $key = Carbon::parse($date)->toDateString();
            $days[$key]['listening'] = [
                'plays' => (int) $plays->sum('count'),
                'songs' => $plays->count(),
                'top' => $plays->take(3)->map(fn ($row) => [
                    'title' => $row->title,
                    'artist' => $row->artist_name,
                    'count' => (int) $row->count,
                    'play' => $tracks[$row->id]->playerPayload(),
                ])->values()->all(),
            ];
        }
    }

    // Week rows for the grid, with each span cut into one segment per row it crosses and
    // given a lane. Lanes are per kind, so a long project never pushes a diary gap out.
    private function weeks(array $days, array $spans, Carbon $gridStart): array
    {
        $weeks = array_map(fn ($chunk) => ['days' => $chunk, 'bars' => [], 'lanes' => 0, 'hidden' => []], array_chunk($days, 7, true));
        $gridEnd = end($days)['date'];

        foreach (['project', 'gap'] as $kind) {
            $taken = array_fill(0, count($weeks), []);

            foreach ($spans as $span) {
                if ($span['kind'] !== $kind || $span['to']->lt($gridStart) || $span['from']->gt($gridEnd)) {
                    continue;
                }

                $from = $span['from']->copy()->max($gridStart);
                $to = $span['to']->copy()->min($gridEnd);

                for ($w = intdiv((int) $gridStart->diffInDays($from), 7); $w <= intdiv((int) $gridStart->diffInDays($to), 7); $w++) {
                    $weekStart = $gridStart->copy()->addDays($w * 7);
                    $segFrom = $from->copy()->max($weekStart);
                    $segTo = $to->copy()->min($weekStart->copy()->addDays(6));
                    $col = (int) $weekStart->diffInDays($segFrom) + 1;
                    $end = (int) $weekStart->diffInDays($segTo) + 1;

                    $lane = 0;
                    while ($lane < self::LANES && $this->overlaps($taken[$w][$lane] ?? [], $col, $end)) {
                        $lane++;
                    }

                    if ($lane === self::LANES) {
                        for ($c = $col; $c <= $end; $c++) {
                            $weeks[$w]['hidden'][$c] = ($weeks[$w]['hidden'][$c] ?? 0) + 1;
                        }
                        continue;
                    }

                    $taken[$w][$lane][] = [$col, $end];

                    $weeks[$w]['bars'][] = [
                        'kind' => $kind,
                        'lane' => $lane,
                        'col' => $col,
                        'end' => $end,
                        'item' => $span['item'],
                        'startsHere' => $segFrom->equalTo($span['from']),
                        'endsHere' => $segTo->equalTo($span['to']),
                        'openEnded' => $kind === 'project' && $span['item']['finish'] === null,
                    ];
                }
            }

            foreach ($taken as $w => $lanes) {
                $weeks[$w][$kind . 'Lanes'] = count($lanes);
            }
        }

        // Gap lanes sit under the project lanes.
        return array_map(function ($week) {
            $week['lanes'] = $week['projectLanes'] + $week['gapLanes'];
            $week['bars'] = array_map(fn ($bar) => $bar['kind'] === 'gap' ? ['lane' => $bar['lane'] + $week['projectLanes']] + $bar : $bar, $week['bars']);

            return $week;
        }, $weeks);
    }

    private function overlaps(array $segments, int $col, int $end): bool
    {
        foreach ($segments as [$a, $b]) {
            if ($col <= $b && $end >= $a) {
                return true;
            }
        }

        return false;
    }

    // date => number of entries about that day, for one year. Sealed entries count: the
    // heatmap is date markers and nothing else.
    private function heatmap(int $userId, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year + 1, 1, 1)->startOfDay();

        return DiaryEntry::where('user_id', $userId)
            ->where(function ($query) use ($start, $end) {
                $query->where(fn ($q) => $q->where('event_date', '>=', $start->toDateString())->where('event_date', '<', $end->toDateString()))
                    ->orWhere(fn ($q) => $q->whereNull('event_date')->where('created_at', '>=', $start)->where('created_at', '<', $end));
            })
            ->selectRaw(self::ENTRY_DAY . ' as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [Carbon::parse($day)->toDateString() => (int) $total])
            ->all();
    }

    private function hasAnything(array $days): bool
    {
        foreach ($days as $day) {
            if ($day['entries'] || $day['written'] || $day['projects'] || $day['photos'] || $day['lockedPhotos']
                || $day['videos'] || $day['tracks'] || $day['listening']) {
                return true;
            }
        }

        return false;
    }
}
