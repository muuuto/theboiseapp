<?php

namespace App\Models\Concerns;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Anything users can like (album comments, forum comments)
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    // Data for the like button: count, whether the user liked it, and who did
    public function likeSummary(User $user): array
    {
        $likes = $this->relationLoaded('likes') ? $this->likes : $this->likes()->with('user')->get();

        return [
            'count' => $likes->count(),
            'liked' => $likes->contains('user_id', $user->id),
            'names' => $likes->map(fn ($like) => $like->user?->name)->filter()->values(),
        ];
    }
}
