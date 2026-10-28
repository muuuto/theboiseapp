<?php

namespace Tests\Feature;

use App\Models\GymWorkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// When a workout started and ended: set by "New workout" (and the page's Done), editable
class GymTimesTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_and_end_times(): void
    {
        $this->travelTo('2026-10-28 18:32:10');
        $me = User::factory()->create(['can_use_gym' => true]);

        $this->actingAs($me)->post('/gym/workouts')->assertRedirect();
        $workout = GymWorkout::sole();
        $this->assertSame('18:32', substr($workout->start_time, 0, 5));
        $this->actingAs($me)->get("/gym/workouts/{$workout->id}")->assertInertia(fn ($page) => $page
            ->where('workout.startTime', '18:32')
            ->where('workout.endTime', ''));

        // Done at 19:41 (saved with the rest), or corrected by hand
        $this->actingAs($me)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-28', 'start_time' => '18:30', 'end_time' => '19:41'])->assertOk();
        $this->assertSame(71, $workout->fresh()->minutes());
        $this->actingAs($me)->get('/gym')->assertInertia(fn ($page) => $page->where('workouts.data.0.minutes', 71));
        $this->actingAs($me)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-28', 'start_time' => '25:99'])->assertJsonValidationErrors('start_time');

        // Past midnight still counts
        $workout->update(['start_time' => '23:30', 'end_time' => '00:15']);
        $this->assertSame(45, $workout->fresh()->minutes());
    }
}
