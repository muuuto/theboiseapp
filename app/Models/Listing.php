<?php

namespace App\Models;

use App\Models\Comment;
use App\Models\ListingUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Listing extends Model
{
    use HasFactory;

    public function scopeFilter($query, array $filters) {
        if ($filters['tag'] ?? false) {
            $query->where('tags', 'like', '%' . $filters['tag'] . '%');
        }
    
        if ($filters['search'] ?? false) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                ->orWhere('tags', 'like', '%' . $filters['search'] . '%');
            });
        }
    
        return $query; 
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

        /**
     * Get the listing user for the album post.
    */
    public function listingUser(): HasMany
    {
        return $this->hasMany(ListingUser::class);
    }
}
