<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Comment;
use App\Support\Settings;
use App\Models\Category;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The roles that belong to the user.
     */
    public function listing(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_user');
    }

    // Admins always; the others only while the admins allow it (the site switch and their own
    // switch on the maintenance page); never the demo account
    public function canPostAlbums(): bool
    {
        if ($this->is_demo) {
            return false;
        }

        return $this->isAdmin == 1
            || ($this->can_post_albums && Settings::get(Settings::MEMBERS_CAN_POST_ALBUMS, false));
    }

    // Admins every album; the others the albums they posted, while they can still post
    public function canEditAlbum(Listing $listing): bool
    {
        return $this->isAdmin == 1 || ($listing->user_id == $this->id && $this->canPostAlbums());
    }

    // Shared expenses groups the user is in
    public function expenseGroups(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseGroup::class)->withTimestamps();
    }

    // Relationship With Listings
    public function listings() {
        return $this->hasMany(Listing::class, 'user_id');
    }

    /**
     * Get the comments for the album post.
    */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * Get the categories created by user.
    */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'created_by');
    }

    public function hidedPost(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_user');
    }

    public function hidedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'categories_user');
    }
}
