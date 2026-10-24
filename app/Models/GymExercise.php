<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// An exercise, the same for everyone: from the ready list (with its photos) or added by someone.
// Admins manage them: names in English and Italian, muscle group, photo, default sets and reps
class GymExercise extends Model
{
    public const GROUPS = ['Chest', 'Back', 'Shoulders', 'Legs', 'Arms', 'Core'];

    // Rows of a new exercise in a workout, when the exercise has no default of its own
    public const DEFAULT_SETS = 3;

    protected $casts = ['default_sets' => 'integer', 'default_reps' => 'integer'];

    // Who added it (empty for the ready list)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // What it looks like: an uploaded photo, otherwise start and end position for the ready list
    // (public domain photos, see public/images/exercises/CREDITS.md)
    public function photos(): array
    {
        if ($this->photo) {
            return [Media::url($this->photo)];
        }
        if (! $this->image) {
            return [];
        }

        return collect([0, 1])->map(fn ($i) => "/images/exercises/{$this->image}-{$i}.webp")
            ->filter(fn ($path) => file_exists(public_path($path)))->values()->all();
    }

    // In the reader's language: the Italian name when there is one
    public function label(): string
    {
        return app()->getLocale() === 'it' && $this->name_it ? $this->name_it : $this->name;
    }

    // Upper body, lower body or neither (core, other)
    public function area(): ?string
    {
        return match ($this->muscle_group) {
            'Chest', 'Back', 'Shoulders', 'Arms' => 'upper',
            'Legs' => 'lower',
            default => null,
        };
    }
}
