<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoAlbumCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('ssd_photos');
        Storage::disk('ssd_photos')->put('videos/on_the_drive.mp4', 'x');
    }

    private function video(int $userId, string $file, string $when): Video
    {
        $video = Video::create(['user_id' => $userId, 'title' => $file, 'description' => '', 'filename' => $file,
            'slug' => Str::slug($file) . '-' . Str::lower(Str::random(5)), 'protection' => 'none']);
        $video->created_at = $when;
        $video->save();

        return $video;
    }

    public function test_a_video_whose_file_is_gone_is_never_an_album_cover(): void
    {
        $user = User::create(['username' => 'vc_' . Str::lower(Str::random(8)), 'password' => 'x', 'is_preview' => false]);
        $album = VideoAlbum::create(['user_id' => $user->id, 'name' => 'Trip', 'is_system' => false]);
        $there = $this->video($user->id, 'on_the_drive.mp4', '2026-01-01 10:00:00');
        $gone = $this->video($user->id, 'gone.mp4', '2026-02-01 10:00:00');
        DB::table('video_album')->insert([['video_id' => $there->id, 'video_album_id' => $album->id], ['video_id' => $gone->id, 'video_album_id' => $album->id]]);

        // The newest is the missing one; the cover falls to the one still on the drive.
        $this->actingAs($user)->get('/videos')->assertOk()
            ->assertViewHas('albumCovers', fn ($covers) => $covers->get($album->id)?->id === $there->id);

        Storage::disk('ssd_photos')->delete('videos/on_the_drive.mp4');

        $this->actingAs($user)->get('/videos')->assertOk()
            ->assertViewHas('albumCovers', fn ($covers) => $covers->get($album->id) === null);
    }
}
