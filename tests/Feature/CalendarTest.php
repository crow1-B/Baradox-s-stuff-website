<?php

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Track;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00', 'Asia/Baghdad'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(bool $preview = false): User
    {
        return User::create(['username' => 'cal_' . Str::lower(Str::random(8)), 'password' => 'x', 'is_preview' => $preview]);
    }

    private function entry(User $user, string $title, ?string $event, string $written, array $extra = []): DiaryEntry
    {
        $entry = DiaryEntry::create(['user_id' => $user->id, 'title' => $title, 'content' => 'Words of ' . $title, 'event_date' => $event] + $extra);
        $entry->forceFill(['created_at' => Carbon::parse($written . ' 21:00'), 'updated_at' => Carbon::parse($written . ' 21:00')])->save();

        return $entry;
    }

    private function photo(User $user, string $protection, string $added): Photo
    {
        $photo = Photo::create(['user_id' => $user->id, 'path' => 'photos/' . Str::random(12) . '.jpg', 'protection' => $protection, 'key_hash' => $protection === 'none' ? null : 'h']);
        $photo->forceFill(['created_at' => Carbon::parse($added . ' 10:00')])->save();

        return $photo;
    }

    private function video(User $user, string $title, string $protection, string $added): Video
    {
        $video = Video::create(['user_id' => $user->id, 'title' => $title, 'filename' => Str::random(6) . '.mp4', 'slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)), 'protection' => $protection, 'key_hash' => $protection === 'none' ? null : 'h']);
        $video->forceFill(['created_at' => Carbon::parse($added . ' 10:00')])->save();

        return $video;
    }

    private function track(User $user, string $title, string $added): Track
    {
        $track = Track::create(['user_id' => $user->id, 'title' => $title, 'path' => 'tracks/' . Str::random(6) . '.mp3']);
        $track->forceFill(['created_at' => Carbon::parse($added . ' 10:00')])->save();

        return $track;
    }

    private function seedEverything(User $user, string $prefix): void
    {
        $this->entry($user, "$prefix entry", '2026-10-10', '2026-10-10');
        Project::create(['user_id' => $user->id, 'title' => "$prefix project", 'description' => 'd', 'status' => 'in_work', 'start_date' => '2026-10-05', 'finish_date' => '2026-10-20']);
        $this->photo($user, 'none', '2026-10-12');
        $this->video($user, "$prefix video", 'none', '2026-10-13');
        $track = $this->track($user, "$prefix song", '2026-10-14');
        DB::table('track_plays')->insert(['user_id' => $user->id, 'track_id' => $track->id, 'play_date' => '2026-10-15', 'count' => 4]);
    }

    public function test_the_page_is_in_the_navigation_and_logs_a_visit(): void
    {
        $me = $this->user();

        $this->actingAs($me)->get(route('calendar'))->assertOk()->assertSee('href="' . route('calendar') . '"', false);
        $this->assertSame(1, DB::table('visits')->where('user_id', $me->id)->where('page_name', 'Calendar Page.')->count());
    }

    public function test_everything_is_scoped_to_the_user(): void
    {
        $me = $this->user();
        $other = $this->user();
        $this->seedEverything($me, 'Mine');
        $this->seedEverything($other, 'Theirs');

        $response = $this->actingAs($me)->get(route('calendar'))->assertOk();

        foreach (['entry', 'project', 'video', 'song'] as $what) {
            $response->assertSee("Mine $what")->assertDontSee("Theirs $what");
        }

        $days = $response->viewData('days');
        $this->assertCount(1, $days['2026-10-12']['photos']);
        $this->assertSame(4, $days['2026-10-15']['listening']['plays']);
        $this->assertSame(1, array_sum($response->viewData('heatmap')));
    }

    public function test_listening_never_joins_someone_elses_track(): void
    {
        $me = $this->user();
        $other = $this->user();
        $theirs = $this->track($other, 'Their tune', '2026-10-01');
        // A play row in my name pointing at their track: the join is scoped on both sides.
        DB::table('track_plays')->insert(['user_id' => $me->id, 'track_id' => $theirs->id, 'play_date' => '2026-10-15', 'count' => 9]);

        $response = $this->actingAs($me)->get(route('calendar'))->assertOk()->assertDontSee('Their tune');
        $this->assertNull($response->viewData('days')['2026-10-15']['listening']);
    }

    public function test_guest_sees_the_page_with_nothing_on_it(): void
    {
        $guest = $this->user(preview: true);
        $this->seedEverything($guest, 'Guest');

        $response = $this->actingAs($guest)->get(route('calendar'))->assertOk()
            ->assertSee('Nothing dated in October 2026');

        foreach (['entry', 'project', 'video', 'song'] as $what) {
            $response->assertDontSee("Guest $what");
        }
        $this->assertSame([], $response->viewData('heatmap'));
        $this->assertTrue($response->viewData('monthProjects')->isEmpty());
        $this->assertSame(0, DB::table('visits')->count());

        // Read-only page: no form the guest could post, beyond signing out.
        preg_match_all('/<form\b[^>]*\bmethod="POST"[^>]*>/i', $response->getContent(), $forms);
        $this->assertSame([], array_values(array_filter($forms[0], fn ($form) => !str_contains($form, route('logout')))));
    }

    public function test_a_sealed_entry_is_a_date_marker_and_nothing_else(): void
    {
        $me = $this->user();
        $track = $this->track($me, 'Sealed song', '2026-01-01');
        $photo = $this->photo($me, 'none', '2026-01-01');
        // Written four days after the day it is about: the gap would say when it was written.
        $sealed = $this->entry($me, 'Secret title', '2026-10-08', '2026-10-12', [
            'mood' => 'stormy', 'track_id' => $track->id, 'key_hash' => 'k', 'algorithm_hash' => 'a',
        ]);
        $sealed->photos()->attach($photo->id);

        $response = $this->actingAs($me)->get(route('calendar'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('A sealed entry')->assertSee('Sealed entry')
            ->assertDontSee('Secret title')->assertDontSee('Words of Secret')
            ->assertDontSee('Stormy')->assertDontSee('data-mood="stormy"', false)
            ->assertDontSee('Sealed song');
        $this->assertStringNotContainsString($photo->thumbnailUrl(), $html);
        $this->assertStringNotContainsString('/photos/' . $photo->id . '/', $html);
        // No pairing hook, no gap, no written marker.
        $this->assertStringNotContainsString('data-cal-entry="' . $sealed->id . '"', $html);

        $days = $response->viewData('days');
        $this->assertCount(1, $days['2026-10-08']['entries']);
        $this->assertSame([], $days['2026-10-12']['written']);
        $this->assertSame(['id' => $sealed->id, 'locked' => true, 'url' => route('diary.entry', $sealed->id)], $days['2026-10-08']['entries'][0]);
        // The heatmap counts it on its day, as a number only.
        $this->assertSame(1, $response->viewData('heatmap')['2026-10-08']);
    }

    public function test_an_open_entry_marks_both_days_and_the_gap_between(): void
    {
        $me = $this->user();
        $entry = $this->entry($me, 'Late words', '2026-10-06', '2026-10-09', ['mood' => 'calm']);

        $response = $this->actingAs($me)->get(route('calendar'))->assertOk()
            ->assertSee('Written 9 Oct, 3 days later')
            ->assertSee('Written this day, about 6 Oct')
            ->assertSee('data-morph="dy-entry-' . $entry->id . '"', false);

        $gaps = collect($response->viewData('weeks'))->flatMap(fn ($week) => $week['bars'])->where('kind', 'gap')->values();
        $this->assertCount(1, $gaps);
        $this->assertSame([2, 5], [$gaps[0]['col'], $gaps[0]['end']]); // Tue 6 .. Fri 9 in a Monday week
        $this->assertTrue($gaps[0]['startsHere'] && $gaps[0]['endsHere']);
    }

    public function test_protected_media_never_hand_out_a_cover(): void
    {
        $me = $this->user();
        $plain = $this->photo($me, 'none', '2026-10-12');
        $gated = $this->photo($me, 'gated', '2026-10-12');
        $encrypted = $this->photo($me, 'encrypted', '2026-10-12');
        $vPlain = $this->video($me, 'Open clip', 'none', '2026-10-13');
        $vGated = $this->video($me, 'Hidden clip', 'gated', '2026-10-13');

        $html = $this->actingAs($me)->get(route('calendar', ['month' => '2026-10']))->assertOk()->getContent();

        // Not vacuous: the plain photo's thumbnail is there.
        $this->assertStringContainsString(e($plain->thumbnailUrl()), $html);
        foreach ([$gated, $encrypted] as $photo) {
            $this->assertStringNotContainsString('/photos/' . $photo->id . '/', $html);
        }
        $this->assertStringContainsString('2 locked', $html);

        // No poster for any video, and the gated one is neither named nor linked by its slug.
        $this->assertDoesNotMatchRegularExpression('~/videos/\d+/(poster|stream)~', $html);
        $this->assertStringContainsString('Open clip', $html);
        $this->assertStringNotContainsString('Hidden clip', $html);
        $this->assertStringNotContainsString($vGated->slug, $html);
        $this->assertStringContainsString('A locked video', $html);
        $this->assertStringContainsString($vPlain->slug, $html);
    }

    public function test_queries_are_bounded_to_the_visible_range(): void
    {
        $me = $this->user();
        // October 2026's grid runs Mon 28 Sep .. Sun 1 Nov.
        $this->entry($me, 'Lead-in day', '2026-09-28', '2026-09-28');
        $this->entry($me, 'Trailing day', '2026-11-01', '2026-11-01');
        $this->entry($me, 'Before the grid', '2026-09-27', '2026-09-27');
        $this->entry($me, 'After the grid', '2026-11-02', '2026-11-02');
        $this->entry($me, 'Last year', '2025-10-10', '2025-10-10');
        Project::create(['user_id' => $me->id, 'title' => 'Across the month', 'description' => 'd', 'status' => 'in_work', 'start_date' => '2026-08-01', 'finish_date' => '2026-12-31']);
        Project::create(['user_id' => $me->id, 'title' => 'Over by summer', 'description' => 'd', 'status' => 'done', 'start_date' => '2026-05-01', 'finish_date' => '2026-07-01']);
        Project::create(['user_id' => $me->id, 'title' => 'Not started yet', 'description' => 'd', 'status' => 'future', 'start_date' => '2026-12-01']);
        $this->photo($me, 'none', '2026-09-27');
        $this->track($me, 'Next month song', '2026-11-02');

        $response = $this->actingAs($me)->get(route('calendar', ['month' => '2026-10']))->assertOk();
        $response->assertSee('Lead-in day')->assertSee('Trailing day')->assertSee('Across the month')
            ->assertDontSee('Before the grid')->assertDontSee('After the grid')->assertDontSee('Last year')
            ->assertDontSee('Over by summer')->assertDontSee('Not started yet')->assertDontSee('Next month song');

        $days = $response->viewData('days');
        $this->assertSame(['2026-09-28', '2026-11-01'], [array_key_first($days), array_key_last($days)]);
        $this->assertSame(0, collect($days)->sum(fn ($day) => count($day['photos'])));

        // A project that spans the whole month is a bar across every week, open at both ends.
        $bars = collect($response->viewData('weeks'))->map(fn ($week) => collect($week['bars'])->where('kind', 'project')->first());
        $this->assertCount(5, $bars->filter());
        $this->assertFalse($bars->contains(fn ($bar) => $bar['startsHere'] || $bar['endsHere']));

        // The heatmap is one year, and only that year.
        $this->assertSame(['2026-09-27' => 1, '2026-09-28' => 1, '2026-11-01' => 1, '2026-11-02' => 1], collect($response->viewData('heatmap'))->sortKeys()->all());
        $this->assertSame(['2025-10-10' => 1], $this->actingAs($me)->get(route('calendar', ['month' => '2025-03']))->viewData('heatmap'));
    }

    public function test_a_malformed_month_is_a_404(): void
    {
        $me = $this->user();

        foreach (['2026-13', '2026-1', 'october', '1899-12', '2026-10-01'] as $month) {
            $this->actingAs($me)->get(route('calendar', ['month' => $month]))->assertNotFound();
        }
        $this->actingAs($me)->get(route('calendar', ['month' => '2026-02', 'day' => '2026-02-30']))->assertOk();
    }
}
