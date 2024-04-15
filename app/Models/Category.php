<?php

namespace App\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    public function scopeFilter($query, array $filters) {
        if($filters['tag'] ?? false) {
            return DB::table('categories')->where('tags', 'like', '%' . request('tag') . '%');
        }

        if($filters['search'] ?? false) {
            return DB::table('categories')->where('title', 'like', '%' . request('search') . '%')
                ->orWhere('description', 'like', '%' . request('search') . '%')
                ->orWhere('tags', 'like', '%' . request('search') . '%');
        }
    }

    // Relationship With Posts
    public function posts() {
        return $this->hasMany(Post::class, 'category_id')->latest();
    }

    public function hidedUser(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'categories_user');
    }
}
