<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One row of a workout: weight (kg, empty = bodyweight) × sets × reps (empty = not written)
class GymSet extends Model
{
    protected $casts = ['weight' => 'float', 'sets' => 'integer', 'reps' => 'integer'];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(GymWorkout::class, 'gym_workout_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(GymExercise::class, 'gym_exercise_id');
    }

    public function volume(): float
    {
        return ($this->weight ?? 0) * ($this->reps ?? 0) * $this->sets;
    }

    // As the pages show it: { weight, sets, reps }
    public function row(): array
    {
        return ['weight' => $this->weight, 'sets' => $this->sets, 'reps' => $this->reps];
    }

    // Estimated one-rep max (Epley), for comparing a heavy triple with a lighter set of ten
    public static function oneRepMax(?float $weight, ?int $reps): ?float
    {
        if (! $weight || ! $reps || $reps < 1) {
            return null;
        }

        return round($reps === 1 ? $weight : $weight * (1 + $reps / 30), 1);
    }
}
