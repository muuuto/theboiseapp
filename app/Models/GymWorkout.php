<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// A day at the gym: its rows (kg × sets × reps), in order
class GymWorkout extends Model
{
    public const FOCUSES = ['upper', 'lower', 'mixed'];

    protected $casts = ['date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(GymSet::class)->orderBy('position');
    }

    // The workout it was started from ("repeat as today's")
    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    // Upper body, lower body or mixed: the one chosen, otherwise from the exercises (core and
    // other don't count; null when there's nothing to tell). Needs sets.exercise
    public function focus(): ?string
    {
        if ($this->focus) {
            return $this->focus;
        }
        $areas = $this->sets->map(fn (GymSet $set) => $set->exercise?->area())->filter()->unique();

        return match (true) {
            $areas->count() > 1 => 'mixed',
            $areas->count() === 1 => $areas->first(),
            default => null,
        };
    }

    // Kg lifted in total: weight × reps × sets of every row
    public function volume(): int
    {
        return (int) round($this->sets->sum(fn (GymSet $set) => $set->volume()));
    }
}
