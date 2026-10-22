<?php

namespace Tests\Feature;

use App\Models\Track;
use App\Models\TrackPlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackPlayTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $preview = false): User
    {
        return User::create(['username' => 'tp_' . Str::lower(Str::random(8)), 'password' => 'x', 'is_preview' => $preview]);
    }

    public function test_plays_count_up_per_local_day(): void
    {
        $user = $this->user();
        $track = Track::create(['user_id' => $user->id, 'title' => 'S', 'path' => 'tracks/x.mp3']);

        // 22:30 UTC on the 22nd is already the 23rd in Baghdad.
        Carbon::setTestNow(Carbon::parse('2026-10-22 22:30:00', 'UTC'));
        $this->actingAs($user)->post(route('music.play', $track))->assertNoContent();
        $this->actingAs($user)->post(route('music.play', $track))->assertNoContent();

        $row = DB::table('track_plays')->sole();
        $this->assertSame('2026-10-23', Carbon::parse($row->play_date)->toDateString());
        $this->assertSame(2, (int) $row->count);
        $this->assertSame([$row->play_date => $track->id], TrackPlay::topByDate($user->id));
    }

    public function test_someone_elses_track_is_forbidden(): void
    {
        $track = Track::create(['user_id' => $this->user()->id, 'title' => 'S', 'path' => 'tracks/x.mp3']);

        $this->actingAs($this->user())->post(route('music.play', $track))->assertForbidden();
        $this->assertSame(0, DB::table('track_plays')->count());
    }

    public function test_the_most_played_wins_the_day(): void
    {
        $user = $this->user();
        $a = Track::create(['user_id' => $user->id, 'title' => 'A', 'path' => 'tracks/a.mp3']);
        $b = Track::create(['user_id' => $user->id, 'title' => 'B', 'path' => 'tracks/b.mp3']);
        DB::table('track_plays')->insert([
            ['user_id' => $user->id, 'track_id' => $a->id, 'play_date' => '2026-10-20', 'count' => 5],
            ['user_id' => $user->id, 'track_id' => $b->id, 'play_date' => '2026-10-20', 'count' => 2],
            ['user_id' => $user->id, 'track_id' => $b->id, 'play_date' => '2026-10-21', 'count' => 1],
        ]);

        $this->assertSame(['2026-10-20' => $a->id, '2026-10-21' => $b->id], TrackPlay::topByDate($user->id));
    }
}
