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

        // Uploaded photos never land in the real storage folder
        \Illuminate\Support\Facades\Storage::fake('public');

        // Never the real internet (exchange rates, altitudes): each test says what the services
        // answer in $fakeHttp (a piece of the address => the JSON); anything else is "unavailable"
        \Illuminate\Support\Facades\Http::fake(function ($request) {
            foreach ($this->fakeHttp as $address => $json) {
                if (str_contains($request->url(), $address)) {
                    return \Illuminate\Support\Facades\Http::response(is_callable($json) ? $json($request) : $json);
                }
            }

            return \Illuminate\Support\Facades\Http::response([], 503);
        });
    }

    protected array $fakeHttp = [];

    // A small photo to upload, e.g. an album's cover (required)
    protected function fakeCover(): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->image('cover.jpg', 400, 300);
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
