<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Panels now load a Vite theme; tests never need the built assets. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
