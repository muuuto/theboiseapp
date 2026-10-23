<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// An exercise: from the ready list (user_id empty, name translated) or someone's own
class GymExercise extends Model
{
    public const GROUPS = ['Chest', 'Back', 'Shoulders', 'Legs', 'Arms', 'Core'];

    // The ready list and this person's own ones
    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    // What it looks like: start and end position for the ready list (public domain photos, see
    // public/images/exercises/CREDITS.md), the uploaded photo for someone's own
    public function photos(): array
    {
        if ($this->isCustom()) {
            return $this->photo ? [Media::url($this->photo)] : [];
        }
        $slug = Str::slug($this->name);

        return collect([0, 1])->map(fn ($i) => "/images/exercises/{$slug}-{$i}.webp")
            ->filter(fn ($path) => file_exists(public_path($path)))->values()->all();
    }

    // The ready ones are translated; people's own stay as they wrote them
    public function label(): string
    {
        return $this->isCustom() ? $this->name : __($this->name);
    }
}
