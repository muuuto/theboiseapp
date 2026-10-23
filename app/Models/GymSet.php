<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One set: weight (kg, empty = bodyweight) × reps
class GymSet extends Model
{
    protected $casts = ['weight' => 'float'];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(GymWorkout::class, 'gym_workout_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(GymExercise::class, 'gym_exercise_id');
    }

    // Estimated one-rep max (Epley), for comparing a heavy triple with a lighter set of ten
    public static function oneRepMax(?float $weight, int $reps): ?float
    {
        if (! $weight || $reps < 1) {
            return null;
        }

        return round($reps === 1 ? $weight : $weight * (1 + $reps / 30), 1);
    }
}
