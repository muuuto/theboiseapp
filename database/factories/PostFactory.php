<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'tags' => 'general',
            'content' => $this->faker->paragraphs(2, true),
            'counter' => 0,
        ];
    }

    /**
     * Indicate that the post is visible only to its author.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => 1,
        ]);
    }
}
