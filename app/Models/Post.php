<?php

namespace App\Models;

use App\Models\PostComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    use HasFactory;

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
        return $this->belongsToMany(User::class);
    }
}
