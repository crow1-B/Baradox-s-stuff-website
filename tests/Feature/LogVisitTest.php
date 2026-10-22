<?php

namespace Tests\Feature;

use App\Http\Middleware\LogVisit;
use App\Models\Album;
use App\Models\User;
use App\Models\VideoAlbum;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ssd_photos');
    }

    private function makeUser(): User
    {
        return User::create([
            'username' => 'lv_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => false,
        ]);
    }

    public function test_every_logged_route_has_a_label(): void
    {
        $logged = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('log.visit', $route->gatherMiddleware(), true));

        $this->assertNotEmpty($logged);

        foreach ($logged as $route) {
            $name = $route->getName();

            $this->assertNotNull($name, "/{$route->uri()} carries log.visit but has no route name");
            $this->assertTrue(
                array_key_exists($name, LogVisit::LABELS) || in_array($name, LogVisit::SKIPPED, true),
                "Route '{$name}' carries log.visit but has no label in LogVisit::LABELS"
            );
        }
    }

    public function test_a_top_level_page_keeps_its_label(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/videos')->assertOk();

        $this->assertSame('Videos Page.', Visit::where('user_id', $user->id)->sole()->page_name);
    }

    public function test_a_nested_page_gets_a_human_label_not_its_path(): void
    {
        $user = $this->makeUser();
        $album = VideoAlbum::create(['user_id' => $user->id, 'name' => 'Karbala trip', 'is_system' => false]);

        $this->actingAs($user)->get("/video-albums/{$album->id}")->assertOk();

        $visit = Visit::where('user_id', $user->id)->sole();
        $this->assertSame('Video Album “Karbala trip” Page.', $visit->page_name);
        $this->assertSame("video-albums/{$album->id}", $visit->path);
    }

    public function test_someone_elses_album_name_never_reaches_the_history(): void
    {
        $owner = $this->makeUser();
        $visitor = $this->makeUser();
        $album = VideoAlbum::create(['user_id' => $owner->id, 'name' => 'Private name', 'is_system' => false]);

        $this->actingAs($visitor)->get("/video-albums/{$album->id}")->assertForbidden();

        $this->assertSame('Video Album Page.', Visit::where('user_id', $visitor->id)->sole()->page_name);
    }

    public function test_a_photo_album_is_recorded_with_its_name(): void
    {
        $user = $this->makeUser();
        $album = Album::create(['user_id' => $user->id, 'name' => 'Arbaeen', 'is_system' => false]);

        $this->actingAs($user)->get("/albums/{$album->id}")->assertOk();

        $this->assertSame('Photo Album “Arbaeen” Page.', Visit::where('user_id', $user->id)->sole()->page_name);
    }

    public function test_someone_elses_photo_album_name_never_reaches_the_history(): void
    {
        $owner = $this->makeUser();
        $visitor = $this->makeUser();
        $album = Album::create(['user_id' => $owner->id, 'name' => 'Private name', 'is_system' => false]);

        $this->actingAs($visitor)->get("/albums/{$album->id}")->assertForbidden();

        $this->assertSame('Photo Album Page.', Visit::where('user_id', $visitor->id)->sole()->page_name);
    }

    public function test_the_homepage_is_not_recorded(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/homepage')->assertOk();

        $this->assertSame(0, Visit::where('user_id', $user->id)->count());
    }
}
