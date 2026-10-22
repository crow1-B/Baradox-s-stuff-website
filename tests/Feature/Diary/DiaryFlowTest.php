<?php

namespace Tests\Feature\Diary;

use App\Models\DiaryEntry;
use App\Models\Photo;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiaryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ssd_photos');
        $this->user = $this->makeUser();
    }

    private function makeUser(): User
    {
        return User::create(['username' => 'df_' . Str::lower(Str::random(8)), 'password' => 'irrelevant', 'is_preview' => false]);
    }

    private function photo(User $owner): Photo
    {
        return Photo::create(['user_id' => $owner->id, 'path' => 'photos/x.jpg', 'protection' => 'none']);
    }

    private function track(User $owner): Track
    {
        return Track::create(['user_id' => $owner->id, 'title' => 'Song', 'path' => 'tracks/x.mp3']);
    }

    public function test_closing_the_day_saves_mood_song_photos_and_lands_on_the_entry(): void
    {
        $photo = $this->photo($this->user);
        $track = $this->track($this->user);

        $response = $this->actingAs($this->user)->post('/diary', [
            'title' => 'A day',
            'content' => 'Words.',
            'event_date' => '2026-10-23',
            'mood' => 'calm',
            'track_id' => $track->id,
            'photo_ids' => [$photo->id],
            'photos' => [UploadedFile::fake()->image('new.jpg')],
            'people' => 'Ali, Hussein',
        ]);

        $entry = DiaryEntry::where('user_id', $this->user->id)->sole();
        $response->assertRedirect(route('diary.entry', $entry))->assertSessionHas('dy_saved', $entry->id);

        $this->assertSame('calm', $entry->mood);
        $this->assertSame($track->id, $entry->track_id);
        $this->assertCount(2, $entry->photos);
        $this->assertTrue($entry->photos->contains($photo));
        $this->assertSame(['Ali', 'Hussein'], $entry->people->pluck('name')->sort()->values()->all());
        Storage::disk('ssd_photos')->assertExists($entry->photos->firstWhere('id', '!=', $photo->id)->path);
    }

    public function test_an_unknown_mood_is_refused(): void
    {
        $this->actingAs($this->user)->post('/diary', ['title' => 'T', 'content' => 'C', 'mood' => 'ecstatic'])
            ->assertSessionHasErrors('mood');

        $this->assertSame(0, DiaryEntry::count());
    }

    public function test_someone_elses_photo_or_song_is_an_error_and_nothing_is_written(): void
    {
        $other = $this->makeUser();

        $this->actingAs($this->user)->post('/diary', ['title' => 'T', 'content' => 'C', 'photo_ids' => [$this->photo($other)->id]])
            ->assertSessionHasErrors('photo_ids');
        $this->actingAs($this->user)->post('/diary', ['title' => 'T', 'content' => 'C', 'track_id' => $this->track($other)->id])
            ->assertSessionHasErrors('track_id');

        $this->assertSame(0, DiaryEntry::count());
    }

    public function test_an_edit_replaces_the_photo_set_and_can_clear_mood_and_song(): void
    {
        [$keep, $drop, $add] = [$this->photo($this->user), $this->photo($this->user), $this->photo($this->user)];
        $entry = DiaryEntry::create(['user_id' => $this->user->id, 'title' => 'T', 'content' => 'C', 'mood' => 'low', 'track_id' => $this->track($this->user)->id]);
        $entry->photos()->sync([$keep->id, $drop->id]);

        $this->actingAs($this->user)->patch('/diary/' . $entry->id, [
            'title' => 'T2', 'content' => 'C2', 'mood' => '', 'track_id' => '', 'photo_ids' => [$keep->id, $add->id],
        ])->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertNull($entry->mood);
        $this->assertNull($entry->track_id);
        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $entry->photos->pluck('id')->all());
        $this->assertNotNull(Photo::find($drop->id), 'detaching never deletes the photo');
    }

    public function test_deleting_a_photo_detaches_it_and_keeps_the_entry(): void
    {
        $photo = $this->photo($this->user);
        $entry = DiaryEntry::create(['user_id' => $this->user->id, 'title' => 'T', 'content' => 'C']);
        $entry->photos()->sync([$photo->id]);

        $photo->delete();

        $this->assertNotNull($entry->fresh());
        $this->assertCount(0, $entry->fresh()->photos);
    }

    public function test_deleting_a_track_keeps_the_entry(): void
    {
        $track = $this->track($this->user);
        $entry = DiaryEntry::create(['user_id' => $this->user->id, 'title' => 'T', 'content' => 'C', 'track_id' => $track->id]);

        $track->delete();

        $this->assertNull($entry->fresh()->track_id);
    }
}
