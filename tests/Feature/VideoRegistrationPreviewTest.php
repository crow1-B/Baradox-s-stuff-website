<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoRegistrationPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ssd_photos');
        Storage::disk('ssd_photos')->put('videos/real_holiday.mp4', 'not really a video');
    }

    private function makeUser(bool $preview): User
    {
        return User::create([
            'username' => 'vr_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => $preview,
        ]);
    }

    public function test_the_owner_sees_unregistered_files(): void
    {
        $this->actingAs($this->makeUser(false))
            ->get('/videos')
            ->assertOk()
            ->assertViewHas('available', fn ($files) => array_column($files, 'name') === ['real_holiday.mp4'])
            ->assertSee('real_holiday.mp4');
    }

    public function test_guest_sees_no_filenames_on_the_videos_page(): void
    {
        $this->actingAs($this->makeUser(true))
            ->get('/videos')
            ->assertOk()
            ->assertViewHas('available', [])
            ->assertDontSee('real_holiday.mp4');
    }

    public function test_guest_sees_no_filenames_on_an_album_page(): void
    {
        $guest = $this->makeUser(true);
        $album = VideoAlbum::create(['user_id' => $guest->id, 'name' => 'Mine', 'is_system' => false]);

        $this->actingAs($guest)
            ->get("/video-albums/{$album->id}")
            ->assertOk()
            ->assertViewHas('available', [])
            ->assertDontSee('real_holiday.mp4');
    }

    public function test_guest_cannot_register_or_probe_a_file(): void
    {
        $guest = $this->makeUser(true);

        // Same answer whether the file exists or not, so it can't be used to probe the drive.
        foreach (['real_holiday.mp4', 'does_not_exist.mp4'] as $filename) {
            $this->actingAs($guest)
                ->post('/videos', ['title' => 'x', 'filename' => $filename])
                ->assertForbidden();
        }

        $this->assertSame(0, Video::where('user_id', $guest->id)->count());
    }
}
