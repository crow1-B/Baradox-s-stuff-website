<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'track_id', 'play_date', 'count'])]
class TrackPlay extends Model
{
    // play_date is the local day here, never UTC: "today" means the day on the wall clock.
    public const TIMEZONE = 'Asia/Baghdad';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'play_date' => 'date',
        ];
    }

    public static function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    public static function record(int $userId, int $trackId): void
    {
        DB::table('track_plays')->upsert(
            [['user_id' => $userId, 'track_id' => $trackId, 'play_date' => self::today(), 'count' => 1]],
            ['user_id', 'track_id', 'play_date'],
            ['count' => DB::raw('`count` + 1')],
        );
    }

    // date => id of that day's most-played track. Ties go to the most recently added track,
    // so the answer is stable between page loads.
    public static function topByDate(int $userId): array
    {
        $ranked = DB::table('track_plays')
            ->where('user_id', $userId)
            ->select('play_date', 'track_id', DB::raw('ROW_NUMBER() OVER (PARTITION BY play_date ORDER BY `count` DESC, track_id DESC) as position'));

        return DB::query()->fromSub($ranked, 'ranked')
            ->where('position', 1)
            ->pluck('track_id', 'play_date')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
