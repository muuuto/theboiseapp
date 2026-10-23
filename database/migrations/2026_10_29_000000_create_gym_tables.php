<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Gym tracker: workouts with exercises and sets (kg × reps), for the people the admins allow.
// Private: each person sees only their own (records can be shared with the other gym users)
return new class extends Migration
{
    // The ready list (names in English, translated in lang/it.json), by muscle group
    private const EXERCISES = [
        'Chest' => ['Bench press', 'Incline bench press', 'Dumbbell bench press', 'Incline dumbbell press', 'Chest fly', 'Cable crossover', 'Push-up', 'Dips'],
        'Back' => ['Deadlift', 'Pull-up', 'Chin-up', 'Lat pulldown', 'Barbell row', 'Dumbbell row', 'Seated cable row', 'T-bar row', 'Face pull'],
        'Shoulders' => ['Overhead press', 'Dumbbell shoulder press', 'Arnold press', 'Lateral raise', 'Front raise', 'Rear delt fly', 'Shrug'],
        'Legs' => ['Squat', 'Front squat', 'Goblet squat', 'Leg press', 'Lunge', 'Bulgarian split squat', 'Romanian deadlift', 'Leg extension', 'Leg curl', 'Hip thrust', 'Calf raise'],
        'Arms' => ['Barbell curl', 'Dumbbell curl', 'Hammer curl', 'Preacher curl', 'Triceps pushdown', 'Skull crusher', 'Overhead triceps extension', 'Close-grip bench press'],
        'Core' => ['Crunch', 'Cable crunch', 'Hanging leg raise', 'Russian twist', 'Ab wheel'],
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'can_use_gym')) {
                $table->boolean('can_use_gym')->default(false);
            }
            if (! Schema::hasColumn('users', 'gym_share_records')) {
                $table->boolean('gym_share_records')->default(false);
            }
        });

        Schema::create('gym_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('muscle_group', 20)->nullable();
            // Someone's own exercise can have a photo (the ready list has its own in public/images/exercises)
            $table->string('photo')->nullable();
            // null = from the ready list; otherwise someone's own (only they see it)
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('gym_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::create('gym_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_workout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('weight', 6, 2)->nullable(); // kg; empty = bodyweight
            $table->unsignedSmallInteger('reps');
            $table->timestamps();
        });

        $now = now();
        foreach (self::EXERCISES as $group => $names) {
            foreach ($names as $name) {
                DB::table('gym_exercises')->insert(['name' => $name, 'muscle_group' => $group, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_sets');
        Schema::dropIfExists('gym_workouts');
        Schema::dropIfExists('gym_exercises');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['can_use_gym', 'gym_share_records']));
    }
};
