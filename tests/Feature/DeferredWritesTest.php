<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use App\Support\Http\DeferredWrites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeferredWritesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'username' => 'dw_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => false,
        ]);
    }

    public function test_a_visit_is_queued_during_the_request_and_written_after_it(): void
    {
        $user = $this->makeUser();
        $atHandled = null;

        // RequestHandled fires once the response is built, before the kernel terminates.
        Event::listen(RequestHandled::class, function () use ($user, &$atHandled) {
            $atHandled = Visit::where('user_id', $user->id)->count();
        });

        $this->actingAs($user)->get('/diary')->assertOk();

        $this->assertSame(0, $atHandled);
        $this->assertSame(1, Visit::where('user_id', $user->id)->where('path', 'diary')->count());
        $this->assertFalse(app(DeferredWrites::class)->pending());
    }

    public function test_a_response_with_queued_writes_says_how_long_it_is(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/diary');

        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
    }

    public function test_a_failing_table_is_reported_and_does_not_stop_the_others(): void
    {
        $user = $this->makeUser();
        Exceptions::fake();

        $writes = new DeferredWrites();
        $writes->insert('no_such_table', ['x' => 1]);
        $writes->insert('visits', ['user_id' => $user->id, 'page_name' => 'Diary Page.', 'path' => 'diary', 'created_at' => now(), 'updated_at' => now()]);
        $writes->flush();

        $this->assertSame(1, Visit::where('user_id', $user->id)->count());
        $this->assertFalse($writes->pending());
        Exceptions::assertReported(\Illuminate\Database\QueryException::class);
    }
}
