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

    // In an album from two months ago: "active", so the automatic notifications reach them
    protected function makeActive(\App\Models\User ...$users): void
    {
        \App\Models\Listing::factory()->create([
            'dateFrom' => now()->subMonths(2)->toDateString(),
            'dateTo' => now()->subMonths(2)->addDay()->toDateString(),
        ])->users()->attach(collect($users)->pluck('id'), ['seen' => true]);
    }
}
