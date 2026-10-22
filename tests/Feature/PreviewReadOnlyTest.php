<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\DiaryEntry;
use App\Models\ExtraNote;
use App\Models\FutureUpdate;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Track;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAlbum;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreviewReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    // Login and the one-click Guest button are how you arrive, logout is how you leave.
    private const NOT_WRITES = ['login', 'login.guest', 'logout'];

    // Route middleware that answers on its own terms (e.g. a 404 that hides a page) may
    // refuse before the read-only check does; either way nothing may get through.
    private const PLAIN_MIDDLEWARE = ['web', 'auth', 'log.visit'];

    private const TABLES = ['albums', 'video_albums', 'diary_entries', 'extra_notes', 'future_updates',
        'photos', 'projects', 'tracks', 'videos', 'visits', 'album_photo', 'video_album', 'people', 'languages'];

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ssd_photos');
        Storage::fake('public');

        $this->guest = User::create([
            'username' => 'pr_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => true,
        ]);
    }

    // One record of every bound model, owned by the guest itself: without the read-only
    // check the controllers' ownership tests would let every one of these requests through.
    private function seedGuestRecords(): array
    {
        $id = $this->guest->id;

        return [
            'album' => Album::create(['user_id' => $id, 'name' => 'A', 'is_system' => false])->id,
            'video-album' => VideoAlbum::create(['user_id' => $id, 'name' => 'V', 'is_system' => false])->id,
            'entry' => DiaryEntry::create(['user_id' => $id, 'title' => 'T', 'content' => 'C'])->id,
            'note' => ExtraNote::create(['user_id' => $id, 'content' => 'N'])->id,
            'update' => FutureUpdate::create(['user_id' => $id, 'content' => 'U'])->id,
            'photo' => Photo::create(['user_id' => $id, 'path' => 'photos/x.jpg', 'protection' => 'none'])->id,
            'project' => Project::create(['user_id' => $id, 'title' => 'P', 'description' => 'D', 'status' => 'future', 'start_date' => '2026-01-01'])->id,
            'track' => Track::create(['user_id' => $id, 'title' => 'T', 'path' => 'tracks/x.mp3'])->id,
            'video' => Video::create(['user_id' => $id, 'title' => 'V', 'filename' => 'x.mp4', 'slug' => 'v-abcde', 'protection' => 'none'])->id,
            'visit' => Visit::create(['user_id' => $id, 'page_name' => 'Diary Page.', 'path' => 'diary'])->id,
        ];
    }

    private function counts(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    public function test_every_write_route_refuses_the_guest(): void
    {
        $ids = $this->seedGuestRecords();
        $before = $this->counts();
        $walked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $writes = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);

            // Allowlisted routes answer the guest themselves; PreviewHandledRoutesTest covers them.
            if ($writes === [] || in_array($route->getName(), self::NOT_WRITES, true)
                || in_array($route->getName(), config('shell.preview_handled', []), true)
                || ! in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }

            $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($route, $ids) {
                $key = $m[1] === 'album' && str_starts_with($route->uri(), 'video-albums') ? 'video-album' : $m[1];

                return $ids[$key] ?? '0';
            }, $route->uri());

            foreach ($writes as $method) {
                $response = $this->actingAs($this->guest)->call($method, '/' . $uri, ['title' => 'x', 'content' => 'x']);
                $label = "{$method} /{$route->uri()}";

                if (array_diff($route->gatherMiddleware(), self::PLAIN_MIDDLEWARE) === []) {
                    $this->assertSame(403, $response->status(), "{$label} should be refused with 403");
                } else {
                    $this->assertContains($response->status(), [403, 404], "{$label} let the guest through");
                }

                $walked++;
            }
        }

        $this->assertGreaterThan(40, $walked);
        $this->assertSame($before, $this->counts());
        $this->assertSame([], Storage::disk('ssd_photos')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_guest_can_still_sign_out(): void
    {
        $this->actingAs($this->guest)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_an_ordinary_account_is_not_affected(): void
    {
        $owner = User::create(['username' => 'pr_owner', 'password' => 'irrelevant', 'is_preview' => false]);

        $this->actingAs($owner)->post('/extra_notes', ['content' => 'still works']);

        $this->assertSame(1, ExtraNote::where('user_id', $owner->id)->count());
    }

    public function test_browsing_as_the_guest_writes_nothing(): void
    {
        $before = $this->counts();

        foreach (['/homepage', '/music', '/photos', '/videos', '/diary', '/extra_notes', '/projects', '/future_updates', '/visits'] as $page) {
            $this->actingAs($this->guest)->get($page)->assertOk();
        }

        $this->assertSame($before, $this->counts());
    }
}
