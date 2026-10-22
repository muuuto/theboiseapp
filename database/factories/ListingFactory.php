<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateFrom = $this->faker->dateTimeBetween('-5 years', '-1 year');

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'tags' => 'mountains, friends',
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => (clone $dateFrom)->modify('+2 days')->format('Y-m-d'),
            'location' => $this->faker->city(),
            'albumLink' => $this->faker->url(),
            'description' => $this->faker->paragraph(),
            // Every album has a preview (the file itself isn't needed in tests)
            'logo' => 'logos/sample.webp',
        ];
    }
}
