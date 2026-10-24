<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Gym tracker, second round:
// - exercises are shared by everyone, with an Italian name and default sets and reps (admins
//   manage them); the ready list keeps its photos through `image`, and gets three for the hips
// - a row of a workout is "kg × sets × reps": until now there was one number only, and people
//   wrote the sets in it, so it moves to `sets` and the reps start empty
// - a workout has its focus (upper body, lower body, mixed; empty = from the exercises) and
//   remembers the workout it was copied from
return new class extends Migration
{
    // The ready list in Italian (until now in lang/it.json)
    private const ITALIAN = [
        'Ab wheel' => 'Ruota per addominali',
        'Arnold press' => 'Arnold press',
        'Barbell curl' => 'Curl con bilanciere',
        'Barbell row' => 'Rematore con bilanciere',
        'Bench press' => 'Panca piana',
        'Bulgarian split squat' => 'Squat bulgaro',
        'Cable crossover' => 'Croci ai cavi',
        'Cable crunch' => 'Crunch ai cavi',
        'Calf raise' => 'Calf raise',
        'Chest fly' => 'Croci con manubri',
        'Chin-up' => 'Trazioni presa supina',
        'Close-grip bench press' => 'Panca presa stretta',
        'Crunch' => 'Crunch',
        'Deadlift' => 'Stacco da terra',
        'Dips' => 'Dip alle parallele',
        'Dumbbell bench press' => 'Panca piana con manubri',
        'Dumbbell curl' => 'Curl con manubri',
        'Dumbbell row' => 'Rematore con manubrio',
        'Dumbbell shoulder press' => 'Lento con manubri',
        'Face pull' => 'Face pull',
        'Front raise' => 'Alzate frontali',
        'Front squat' => 'Front squat',
        'Goblet squat' => 'Goblet squat',
        'Hammer curl' => 'Curl a martello',
        'Hanging leg raise' => 'Sollevamento gambe alla sbarra',
        'Hip thrust' => 'Hip thrust',
        'Incline bench press' => 'Panca inclinata',
        'Incline dumbbell press' => 'Panca inclinata con manubri',
        'Lat pulldown' => 'Lat machine',
        'Lateral raise' => 'Alzate laterali',
        'Leg curl' => 'Leg curl',
        'Leg extension' => 'Leg extension',
        'Leg press' => 'Pressa',
        'Lunge' => 'Affondi',
        'Overhead press' => 'Lento avanti',
        'Overhead triceps extension' => 'Estensioni tricipiti sopra la testa',
        'Preacher curl' => 'Curl alla panca Scott',
        'Pull-up' => 'Trazioni alla sbarra',
        'Push-up' => 'Piegamenti',
        'Rear delt fly' => 'Alzate posteriori',
        'Romanian deadlift' => 'Stacco rumeno',
        'Russian twist' => 'Russian twist',
        'Seated cable row' => 'Pulley basso',
        'Shrug' => 'Scrollate',
        'Skull crusher' => 'French press',
        'Squat' => 'Squat',
        'T-bar row' => 'Rematore T-bar',
        'Triceps pushdown' => 'Pushdown ai cavi',
    ];

    // New in the ready list: hips and glutes (photos from the same source, see CREDITS.md)
    private const NEW_EXERCISES = [
        'Glute bridge' => 'Ponte per glutei',
        'Cable kickback' => 'Slanci posteriori ai cavi',
        'Cable pull-through' => 'Pull-through ai cavi',
    ];

    public function up(): void
    {
        Schema::table('gym_exercises', function (Blueprint $table) {
            $table->string('name_it', 100)->nullable()->after('name');
            // The ready list's photos: public/images/exercises/{image}-0.webp and -1.webp
            $table->string('image', 100)->nullable()->after('photo');
            $table->unsignedTinyInteger('default_sets')->nullable();
            $table->unsignedSmallInteger('default_reps')->nullable();
        });
        foreach (DB::table('gym_exercises')->whereNull('user_id')->get() as $exercise) {
            DB::table('gym_exercises')->where('id', $exercise->id)->update([
                'name_it' => self::ITALIAN[$exercise->name] ?? null,
                'image' => Str::slug($exercise->name),
            ]);
        }
        $now = now();
        foreach (self::NEW_EXERCISES as $name => $italian) {
            // Someone may have added it already: that one stays
            if (DB::table('gym_exercises')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                continue;
            }
            DB::table('gym_exercises')->insert(['name' => $name, 'name_it' => $italian, 'muscle_group' => 'Legs', 'image' => Str::slug($name), 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('gym_sets', function (Blueprint $table) {
            $table->unsignedSmallInteger('sets')->default(1)->after('weight');
            $table->unsignedSmallInteger('reps')->nullable()->change();
        });
        DB::table('gym_sets')->update(['sets' => DB::raw('reps')]);
        DB::table('gym_sets')->update(['reps' => null]);

        Schema::table('gym_workouts', function (Blueprint $table) {
            $table->string('focus', 10)->nullable()->after('date');
            $table->foreignId('copied_from_id')->nullable()->after('notes')->constrained('gym_workouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gym_workouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_id');
            $table->dropColumn('focus');
        });

        DB::table('gym_sets')->whereNull('reps')->update(['reps' => DB::raw('sets')]);
        Schema::table('gym_sets', function (Blueprint $table) {
            $table->unsignedSmallInteger('reps')->nullable(false)->change();
            $table->dropColumn('sets');
        });

        DB::table('gym_exercises')->whereNull('user_id')->whereIn('name', array_keys(self::NEW_EXERCISES))->delete();
        Schema::table('gym_exercises', fn (Blueprint $table) => $table->dropColumn(['name_it', 'image', 'default_sets', 'default_reps']));
    }
};
