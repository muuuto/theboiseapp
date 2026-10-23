<?php

namespace Tests\Feature;

use App\Models\GymExercise;
use App\Models\GymSet;
use App\Models\GymWorkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// Gym tracker: only for the people the admins allow, each person sees only their own workouts,
// records shared only by choice
class GymTest extends TestCase
{
    use RefreshDatabase;

    private User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anna = User::factory()->create(['can_use_gym' => true]);
    }

    private function exercise(string $name): GymExercise
    {
        return GymExercise::whereNull('user_id')->where('name', $name)->firstOrFail();
    }

    // A workout with the sets of one exercise: [[kg, reps], …]
    private function workout(User $user, string $date, string $exercise, array $sets): GymWorkout
    {
        $workout = GymWorkout::create(['user_id' => $user->id, 'date' => $date]);
        foreach ($sets as $position => [$weight, $reps]) {
            $workout->sets()->create(['gym_exercise_id' => $this->exercise($exercise)->id, 'position' => $position, 'weight' => $weight, 'reps' => $reps]);
        }

        return $workout;
    }

    public function test_only_the_people_allowed_and_never_the_demo(): void
    {
        $bob = User::factory()->create();
        $demo = User::factory()->create(['is_demo' => true, 'can_use_gym' => true]);

        $this->actingAs($bob)->get('/gym')->assertForbidden();
        $this->actingAs($bob)->post('/gym/workouts')->assertForbidden();
        $this->actingAs($bob)->get('/expenses')->assertInertia(fn ($page) => $page->where('auth.user.canUseGym', false));
        $this->actingAs($demo)->get('/gym')->assertForbidden();

        $this->actingAs($this->anna)->get('/gym')->assertOk()->assertInertia(fn ($page) => $page->component('Gym/Index')->where('auth.user.canUseGym', true));
    }

    public function test_a_workout_with_sets_saved_as_you_type(): void
    {
        $this->actingAs($this->anna)->post('/gym/workouts')->assertRedirect();
        $workout = GymWorkout::where('user_id', $this->anna->id)->sole();
        $this->assertSame(now()->toDateString(), $workout->date->toDateString());

        $bench = $this->exercise('Bench press')->id;
        $pullUp = $this->exercise('Pull-up')->id;
        $this->actingAs($this->anna)->putJson("/gym/workouts/{$workout->id}", [
            'date' => '2026-10-20',
            'notes' => 'Good one',
            'exercises' => [
                ['exerciseId' => $bench, 'sets' => [['weight' => 60, 'reps' => 8], ['weight' => 62.5, 'reps' => 6], ['weight' => 65, 'reps' => null]]],
                ['exerciseId' => $pullUp, 'sets' => [['weight' => null, 'reps' => 10]]],
            ],
        ])->assertOk()->assertJson(['saved' => true]);

        $workout->refresh();
        $this->assertSame('2026-10-20', $workout->date->toDateString());
        $this->assertSame('Good one', $workout->notes);
        // The set without reps isn't done yet; bodyweight has no kg
        $this->assertSame([[60.0, 8], [62.5, 6], [null, 10]], $workout->sets->map(fn ($set) => [$set->weight, $set->reps])->all());

        $this->actingAs($this->anna)->get("/gym/workouts/{$workout->id}")->assertInertia(fn ($page) => $page->component('Gym/Workout')
            ->where('workout.exercises.0.exerciseId', $bench)
            ->has('workout.exercises.0.sets', 2)
            ->where('workout.exercises.1.sets.0.weight', null));

        $this->actingAs($this->anna)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-20', 'exercises' => [['exerciseId' => $bench, 'sets' => [['weight' => 'heavy', 'reps' => 5]]]]])
            ->assertUnprocessable();

        $this->actingAs($this->anna)->delete("/gym/workouts/{$workout->id}")->assertRedirect('/gym');
        $this->assertDatabaseCount('gym_sets', 0);
    }

    public function test_nobody_sees_someone_elses_workouts_or_exercises(): void
    {
        $bob = User::factory()->create(['can_use_gym' => true]);
        $workout = $this->workout($this->anna, '2026-10-01', 'Squat', [[100, 5]]);
        $own = GymExercise::create(['name' => 'Sled push', 'user_id' => $this->anna->id]);

        $this->actingAs($bob)->get("/gym/workouts/{$workout->id}")->assertNotFound();
        $this->actingAs($bob)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-01'])->assertNotFound();
        $this->actingAs($bob)->delete("/gym/workouts/{$workout->id}")->assertNotFound();
        $this->actingAs($bob)->get("/gym/exercises/{$own->id}")->assertNotFound();

        // Bob can't put Anna's exercise in his workout, nor sees it in his list
        $bobs = GymWorkout::create(['user_id' => $bob->id, 'date' => '2026-10-02']);
        $this->actingAs($bob)->putJson("/gym/workouts/{$bobs->id}", ['date' => '2026-10-02', 'exercises' => [['exerciseId' => $own->id, 'sets' => [['weight' => 50, 'reps' => 5]]]]])
            ->assertJsonValidationErrors('exercises.0.exerciseId');
        $this->actingAs($bob)->get("/gym/workouts/{$bobs->id}")->assertInertia(fn ($page) => $page
            ->where('exercises', fn ($list) => collect($list)->pluck('id')->doesntContain($own->id) && count($list) === 48));
        $this->actingAs($bob)->get('/gym')->assertInertia(fn ($page) => $page->has('workouts.data', 1)->where('workouts.data.0.id', $bobs->id));
    }

    public function test_your_own_exercises_with_a_photo(): void
    {
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'Sled push', 'group' => 'Legs', 'photo' => $this->fakeCover()])
            ->assertOk()->assertJson(['label' => 'Sled push', 'group' => 'Legs', 'custom' => true]);
        $exercise = GymExercise::where('user_id', $this->anna->id)->sole();
        $this->assertNotNull($exercise->photo);
        // The same name again: the same exercise, not a copy (the ready list too)
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'sled PUSH'])->assertJson(['id' => $exercise->id]);
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'bench press'])->assertJson(['id' => $this->exercise('Bench press')->id, 'custom' => false]);
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'Yoga', 'group' => 'Mind'])->assertJsonValidationErrors('group');
        $this->assertSame(1, GymExercise::whereNotNull('user_id')->count());

        $this->actingAs($this->anna)->post("/gym/exercises/{$exercise->id}/photo", ['photo' => $this->fakeCover()])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['can_use_gym' => true]))->post("/gym/exercises/{$exercise->id}/photo", ['photo' => $this->fakeCover()])->assertNotFound();
        $this->actingAs($this->anna)->post("/gym/exercises/{$this->exercise('Squat')->id}/photo", ['photo' => $this->fakeCover()])->assertNotFound();
    }

    public function test_every_ready_exercise_has_its_photos_and_italian_name(): void
    {
        $italian = json_decode(File::get(lang_path('it.json')), true);
        foreach (GymExercise::whereNull('user_id')->get() as $exercise) {
            $this->assertCount(2, $exercise->photos(), "{$exercise->name}: start and end photos in public/images/exercises");
            $this->assertArrayHasKey($exercise->name, $italian, "{$exercise->name}: Italian name in lang/it.json");
            $this->assertContains($exercise->muscle_group, GymExercise::GROUPS);
        }
        app()->setLocale('it');
        $this->assertSame('Panca piana', $this->exercise('Bench press')->label());
        $this->assertSame('Sled push', GymExercise::create(['name' => 'Sled push', 'user_id' => $this->anna->id])->label());
    }

    public function test_progress_and_personal_records(): void
    {
        $this->workout($this->anna, '2026-09-01', 'Bench press', [[60, 8], [60, 6]]);
        $this->workout($this->anna, '2026-09-08', 'Bench press', [[65, 5], [70, 2]]);
        $this->workout($this->anna, '2026-09-15', 'Bench press', [[62.5, 10]]);
        $bench = $this->exercise('Bench press')->id;

        $this->actingAs($this->anna)->get("/gym/exercises/{$bench}")->assertInertia(fn ($page) => $page->component('Gym/Exercise')
            ->where('records.heaviest.weight', 70)
            ->where('records.heaviest.date', '2026-09-08')
            // Epley: 62.5 × (1 + 10/30) = 83.3 beats 70 × (1 + 2/30) = 74.7
            ->where('records.oneRepMax.value', 83.3)
            ->where('records.mostReps.reps', 10)
            ->has('chart', 3)
            ->where('chart.0.best', 60)
            ->where('days.0.date', '2026-09-15'));

        // The second workout beat the bench press record (once, however many sets); the first time isn't a record
        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page
            ->where('workouts.data.0.records', 0)
            ->where('workouts.data.1.records', 1)
            ->where('workouts.data.2.records', 0)
            ->where('workouts.data.1.volume', 465)
            ->where('done.0.id', $bench));

        // The editor of the last one knows the best before it and how it went last time
        $last = GymWorkout::where('date', '2026-09-15')->sole();
        $this->actingAs($this->anna)->get("/gym/workouts/{$last->id}")->assertInertia(fn ($page) => $page
            ->where("previousBest.{$bench}", 70)
            ->where("lastTime.{$bench}.date", '2026-09-08')
            ->has("lastTime.{$bench}.sets", 2));

        $this->assertSame(60.0, GymSet::oneRepMax(60, 1));
        $this->assertNull(GymSet::oneRepMax(null, 12));
    }

    public function test_records_are_shared_only_by_choice(): void
    {
        $bob = User::factory()->create(['can_use_gym' => true, 'name' => 'Bob']);
        $this->workout($this->anna, '2026-09-01', 'Deadlift', [[120, 3]]);
        $this->workout($bob, '2026-09-01', 'Squat', [[90, 5]]);

        $this->actingAs($bob)->get('/gym/records')->assertInertia(fn ($page) => $page->component('Gym/Records')->where('sharing', false)->has('people', 0));

        $this->actingAs($this->anna)->put('/gym/share', ['on' => true])->assertSessionHas('message', 'Your records are shared with the other gym users');
        $this->actingAs($bob)->get('/gym/records')->assertInertia(fn ($page) => $page
            ->has('people', 1)
            ->where('people.0.id', $this->anna->id)
            ->where('people.0.lifts.0.exercise', 'Deadlift')
            ->where('people.0.lifts.0.weight', 120)
            // Only the records: nothing about the workouts themselves
            ->missing('people.0.workouts'));

        // No longer allowed in the gym: no longer on the board
        $this->anna->update(['can_use_gym' => false]);
        $this->actingAs($bob)->get('/gym/records')->assertInertia(fn ($page) => $page->has('people', 0));
    }

    public function test_admins_choose_who_uses_it(): void
    {
        $admin = User::factory()->admin()->create();
        $bob = User::factory()->create();
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($this->anna)->put("/admin/maintenance/gym/{$bob->id}", ['on' => true])->assertForbidden();

        $this->actingAs($admin)->put("/admin/maintenance/gym/{$bob->id}", ['on' => true])->assertRedirect();
        $this->assertTrue($bob->fresh()->canUseGym());
        $this->actingAs($admin)->put("/admin/maintenance/gym/{$demo->id}", ['on' => true])->assertStatus(422);

        $this->actingAs($admin)->get('/admin/maintenance')->assertInertia(fn ($page) => $page
            ->where('gym', fn ($people) => collect($people)->firstWhere('id', $bob->id)['on'] === true && ! collect($people)->contains('id', $demo->id)));

        $this->actingAs($admin)->put('/admin/maintenance/gym/everyone', ['on' => false])->assertSessionHas('message', 'Nobody can use the gym tracker');
        $this->assertSame(0, User::where('can_use_gym', true)->count());
        $this->actingAs($admin)->put('/admin/maintenance/gym/everyone', ['on' => true]);
        $this->assertFalse($demo->fresh()->canUseGym());
        $this->assertTrue($this->anna->fresh()->canUseGym());
    }

    public function test_in_italian(): void
    {
        $this->anna->update(['locale' => 'it']);
        $workout = $this->workout($this->anna, '2026-09-01', 'Bench press', [[60, 8]]);

        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page->where('workouts.data.0.exercises.0', 'Panca piana'));
        $this->actingAs($this->anna)->put("/gym/workouts/{$workout->id}", ['date' => '2026-09-01']);
        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page->where('flash.message', 'Allenamento salvato!'));
    }
}
