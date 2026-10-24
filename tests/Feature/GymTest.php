<?php

namespace Tests\Feature;

use App\Models\GymExercise;
use App\Models\GymSet;
use App\Models\GymWorkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Gym tracker: only for the people the admins allow, each person sees only their own workouts,
// the exercises are the same for everyone (admins manage them), records shared only by choice
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
        return GymExercise::where('name', $name)->firstOrFail();
    }

    // A workout with the rows of one exercise: [[kg, reps, sets = 1], …]
    private function workout(User $user, string $date, string $exercise, array $rows): GymWorkout
    {
        $workout = GymWorkout::create(['user_id' => $user->id, 'date' => $date]);
        foreach ($rows as $position => $row) {
            [$weight, $reps, $sets] = $row + [2 => 1];
            $workout->sets()->create(['gym_exercise_id' => $this->exercise($exercise)->id, 'position' => $position, 'weight' => $weight, 'sets' => $sets, 'reps' => $reps]);
        }

        return $workout;
    }

    public function test_only_the_people_allowed_and_never_the_demo(): void
    {
        $bob = User::factory()->create();
        $demo = User::factory()->create(['is_demo' => true, 'can_use_gym' => true]);

        $this->actingAs($bob)->get('/gym')->assertForbidden();
        $this->actingAs($bob)->post('/gym/workouts')->assertForbidden();
        $this->actingAs($bob)->postJson('/gym/exercises', ['name' => 'Sled push'])->assertForbidden();
        $this->actingAs($bob)->get('/expenses')->assertInertia(fn ($page) => $page->where('auth.user.canUseGym', false));
        $this->actingAs($demo)->get('/gym')->assertForbidden();

        $this->actingAs($this->anna)->get('/gym')->assertOk()->assertInertia(fn ($page) => $page->component('Gym/Index')->where('auth.user.canUseGym', true));
    }

    public function test_a_workout_saved_as_you_type(): void
    {
        $this->actingAs($this->anna)->post('/gym/workouts')->assertRedirect();
        $workout = GymWorkout::where('user_id', $this->anna->id)->sole();
        $this->assertSame(now()->toDateString(), $workout->date->toDateString());

        $bench = $this->exercise('Bench press')->id;
        $pullUp = $this->exercise('Pull-up')->id;
        $this->actingAs($this->anna)->putJson("/gym/workouts/{$workout->id}", [
            'date' => '2026-10-20',
            'focus' => 'upper',
            'notes' => 'Good one',
            'exercises' => [
                ['exerciseId' => $bench, 'rows' => [['weight' => 60, 'sets' => 3, 'reps' => 8], ['weight' => 62.5, 'sets' => 1, 'reps' => 6], ['weight' => 65, 'sets' => 2, 'reps' => null]]],
                // Not done yet: only the sets, still saved (the exercise keeps its place)
                ['exerciseId' => $pullUp, 'rows' => [['weight' => null, 'sets' => 3, 'reps' => null]]],
            ],
        ])->assertOk()->assertJson(['saved' => true]);

        $workout->refresh();
        $this->assertSame('2026-10-20', $workout->date->toDateString());
        $this->assertSame('upper', $workout->focus);
        $this->assertSame('Good one', $workout->notes);
        $this->assertSame(
            [[60.0, 3, 8], [62.5, 1, 6], [65.0, 2, null], [null, 3, null]],
            $workout->sets->map(fn ($set) => [$set->weight, $set->sets, $set->reps])->all()
        );

        $this->actingAs($this->anna)->get("/gym/workouts/{$workout->id}")->assertInertia(fn ($page) => $page->component('Gym/Workout')
            ->where('workout.exercises.0.exerciseId', $bench)
            ->has('workout.exercises.0.rows', 3)
            ->where('workout.exercises.0.rows.0', ['weight' => 60, 'sets' => 3, 'reps' => 8])
            ->where('workout.exercises.1.rows.0.weight', null)
            ->where('workout.focus', 'upper'));

        // 60 × 8 × 3 + 62.5 × 6 = 1815 kg
        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page
            ->where('workouts.data.0.volume', 1815)
            ->where('workouts.data.0.sets', 9));

        $this->actingAs($this->anna)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-20', 'exercises' => [['exerciseId' => $bench, 'rows' => [['weight' => 'heavy', 'sets' => 3, 'reps' => 5]]]]])
            ->assertUnprocessable();
        $this->actingAs($this->anna)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-20', 'focus' => 'cardio'])->assertJsonValidationErrors('focus');

        $this->actingAs($this->anna)->delete("/gym/workouts/{$workout->id}")->assertRedirect('/gym');
        $this->assertDatabaseCount('gym_sets', 0);
    }

    public function test_the_focus_comes_from_the_exercises_until_chosen(): void
    {
        $legs = $this->workout($this->anna, '2026-10-01', 'Squat', [[100, 5]]);
        $legs->sets()->create(['gym_exercise_id' => $this->exercise('Crunch')->id, 'position' => 1, 'sets' => 3, 'reps' => 20]);
        $mixed = $this->workout($this->anna, '2026-10-02', 'Squat', [[100, 5]]);
        $mixed->sets()->create(['gym_exercise_id' => $this->exercise('Bench press')->id, 'position' => 1, 'weight' => 60, 'sets' => 3, 'reps' => 8]);
        $chosen = $this->workout($this->anna, '2026-10-03', 'Squat', [[100, 5]]);
        $chosen->update(['focus' => 'upper']);
        GymWorkout::create(['user_id' => $this->anna->id, 'date' => '2026-10-04']);

        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page
            ->where('workouts.data.0.focus', null) // nothing in it yet
            ->where('workouts.data.1.focus', 'upper')
            ->where('workouts.data.2.focus', 'mixed')
            ->where('workouts.data.3.focus', 'lower')); // core doesn't count
    }

    public function test_repeat_a_workout_as_todays(): void
    {
        $this->travelTo('2026-10-24 18:00');
        $old = $this->workout($this->anna, '2026-10-20', 'Bench press', [[60, 8, 3], [65, 5, 1]]);
        $old->update(['focus' => 'upper']);
        // A later workout with the bench press: the hints come from the day repeated, not this one
        $this->workout($this->anna, '2026-10-22', 'Bench press', [[50, 10, 3]]);

        $this->actingAs($this->anna)->post('/gym/workouts', ['from' => $old->id])->assertRedirect();
        $today = GymWorkout::where('date', '2026-10-24')->sole();
        $this->assertSame($old->id, $today->copied_from_id);
        $this->assertSame('upper', $today->focus);
        // The plan: exercises, sets and reps; the kg to write again
        $this->assertSame([[null, 3, 8], [null, 1, 5]], $today->sets->map(fn ($set) => [$set->weight, $set->sets, $set->reps])->all());

        $bench = $this->exercise('Bench press')->id;
        $this->actingAs($this->anna)->get("/gym/workouts/{$today->id}")->assertInertia(fn ($page) => $page
            ->where("lastTime.{$bench}.date", '2026-10-20')
            ->where("lastTime.{$bench}.rows.0.weight", 60)
            ->where('previousBest.' . $bench, 65)
            ->where('today', '2026-10-24'));

        // Only your own workouts
        $bob = User::factory()->create(['can_use_gym' => true]);
        $this->actingAs($bob)->post('/gym/workouts', ['from' => $old->id])->assertNotFound();
        $this->assertSame(0, GymWorkout::where('user_id', $bob->id)->count());
    }

    public function test_nobody_sees_someone_elses_workouts(): void
    {
        $bob = User::factory()->create(['can_use_gym' => true]);
        $workout = $this->workout($this->anna, '2026-10-01', 'Squat', [[100, 5]]);

        $this->actingAs($bob)->get("/gym/workouts/{$workout->id}")->assertNotFound();
        $this->actingAs($bob)->putJson("/gym/workouts/{$workout->id}", ['date' => '2026-10-01'])->assertNotFound();
        $this->actingAs($bob)->delete("/gym/workouts/{$workout->id}")->assertNotFound();

        $bobs = GymWorkout::create(['user_id' => $bob->id, 'date' => '2026-10-02']);
        $this->actingAs($bob)->get('/gym')->assertInertia(fn ($page) => $page->has('workouts.data', 1)->where('workouts.data.0.id', $bobs->id));
        // Anna's squats aren't in Bob's progress
        $this->actingAs($bob)->get("/gym/exercises/{$this->exercise('Squat')->id}")->assertInertia(fn ($page) => $page->where('records', null));
    }

    public function test_an_exercise_someone_adds_is_there_for_everyone(): void
    {
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'Sled push', 'group' => 'Legs', 'photo' => $this->fakeCover(), 'defaultReps' => 12])
            ->assertOk()->assertJson(['label' => 'Sled push', 'group' => 'Legs', 'defaultReps' => null]); // defaults: admins only
        $exercise = GymExercise::where('name', 'Sled push')->sole();
        $this->assertSame($this->anna->id, $exercise->user_id);
        $this->assertCount(1, $exercise->photos());
        // The same name again (also the Italian one): the same exercise, not a copy
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'sled PUSH'])->assertJson(['id' => $exercise->id]);
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'panca PIANA'])->assertJson(['id' => $this->exercise('Bench press')->id]);
        $this->actingAs($this->anna)->postJson('/gym/exercises', ['name' => 'Yoga', 'group' => 'Mind'])->assertJsonValidationErrors('group');
        $this->assertSame(1, GymExercise::whereNotNull('user_id')->count());

        // Bob finds it and uses it
        $bob = User::factory()->create(['can_use_gym' => true]);
        $bobs = GymWorkout::create(['user_id' => $bob->id, 'date' => '2026-10-02']);
        $this->actingAs($bob)->get("/gym/workouts/{$bobs->id}")->assertInertia(fn ($page) => $page
            ->where('exercises', fn ($list) => collect($list)->pluck('id')->contains($exercise->id) && count($list) === 52));
        $this->actingAs($bob)->putJson("/gym/workouts/{$bobs->id}", ['date' => '2026-10-02', 'exercises' => [['exerciseId' => $exercise->id, 'rows' => [['weight' => 50, 'sets' => 3, 'reps' => 5]]]]])
            ->assertOk();
        $this->actingAs($bob)->get("/gym/exercises/{$exercise->id}")->assertOk();
        $this->actingAs($bob)->putJson("/gym/workouts/{$bobs->id}", ['date' => '2026-10-02', 'exercises' => [['exerciseId' => 9999, 'rows' => []]]])
            ->assertJsonValidationErrors('exercises.0.exerciseId');
    }

    public function test_admins_manage_the_exercises(): void
    {
        $admin = User::factory()->admin()->create();
        $squat = $this->exercise('Squat');
        $this->workout($this->anna, '2026-10-01', 'Squat', [[100, 5]]);

        // Not for the other gym users
        $this->actingAs($this->anna)->get('/gym/exercises')->assertForbidden();
        $this->actingAs($this->anna)->put("/gym/exercises/{$squat->id}", ['name' => 'Squat'])->assertForbidden();
        $this->actingAs($this->anna)->post("/gym/exercises/{$squat->id}/photo", ['photo' => $this->fakeCover()])->assertForbidden();

        $this->actingAs($admin)->get('/gym/exercises')->assertInertia(fn ($page) => $page->component('Gym/Exercises')
            ->has('exercises', 51) // the ready list
            ->where('exercises', fn ($list) => collect($list)->firstWhere('id', $squat->id)['uses'] === 1 && collect($list)->firstWhere('id', $squat->id)['nameIt'] === 'Squat'));

        $this->actingAs($admin)->put("/gym/exercises/{$squat->id}", ['name' => 'Back squat', 'nameIt' => 'Squat con bilanciere', 'group' => 'Legs', 'defaultSets' => 5, 'defaultReps' => 5])
            ->assertSessionHas('message', 'Exercise saved');
        $squat->refresh();
        $this->assertSame([5, 5], [$squat->default_sets, $squat->default_reps]);
        // Renamed, it keeps its ready-made photos
        $this->assertCount(2, $squat->photos());
        app()->setLocale('it');
        $this->assertSame('Squat con bilanciere', $squat->label());
        app()->setLocale('en');
        $this->assertSame('Back squat', $squat->label());
        $this->actingAs($admin)->put("/gym/exercises/{$squat->id}", ['name' => '', 'defaultSets' => 0])->assertSessionHasErrors(['name', 'defaultSets']);

        // A photo of its own, and back to the ready-made ones
        $this->actingAs($admin)->post("/gym/exercises/{$squat->id}/photo", ['photo' => $this->fakeCover()])->assertSessionHasNoErrors();
        $this->assertCount(1, $squat->fresh()->photos());
        $this->actingAs($admin)->delete("/gym/exercises/{$squat->id}/photo");
        $this->assertCount(2, $squat->fresh()->photos());

        // A new one, with everything; deleted only while nobody used it
        $this->actingAs($admin)->post('/gym/exercises', ['name' => 'Sled push', 'nameIt' => 'Spinta della slitta', 'group' => 'Legs', 'defaultSets' => 4, 'defaultReps' => 10])
            ->assertSessionHas('message', 'Exercise added');
        $sled = $this->exercise('Sled push');
        $this->assertSame(['Spinta della slitta', 4, 10], [$sled->name_it, $sled->default_sets, $sled->default_reps]);
        $this->actingAs($admin)->delete("/gym/exercises/{$squat->id}")->assertStatus(422);
        $this->actingAs($admin)->delete("/gym/exercises/{$sled->id}")->assertSessionHas('message', 'Exercise deleted');
        $this->assertNull(GymExercise::find($sled->id));
    }

    public function test_every_ready_exercise_has_its_photos_and_italian_name(): void
    {
        foreach (GymExercise::whereNull('user_id')->get() as $exercise) {
            $this->assertCount(2, $exercise->photos(), "{$exercise->name}: start and end photos in public/images/exercises");
            $this->assertNotEmpty($exercise->name_it, "{$exercise->name}: Italian name");
            $this->assertContains($exercise->muscle_group, GymExercise::GROUPS);
        }
        app()->setLocale('it');
        $this->assertSame('Panca piana', $this->exercise('Bench press')->label());
        $this->assertSame('Sled push', GymExercise::create(['name' => 'Sled push', 'user_id' => $this->anna->id])->label());
    }

    public function test_progress_and_personal_records(): void
    {
        $this->workout($this->anna, '2026-09-01', 'Bench press', [[60, 8, 3], [60, 6]]);
        $this->workout($this->anna, '2026-09-08', 'Bench press', [[65, 5], [70, 2]]);
        $this->workout($this->anna, '2026-09-15', 'Bench press', [[62.5, 10]]);
        // Kg written, reps not yet: counts for the heaviest, not for the estimated max
        $this->workout($this->anna, '2026-09-20', 'Bench press', [[72.5, null, 3]]);
        $bench = $this->exercise('Bench press')->id;

        $this->actingAs($this->anna)->get("/gym/exercises/{$bench}")->assertInertia(fn ($page) => $page->component('Gym/Exercise')
            ->where('records.heaviest.weight', 72.5)
            ->where('records.heaviest.reps', null)
            // Epley: 62.5 × (1 + 10/30) = 83.3 beats 70 × (1 + 2/30) = 74.7
            ->where('records.oneRepMax.value', 83.3)
            ->where('records.mostReps.reps', 10)
            ->has('chart', 4)
            ->where('chart.0.best', 60)
            ->where('days.0.date', '2026-09-20')
            ->where('days.3.rows.0', ['weight' => 60, 'sets' => 3, 'reps' => 8]));

        // The second and the fourth workout beat the bench press record (once, however many rows);
        // the first time isn't a record
        $this->actingAs($this->anna)->get('/gym')->assertInertia(fn ($page) => $page
            ->where('workouts.data.0.records', 1)
            ->where('workouts.data.1.records', 0)
            ->where('workouts.data.2.records', 1)
            ->where('workouts.data.3.records', 0)
            ->where('workouts.data.2.volume', 465)
            ->where('workouts.data.3.volume', 1800)
            ->where('done.0.id', $bench));

        // The editor of the third one knows the best before it and how it went last time
        $third = GymWorkout::where('date', '2026-09-15')->sole();
        $this->actingAs($this->anna)->get("/gym/workouts/{$third->id}")->assertInertia(fn ($page) => $page
            ->where("previousBest.{$bench}", 70)
            ->where("lastTime.{$bench}.date", '2026-09-08')
            ->has("lastTime.{$bench}.rows", 2));

        $this->assertSame(60.0, GymSet::oneRepMax(60, 1));
        $this->assertNull(GymSet::oneRepMax(null, 12));
        $this->assertNull(GymSet::oneRepMax(60, null));
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
