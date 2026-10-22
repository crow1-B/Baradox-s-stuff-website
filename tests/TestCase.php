<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without the dev server or a build: @vite would otherwise throw
        // "Vite manifest not found" whenever public/hot is absent.
        $this->withoutVite();
    }
}
