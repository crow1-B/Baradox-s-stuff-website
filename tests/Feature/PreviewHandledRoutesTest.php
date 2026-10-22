<?php

namespace Tests\Feature;

use App\Http\Middleware\RejectPreviewWrites;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// config('shell.preview_handled') lets named routes past RejectPreviewWrites because they
// refuse the preview account themselves. These tests hold every name on it to that.
class PreviewHandledRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const READS = ['GET', 'HEAD', 'OPTIONS'];

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without the dev server or a build.
        $this->withoutVite();

        Storage::fake('ssd_photos');
        Storage::fake('public');

        $this->guest = User::create([
            'username' => 'ph_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => true,
        ]);
    }

    private function counts(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])
            ->all();
    }

    public function test_every_listed_name_is_a_write_route(): void
    {
        $listed = config('shell.preview_handled', []);

        $this->assertIsArray($listed);

        foreach ($listed as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "'{$name}' is allowlisted but no route has that name");
            $this->assertContains('web', $route->gatherMiddleware(), "'{$name}' is not in the web group");
            $this->assertNotEmpty(array_diff($route->methods(), self::READS), "'{$name}' is not a write route");
        }
    }

    public function test_every_listed_route_rejects_or_does_nothing_for_the_guest_on_its_own(): void
    {
        foreach (config('shell.preview_handled', []) as $name) {
            $route = Route::getRoutes()->getByName($name);
            $uri = '/' . preg_replace('/\{\w+\??\}/', '0', $route->uri());

            foreach (array_diff($route->methods(), self::READS) as $method) {
                $before = $this->counts();

                $response = $this->actingAs($this->guest)->json($method, $uri);
                $label = "{$method} {$uri} ({$name})";

                $message = json_decode($response->getContent(), true)['message'] ?? null;

                $this->assertNotSame(RejectPreviewWrites::MESSAGE, $message, "{$label} was refused by the middleware, not by itself");
                $this->assertTrue(
                    $response->status() === 204 || $response->isClientError(),
                    "{$label} answered {$response->status()}; expected 204 or a 4xx"
                );
                $this->assertSame($before, $this->counts(), "{$label} wrote to the database");
                $this->assertSame([], Storage::disk('ssd_photos')->allFiles());
                $this->assertSame([], Storage::disk('public')->allFiles());
            }
        }
    }

    public function test_the_list_is_what_lets_a_route_through(): void
    {
        Route::post('/preview-handled-probe', fn () => response()->noContent())
            ->middleware(['web', 'auth'])
            ->name('test.preview-handled-probe');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->guest)->post('/preview-handled-probe')->assertForbidden();

        config()->push('shell.preview_handled', 'test.preview-handled-probe');

        $this->actingAs($this->guest)->post('/preview-handled-probe')->assertNoContent();
    }
}
