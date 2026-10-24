<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SecretFieldTest extends TestCase
{
    public function test_the_login_password_has_a_hidden_toggle_and_keeps_its_autocomplete(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="password"[^>]*id="password"[^>]*autocomplete="current-password"/', $html);
        $this->assertMatchesRegularExpression('/<button type="button" class="nd-secret__toggle"[^>]*aria-label="Show password"[^>]*aria-pressed="false"[^>]*aria-controls="password"/s', $html);
    }

    public function test_every_attribute_reaches_the_input_and_mask_mode_stays_text(): void
    {
        $html = Blade::render('<x-nd-secret class="x" id="k" name="key" autocomplete="new-password" data-hook />');
        $this->assertStringContainsString('type="password"', $html);
        $this->assertMatchesRegularExpression('/class="nd-secret__input x"/', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('aria-label="Show key"', $html);

        $masked = Blade::render('<x-nd-secret mask what="password" id="p" autocomplete="off" />');
        $this->assertStringContainsString('type="text"', $masked);
        $this->assertStringContainsString('data-secret-mode="mask"', $masked);
        $this->assertStringNotContainsString('type="password"', $masked);
    }
}
