<?php

namespace App\Models;

use App\Models\Concerns\HasReactions;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory, HasReactions;

    public function scopeFilter($query, array $filters) {
        if($filters['tag'] ?? false) {
            return DB::table('posts')->where('tags', 'like', '%' . request('tag') . '%');
        }

        if($filters['search'] ?? false) {
            return DB::table('posts')->where('title', 'like', '%' . request('search') . '%')
                ->orWhere('content', 'like', '%' . request('search') . '%')
                ->orWhere('tags', 'like', '%' . request('search') . '%');
        }
    }

    // Relationship To User
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author');
    }
    
    /**
     * Get the comments for the album post.
    */
    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function hidedUser(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_user');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // The author and admins can change privacy settings and notify everyone.
    // Note: $this->author is the author's id (the column), $this->author() the relationship
    public function isManagedBy(User $user): bool
    {
        return $this->author == $user->id || $user->isAdmin == 1;
    }

    // Private posts are only for the author; hidden posts and categories are not for the hidden users
    public function isVisibleTo(User $user): bool
    {
        if ($this->is_private && $this->author != $user->id) {
            return false;
        }

        return ! $this->hidedUser->contains('id', $user->id)
            && ! $this->category->hidedUser->contains('id', $user->id);
    }
}
