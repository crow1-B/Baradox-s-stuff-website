<?php

namespace Tests\Feature\Diary;

use App\Models\DiaryEntry;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiaryEntryPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'de_' . Str::lower(Str::random(8)), 'password' => 'irrelevant', 'is_preview' => false]);
    }

    private function entry(array $attributes = []): DiaryEntry
    {
        return DiaryEntry::create($attributes + ['user_id' => $this->user->id, 'title' => 'Title', 'content' => 'Plain words here.']);
    }

    private function seal(DiaryEntry $entry, string $algorithm, string $key = 'a-long-enough-key'): void
    {
        $this->actingAs($this->user)->patch("/diary/{$entry->id}/lock", ['algorithm' => $algorithm, 'key' => $key])
            ->assertSessionHasNoErrors();
    }

    public function test_the_owner_reads_an_entry_with_its_mood_and_song(): void
    {
        $track = Track::create(['user_id' => $this->user->id, 'title' => 'The Song', 'path' => 'tracks/x.mp3']);
        $entry = $this->entry(['mood' => 'radiant', 'track_id' => $track->id]);

        $this->actingAs($this->user)->get(route('diary.entry', $entry))
            ->assertOk()->assertSee('Plain words here.')->assertSee('Radiant')->assertSee('The Song');
    }

    public function test_someone_elses_entry_is_forbidden(): void
    {
        $other = User::create(['username' => 'x_' . Str::random(6), 'password' => 'x', 'is_preview' => false]);

        $this->actingAs($other)->get(route('diary.entry', $this->entry()))->assertForbidden();
    }

    public function test_a_sealed_entry_shows_nothing_but_its_title_and_day(): void
    {
        $track = Track::create(['user_id' => $this->user->id, 'title' => 'Secret Song', 'path' => 'tracks/x.mp3']);
        $entry = $this->entry(['mood' => 'stormy', 'track_id' => $track->id]);
        $this->seal($entry, 'aes');

        $this->actingAs($this->user)->get(route('diary.entry', $entry))
            ->assertOk()->assertSee('Title')->assertDontSee('Plain words here.')->assertDontSee('Stormy')->assertDontSee('Secret Song');
    }

    public function test_every_cipher_still_round_trips_through_seal_reveal_and_unseal(): void
    {
        foreach (['aes', 'chacha20', 'vigenere'] as $algorithm) {
            $entry = $this->entry(['content' => "يا نجمة\nline two ({$algorithm})"]);
            $this->seal($entry, $algorithm);
            $this->assertNotSame("يا نجمة\nline two ({$algorithm})", $entry->fresh()->content);

            $this->actingAs($this->user)->post(route('diary.reveal', $entry), ['algorithm' => $algorithm, 'key' => 'wrong-key-here'])
                ->assertSessionHasErrors('key');
            $this->actingAs($this->user)->post(route('diary.reveal', $entry), ['algorithm' => $algorithm, 'key' => 'a-long-enough-key'])
                ->assertOk()->assertSee('line two (' . $algorithm . ')');

            $this->actingAs($this->user)->patch("/diary/{$entry->id}/unlock", ['algorithm' => $algorithm, 'key' => 'a-long-enough-key'])
                ->assertSessionHas('dy_unsealed', $entry->id);
            $this->assertSame("يا نجمة\nline two ({$algorithm})", $entry->fresh()->content);
        }
    }

    public function test_neighbours_follow_the_diary_order(): void
    {
        $old = $this->entry(['title' => 'Old', 'event_date' => '2025-01-01']);
        $mid = $this->entry(['title' => 'Mid', 'event_date' => '2025-06-01']);
        $new = $this->entry(['title' => 'New', 'event_date' => '2026-01-01']);

        $this->actingAs($this->user)->get(route('diary.entry', $mid))
            ->assertSee('href="' . route('diary.entry', $old) . '"', false)
            ->assertSee('href="' . route('diary.entry', $new) . '"', false);
    }

    public function test_deleting_goes_back_to_the_list(): void
    {
        $entry = $this->entry();

        $this->actingAs($this->user)->from(route('diary.entry', $entry))->delete('/diary/' . $entry->id)
            ->assertRedirect(route('diary'));
        $this->assertNull($entry->fresh());
    }
}
