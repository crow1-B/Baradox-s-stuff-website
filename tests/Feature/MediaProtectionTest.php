<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\DiaryEntry;
use App\Models\Photo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAlbum;
use App\Support\VideoProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

// The gated contract: a gated or encrypted photo, or a gated video, stays locked inside the
// site until its key is given. Its cover is obscured by the server (CSS only adds to that),
// nothing renders a URL that would serve it plain, and nothing deletes, re-keys or re-registers
// it without the key.
//
// Every picture here is a high-contrast checkerboard, so "recognisable" is measurable: a plain
// thumbnail has strong edges between neighbouring pixels, an obscured one next to none.
class MediaProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'right-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('ssd_photos');
        Storage::fake('public');
    }

    private function user(bool $preview = false): User
    {
        return User::create([
            'username' => 'mp_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => $preview,
        ]);
    }

    private function checkerboard(): string
    {
        $img = imagecreatetruecolor(1200, 900);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        for ($y = 0; $y < 900; $y += 40) {
            for ($x = 0; $x < 1200; $x += 40) {
                imagefilledrectangle($img, $x, $y, $x + 39, $y + 39, (($x + $y) / 40) % 2 ? $white : $black);
            }
        }

        ob_start();
        imagejpeg($img, null, 92);

        return ob_get_clean();
    }

    private function photo(User $user, string $protection, array $extra = []): Photo
    {
        $bytes = $this->checkerboard();

        if ($protection === 'encrypted') {
            $bytes = openssl_encrypt($bytes, 'aes-256-cbc', self::KEY, 0, substr(hash('sha256', self::KEY), 0, 16));
        }

        $path = 'photos/' . Str::random(20) . '.jpg';
        Storage::disk('ssd_photos')->put($path, $bytes);

        $photo = Photo::create([
            'user_id' => $user->id,
            'path' => $path,
            'protection' => $protection,
            'key_hash' => $protection === 'none' ? null : Hash::make(self::KEY),
        ]);

        if ($extra) {
            $photo->forceFill($extra)->save();
        }

        return $photo;
    }

    private function video(User $user, string $protection, string $filename = 'clip.mp4'): Video
    {
        return Video::create([
            'user_id' => $user->id,
            'title' => 'Clip ' . Str::random(4),
            'filename' => $filename,
            'slug' => 'clip-' . Str::lower(Str::random(6)),
            'protection' => $protection,
            'key_hash' => $protection === 'none' ? null : Hash::make(self::KEY),
        ]);
    }

    // A short clip of the checkerboard, for the poster tests. ffmpeg comes with the image (§2).
    private function realClip(string $name = 'clip.mp4'): void
    {
        if (! VideoProbe::available()) {
            $this->markTestSkipped('ffmpeg is not available.');
        }

        $out = Storage::disk('ssd_photos')->path('videos/' . $name);
        @mkdir(dirname($out), 0777, true);
        $board = Storage::disk('ssd_photos')->path('board.jpg');
        file_put_contents($board, $this->checkerboard());

        (new Process(['ffmpeg', '-loglevel', 'error', '-y', '-loop', '1', '-i', $board, '-t', '2', '-r', '5',
            '-vf', 'scale=640:480', '-pix_fmt', 'yuv420p', $out]))->mustRun();
        unlink($board);
    }

    // Mean difference between horizontally neighbouring pixels, in grey levels.
    private function edgeEnergy(string $bytes): float
    {
        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img, 'not an image');

        $w = imagesx($img);
        $h = imagesy($img);
        $sum = 0;
        $n = 0;

        for ($y = 0; $y < $h; $y += 2) {
            $prev = null;
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $grey = ((($c >> 16) & 255) + (($c >> 8) & 255) + ($c & 255)) / 3;
                if ($prev !== null) {
                    $sum += abs($grey - $prev);
                    $n++;
                }
                $prev = $grey;
            }
        }

        return $sum / max(1, $n);
    }

    private function body($response): string
    {
        $response->assertOk();

        return $response instanceof \Illuminate\Testing\TestResponse && $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($response->baseResponse->getFile()->getPathname())
            : $response->getContent();
    }

    private function assertObscured($response, string $what): void
    {
        $energy = $this->edgeEnergy($this->body($response));
        $this->assertLessThan(4, $energy, "$what is recognisable (edge energy $energy)");
    }

    private function assertPadlock($response, string $what): void
    {
        $response->assertOk();
        $this->assertStringStartsWith('image/svg+xml', $response->headers->get('Content-Type'), "$what is not the padlock");
    }

    // Every media URL a page hands the browser, with the JSON slashes and entities undone.
    private function mediaUrls(string $html): array
    {
        $html = html_entity_decode(str_replace('\/', '/', $html));
        preg_match_all('~/(photos|videos)/(\d+)/(thumbnail|view|poster|stream)(\?[^"\'\s<>]*)?~', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['kind' => $x[1], 'id' => (int) $x[2], 'what' => $x[3], 'url' => $x[0]], $m);
    }

    // The heart of it: whatever a page links for a protected item, following that link
    // without a key gives nothing recognisable.
    private function assertPageHidesProtected(string $html, array $protectedPhotos, array $gatedVideos, string $page): int
    {
        $checked = 0;

        foreach ($this->mediaUrls($html) as $link) {
            if ($link['kind'] === 'photos' && isset($protectedPhotos[$link['id']])) {
                $photo = $protectedPhotos[$link['id']];
                $res = $this->get($link['url']);

                if ($link['what'] === 'view') {
                    $res->assertForbidden();
                } elseif ($photo->protection === 'encrypted') {
                    $this->assertPadlock($res, "$page: encrypted photo {$photo->id}");
                } else {
                    $this->assertObscured($res, "$page: gated photo {$photo->id}");
                }
                $checked++;
            }

            if ($link['kind'] === 'videos' && isset($gatedVideos[$link['id']])) {
                $res = $this->get($link['url']);

                if ($link['what'] === 'stream') {
                    $res->assertForbidden();
                } else {
                    $this->assertObscured($res, "$page: gated video {$link['id']}");
                }
                $checked++;
            }
        }

        return $checked;
    }

    private function seedLibrary(User $user): array
    {
        $this->realClip();

        $plain = $this->photo($user, 'none');
        $gated = $this->photo($user, 'gated');
        $encrypted = $this->photo($user, 'encrypted');
        // On this day, a year ago: Home must leave the protected ones out entirely.
        $yearAgo = Carbon::now('Asia/Baghdad')->subYear();
        $oldGated = $this->photo($user, 'gated', ['created_at' => $yearAgo]);
        $oldEncrypted = $this->photo($user, 'encrypted', ['created_at' => $yearAgo]);

        $album = Album::create(['user_id' => $user->id, 'name' => 'Locked', 'is_system' => false]);
        $album->photos()->attach([$gated->id, $encrypted->id]);
        $mixed = Album::create(['user_id' => $user->id, 'name' => 'Mixed', 'is_system' => false]);
        $mixed->photos()->attach([$plain->id, $gated->id, $encrypted->id]);

        $vGated = $this->video($user, 'gated');
        $vPlain = $this->video($user, 'none');
        $vAlbum = VideoAlbum::create(['user_id' => $user->id, 'name' => 'Clips', 'is_system' => false]);
        $vAlbum->videos()->attach([$vGated->id, $vPlain->id]);

        $entry = DiaryEntry::create(['user_id' => $user->id, 'title' => 'Day', 'content' => 'Words']);
        $entry->photos()->attach([$gated->id, $encrypted->id, $plain->id]);

        return [
            'protected' => collect([$gated, $encrypted, $oldGated, $oldEncrypted])->keyBy('id')->all(),
            'gatedVideos' => [$vGated->id => $vGated],
            'pages' => [
                'photos' => '/photos',
                'album' => '/albums/' . $album->id,
                'mixed album' => '/albums/' . $mixed->id,
                'home' => '/homepage',
                'diary' => '/diary',
                'diary entry' => '/diary/' . $entry->id,
                'palette' => '/palette',
                'videos' => '/videos',
                'video album' => '/video-albums/' . $vAlbum->id,
                'calendar' => '/calendar',
            ],
            'gated' => $gated,
            'encrypted' => $encrypted,
            'plain' => $plain,
        ];
    }

    private function walkPages(User $user): void
    {
        $lib = $this->seedLibrary($user);
        $this->actingAs($user);

        $checked = 0;
        foreach ($lib['pages'] as $name => $path) {
            $checked += $this->assertPageHidesProtected($this->get($path)->assertOk()->getContent(), $lib['protected'], $lib['gatedVideos'], $name);
        }

        // Not vacuous: the photo grids, the diary flow and the video cards do link them.
        $this->assertGreaterThan(8, $checked);
    }

    public function test_no_page_hands_the_owner_a_readable_protected_cover(): void
    {
        $this->walkPages($this->user());
    }

    public function test_no_page_hands_guest_a_readable_protected_cover(): void
    {
        $this->walkPages($this->user(preview: true));
    }

    public function test_home_and_album_covers_leave_protected_items_out(): void
    {
        $user = $this->user();
        $lib = $this->seedLibrary($user);
        $this->actingAs($user);

        $home = $this->get('/homepage')->assertOk()->getContent();
        foreach ($lib['protected'] as $photo) {
            $this->assertStringNotContainsString('/photos/' . $photo->id . '/', str_replace('\/', '/', $home));
        }

        // The all-protected album has no cover; the mixed one shows the plain photo.
        $photos = $this->get('/photos')->assertOk()->getContent();
        $this->assertSame(1, substr_count($photos, 'class="ph-album__cover"'));
        $this->assertStringContainsString($lib['plain']->thumbnailUrl(), $photos);
    }

    public function test_the_plain_thumbnail_is_never_served_once_a_photo_is_gated(): void
    {
        $user = $this->user();
        $photo = $this->photo($user, 'none');
        $this->actingAs($user);

        $before = $photo->thumbnailUrl();
        $this->assertGreaterThan(8, $this->edgeEnergy($this->body($this->get($before))), 'the fixture should be recognisable');

        $this->travel(2)->seconds();
        $this->patch("/photos/{$photo->id}/lock", ['key' => self::KEY, 'mode' => 'gated'])->assertRedirect();
        $photo->refresh();

        // The URL changes, so a browser can't answer it from its cache...
        $this->assertNotSame($before, $photo->thumbnailUrl());
        // ...and the old URL, revalidated, gets the obscured picture too.
        $this->assertObscured($this->get($before), 'old URL after gating');
        $this->assertObscured($this->get($photo->thumbnailUrl()), 'new URL after gating');
        $this->assertStringContainsString('no-cache', $this->get($photo->thumbnailUrl())->headers->get('Cache-Control'));

        // The plain thumbnail made before the lock is gone from the drive.
        $this->assertFalse(Storage::disk('ssd_photos')->exists('thumbnails/' . basename($photo->path)));
    }

    public function test_a_plain_thumbnail_left_on_the_drive_is_not_served_for_a_locked_photo(): void
    {
        $user = $this->user();
        $gated = $this->photo($user, 'gated');
        $encrypted = $this->photo($user, 'encrypted');
        $this->actingAs($user);

        // What a request racing the lock could have left behind: the plain thumbnail.
        foreach ([$gated, $encrypted] as $photo) {
            Storage::disk('ssd_photos')->put('thumbnails/' . basename($photo->path), $this->checkerboard());
        }

        $this->assertObscured($this->get($gated->thumbnailUrl()), 'gated with a stale plain file');
        $this->assertPadlock($this->get($encrypted->thumbnailUrl()), 'encrypted with a stale plain file');
    }

    public function test_locking_an_encrypted_photo_leaves_no_plain_thumbnail_behind(): void
    {
        $user = $this->user();
        $photo = $this->photo($user, 'none');
        $this->actingAs($user);

        $this->get($photo->thumbnailUrl())->assertOk();
        $this->patch("/photos/{$photo->id}/lock", ['key' => self::KEY, 'mode' => 'encrypted'])->assertRedirect();

        $this->assertSame([], Storage::disk('ssd_photos')->files('thumbnails'));
        $this->assertPadlock($this->get($photo->refresh()->thumbnailUrl()), 'after encrypting');
    }

    public function test_the_plain_poster_is_never_served_once_a_video_is_gated(): void
    {
        $this->realClip();
        $user = $this->user();
        $video = $this->video($user, 'none');
        $this->actingAs($user);

        $before = $video->posterUrl();
        $this->assertGreaterThan(8, $this->edgeEnergy($this->body($this->get($before))), 'the fixture should be recognisable');

        $this->travel(2)->seconds();
        $this->patch("/videos/{$video->id}/lock", ['key' => self::KEY])->assertRedirect();
        $video->refresh();

        $this->assertNotSame($before, $video->posterUrl());
        $this->assertObscured($this->get($before), 'old poster URL after gating');
        $this->assertObscured($this->get($video->posterUrl()), 'new poster URL after gating');
        $this->assertFalse(Storage::disk('ssd_photos')->exists('video-posters/' . $video->id . '.jpg'));

        // A plain frame left behind under the plain name is still never served for it.
        Storage::disk('ssd_photos')->put('video-posters/' . $video->id . '.jpg', $this->checkerboard());
        $this->assertObscured($this->get($video->posterUrl()), 'gated with a stale plain poster');
    }

    public function test_protected_files_need_their_key(): void
    {
        $this->realClip();
        $user = $this->user();
        $gated = $this->photo($user, 'gated');
        $encrypted = $this->photo($user, 'encrypted');
        $video = $this->video($user, 'gated');
        $this->actingAs($user);

        foreach ([$gated, $encrypted] as $photo) {
            $this->get("/photos/{$photo->id}/view")->assertForbidden();
            $this->get("/photos/{$photo->id}/view?key=wrong-key-999")->assertForbidden();
            $this->get("/photos/{$photo->id}/view?key=" . self::KEY)->assertOk();
        }

        $this->get("/videos/{$video->id}/stream")->assertForbidden();
        $this->get("/videos/{$video->id}/stream?key=wrong-key-999")->assertForbidden();
        $this->get("/videos/{$video->id}/stream?key=" . self::KEY)->assertOk();
    }

    public function test_deleting_a_protected_photo_needs_its_key(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (['gated', 'encrypted'] as $protection) {
            $photo = $this->photo($user, $protection);

            foreach ([[], ['key' => ''], ['key' => 'wrong-key-999']] as $body) {
                $this->delete("/photos/{$photo->id}", $body)->assertRedirect()->assertSessionHasErrors('key');
                $this->assertModelExists($photo);
                $this->assertTrue(Storage::disk('ssd_photos')->exists($photo->path), "$protection file deleted without its key");
            }

            $this->delete("/photos/{$photo->id}", ['key' => self::KEY])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertModelMissing($photo);
            $this->assertFalse(Storage::disk('ssd_photos')->exists($photo->path));
        }

        // An unprotected photo still needs nothing.
        $plain = $this->photo($user, 'none');
        $this->delete("/photos/{$plain->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($plain);
    }

    public function test_bulk_delete_refuses_a_selection_with_a_protected_photo(): void
    {
        $user = $this->user();
        $plain = $this->photo($user, 'none');
        $gated = $this->photo($user, 'gated');
        $encrypted = $this->photo($user, 'encrypted');
        $this->actingAs($user);

        $this->delete('/photos/bulk', ['ids' => [$plain->id, $gated->id, $encrypted->id], 'key' => self::KEY])
            ->assertRedirect()->assertSessionHasErrors('ids');

        foreach ([$plain, $gated, $encrypted] as $photo) {
            $this->assertModelExists($photo);
            $this->assertTrue(Storage::disk('ssd_photos')->exists($photo->path));
        }

        $this->delete('/photos/bulk', ['ids' => [$plain->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($plain);
    }

    public function test_deleting_a_gated_video_needs_its_key(): void
    {
        $user = $this->user();
        $video = $this->video($user, 'gated');
        $this->actingAs($user);

        foreach ([[], ['key' => 'wrong-key-999']] as $body) {
            $this->delete("/videos/{$video->id}", $body)->assertRedirect()->assertSessionHasErrors('key');
            $this->assertModelExists($video);
        }

        $this->delete("/videos/{$video->id}", ['key' => self::KEY])->assertSessionHasNoErrors();
        $this->assertModelMissing($video);

        $plain = $this->video($user, 'none');
        $this->delete("/videos/{$plain->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($plain);
    }

    // Locking again would set a new key without knowing the old one, and unlocking with the
    // new key would then open it: a way around the key, for gated and encrypted alike.
    public function test_a_locked_photo_cannot_be_locked_again(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (['gated', 'encrypted'] as $protection) {
            $photo = $this->photo($user, $protection);
            $hash = $photo->key_hash;
            $bytes = Storage::disk('ssd_photos')->get($photo->path);

            foreach (['gated', 'encrypted'] as $mode) {
                $this->patch("/photos/{$photo->id}/lock", ['key' => 'another-key-456', 'mode' => $mode])
                    ->assertRedirect()->assertSessionHasErrors('key');

                $photo->refresh();
                $this->assertSame($protection, $photo->protection);
                $this->assertSame($hash, $photo->key_hash);
                $this->assertSame($bytes, Storage::disk('ssd_photos')->get($photo->path));
            }

            $this->patch("/photos/{$photo->id}/unlock", ['key' => 'another-key-456'])->assertSessionHasErrors('key');
            $this->assertSame($protection, $photo->refresh()->protection);
        }
    }

    public function test_a_gated_video_cannot_be_gated_again(): void
    {
        $user = $this->user();
        $video = $this->video($user, 'gated');
        $this->actingAs($user);

        $this->patch("/videos/{$video->id}/lock", ['key' => 'another-key-456'])->assertSessionHasErrors('key');
        $this->assertTrue(Hash::check(self::KEY, $video->refresh()->key_hash));

        $this->patch("/videos/{$video->id}/unlock", ['key' => 'another-key-456'])->assertSessionHasErrors('key');
        $this->assertSame('gated', $video->refresh()->protection);
    }

    // Two entries may share a file; an ungated twin would play what the gated one hides.
    public function test_a_gated_videos_file_cannot_be_registered_again(): void
    {
        $user = $this->user();
        Storage::disk('ssd_photos')->put('videos/clip.mp4', 'x');
        $this->video($user, 'gated', 'clip.mp4');
        $this->actingAs($user);

        foreach (['clip.mp4', 'CLIP.mp4'] as $name) {
            $this->post('/videos', ['title' => 'Again', 'filename' => $name])->assertSessionHasErrors('filename');
        }
        $this->assertSame(1, Video::count());
    }

    public function test_a_video_whose_file_an_ungated_entry_plays_cannot_be_gated(): void
    {
        $user = $this->user();
        $one = $this->video($user, 'none', 'clip.mp4');
        $this->video($user, 'none', 'clip.mp4');
        $this->actingAs($user);

        $this->patch("/videos/{$one->id}/lock", ['key' => self::KEY])->assertSessionHasErrors('key');
        $this->assertSame('none', $one->refresh()->protection);
    }

    public function test_unlocking_with_the_key_still_works(): void
    {
        $user = $this->user();
        $photo = $this->photo($user, 'encrypted');
        $video = $this->video($user, 'gated');
        $this->actingAs($user);

        $this->patch("/photos/{$photo->id}/unlock", ['key' => self::KEY])->assertSessionHasNoErrors();
        $this->assertSame('none', $photo->refresh()->protection);
        $this->assertSame($this->checkerboard(), Storage::disk('ssd_photos')->get($photo->path));

        $this->patch("/videos/{$video->id}/unlock", ['key' => self::KEY])->assertSessionHasNoErrors();
        $this->assertSame('none', $video->refresh()->protection);
    }

    public function test_someone_elses_media_is_refused(): void
    {
        $owner = $this->user();
        $photo = $this->photo($owner, 'gated');
        $video = $this->video($owner, 'gated');
        $this->actingAs($this->user());

        $this->get($photo->thumbnailUrl())->assertForbidden();
        $this->get($video->posterUrl())->assertForbidden();
        $this->delete("/photos/{$photo->id}", ['key' => self::KEY])->assertForbidden();
        $this->delete("/videos/{$video->id}", ['key' => self::KEY])->assertForbidden();
        $this->assertModelExists($photo);
        $this->assertModelExists($video);
    }
}
