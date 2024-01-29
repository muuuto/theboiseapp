<?php

namespace App\Models;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Listing extends Model
{
    use HasFactory;

    public function scopeFilter($query, array $filters) {
        if($filters['tag'] ?? false) {
            return DB::table('listings')->where('tags', 'like', '%' . request('tag') . '%');
        }

        if($filters['search'] ?? false) {
            return DB::table('listings')->where('title', 'like', '%' . request('search') . '%')
                ->orWhere('description', 'like', '%' . request('search') . '%')
                ->orWhere('tags', 'like', '%' . request('search') . '%');
        }
    }

    /**
     * The roles that belong to the user.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'listing_user');
    }

    // Relationship To User
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the comments for the album post.
    */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
