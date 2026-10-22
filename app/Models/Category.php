<?php

namespace App\Models;

use App\Support\ForumOrder;
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
    // In the order chosen by the admins, then newest first
    public function posts() {
        return ForumOrder::apply($this->hasMany(Post::class, 'category_id'))->latest();
    }

    public function hidedUser(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'categories_user');
    }
}
