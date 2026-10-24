<?php

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\Track;
use App\Models\User;
use App\Support\Shell\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $preview = false): User
    {
        return User::create(['username' => 'sh_' . Str::lower(Str::random(8)), 'password' => 'x', 'is_preview' => $preview]);
    }

    public function test_the_palette_lists_only_the_users_own_things(): void
    {
        $me = $this->user();
        $other = $this->user();
        DiaryEntry::create(['user_id' => $me->id, 'title' => 'Mine', 'content' => 'c']);
        DiaryEntry::create(['user_id' => $other->id, 'title' => 'Theirs', 'content' => 'c']);
        Track::create(['user_id' => $other->id, 'title' => 'Their song', 'path' => 'tracks/x.mp3']);

        $json = $this->actingAs($me)->getJson(route('palette'))->assertOk()->json();

        $labels = collect($json['items'])->pluck('label');
        $this->assertTrue($labels->contains('Mine'));
        $this->assertFalse($labels->contains('Theirs'));
        $this->assertFalse($labels->contains('Their song'));
        // Pages come from the tracked navigation list and nothing else.
        $this->assertSame(collect(Navigation::pages())->pluck('url')->all(), collect($json['pages'])->pluck('url')->all());
    }

    public function test_on_this_day_names_open_entries_and_only_admits_sealed_ones_exist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 21:00'));
        $me = $this->user();
        DiaryEntry::create(['user_id' => $me->id, 'title' => 'Open last year', 'content' => 'Visible words', 'event_date' => '2025-10-23']);
        DiaryEntry::create(['user_id' => $me->id, 'title' => 'Hidden title', 'content' => 'cipher', 'event_date' => '2024-10-23', 'key_hash' => 'x', 'algorithm_hash' => 'y']);
        DiaryEntry::create(['user_id' => $me->id, 'title' => 'Other day', 'content' => 'c', 'event_date' => '2025-10-22']);

        $response = $this->actingAs($me)->get(route('homepage'))
            ->assertOk()
            ->assertSee('Open last year')->assertSee('Visible words')
            ->assertSee('A sealed entry')->assertDontSee('Hidden title');

        $this->assertEqualsCanonicalizing(['Open last year', 'Hidden title'], $response->viewData('onThisDay')['entries']->pluck('title')->all());
    }

    public function test_guest_gets_no_write_controls_on_home_or_diary(): void
    {
        $guest = $this->user(preview: true);

        $this->actingAs($guest)->get(route('homepage'))->assertOk()->assertDontSee('Close the day');
        $this->actingAs($guest)->get(route('diary'))->assertOk()->assertDontSee('data-dy-flow', false)->assertDontSee('data-dy-write', false);
    }
}
