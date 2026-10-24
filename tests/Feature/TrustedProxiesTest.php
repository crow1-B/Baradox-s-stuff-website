<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// config/trustedproxy.php: X-Forwarded-For only counts from a configured proxy, and "none" stays
// none even when the Host header names a hosting provider the middleware would otherwise trust.
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/__ip', fn (Request $request) => $request->ip());
    }

    public function test_a_spoofed_forwarded_for_is_ignored_by_default(): void
    {
        $this->assertSame([], config('trustedproxy.proxies'));

        $this->get('/__ip', ['X-Forwarded-For' => '6.6.6.6'])->assertSeeText('127.0.0.1');
        $this->get('/__ip', ['X-Forwarded-For' => '6.6.6.6', 'Host' => 'evil.on-forge.com'])->assertSeeText('127.0.0.1');
    }

    public function test_a_configured_proxy_sets_the_client_ip(): void
    {
        config(['trustedproxy.proxies' => ['127.0.0.1']]);

        $this->get('/__ip', ['X-Forwarded-For' => '203.0.113.9'])->assertSeeText('203.0.113.9');
    }
}
