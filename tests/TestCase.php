<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages must render without the built frontend (public/build)
        $this->withoutVite();

        // Site switches are kept in memory for the request: each test starts from the database
        \App\Support\Settings::forget();
    }
}
