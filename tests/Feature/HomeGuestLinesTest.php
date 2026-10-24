<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomeGuestLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(bool $preview): User
    {
        return User::create(['username' => 'hg_' . Str::lower(Str::random(8)), 'password' => 'irrelevant', 'is_preview' => $preview]);
    }

    public function test_guest_is_never_asked_to_write(): void
    {
        $guest = $this->user(true);

        // home.js re-picks the line by the browser's clock, so every line in the JSON counts, not only the one shown.
        $html = $this->actingAs($guest)->get('/homepage')->assertOk()->getContent();

        $this->assertStringNotContainsString('Write it down', $html);
        $this->assertStringNotContainsString('close the day', $html);
        $this->assertStringNotContainsString('Whatever you write today', $html);
        $this->assertStringContainsString('The house is asleep.', $html);
    }

    public function test_the_owner_keeps_the_invitation(): void
    {
        $html = $this->actingAs($this->user(false))->get('/homepage')->assertOk()->getContent();

        $this->assertStringContainsString('Write it down before it fades.', $html);
        $this->assertStringContainsString('A good hour to close the day.', $html);
    }
}
