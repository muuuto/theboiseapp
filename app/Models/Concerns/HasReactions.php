<?php

namespace App\Models\Concerns;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

// Anything people can react to with an emoji (albums, forum posts)
trait HasReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    // Data for the reaction bar: each emoji with how many and who (most used first), the
    // viewer's own, and whether they can react
    public function reactionSummary(User $user, bool $canReact): array
    {
        // Right after a deploy, before its migration runs, the table doesn't exist yet
        if (! Schema::hasTable('reactions')) {
            return ['list' => [], 'mine' => null, 'canReact' => false];
        }
        $reactions = $this->reactions()->with('user')->oldest('id')->get();

        return [
            'list' => $reactions->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => (string) $emoji,
                    'count' => $group->count(),
                    // "You" for the viewer, as the page writes it when they react
                    'names' => $group->map(fn ($reaction) => $reaction->user_id === $user->id ? __('You') : $reaction->user?->name)->filter()->values(),
                ])
                ->sortByDesc('count')->values(),
            'mine' => $reactions->firstWhere('user_id', $user->id)?->emoji,
            'canReact' => $canReact,
        ];
    }
}
