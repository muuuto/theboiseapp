<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Comment;
use App\Support\Settings;
use Carbon\Carbon;
use App\Models\Category;

class User extends Authenticatable implements HasLocalePreference
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
        'notification_settings' => 'array',
    ];

    /**
     * The roles that belong to the user.
     */
    public function listing(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_user');
    }

    // Active: in an album or on the site in the last 18 months (admins always). Who's neither has
    // probably drifted away: the automatic notifications (memories, reminders, birthday, "notify
    // everyone", Wrapped) leave them alone. What someone does with them directly still reaches them
    public const ACTIVE_MONTHS = 18;

    public function scopeActive($query)
    {
        $since = now()->subMonths(self::ACTIVE_MONTHS);

        return $query->where(fn ($query) => $query->where('isAdmin', 1)
            ->orWhere('last_login', '>=', $since)
            ->orWhereHas('listing', fn ($albums) => $albums->where('dateFrom', '>=', $since->toDateString())));
    }

    public function isActive(): bool
    {
        $since = now()->subMonths(self::ACTIVE_MONTHS);

        return $this->isAdmin == 1
            || ($this->last_login && Carbon::parse($this->last_login)->gte($since))
            || $this->listing()->where('dateFrom', '>=', $since->toDateString())->exists();
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

    // The gym tracker: only the people the admins switched on (never the demo account)
    public function canUseGym(): bool
    {
        return ! $this->is_demo && (bool) $this->can_use_gym;
    }

    // Admins every album; the others the albums they posted, while they can still post
    public function canEditAlbum(Listing $listing): bool
    {
        return $this->isAdmin == 1 || ($listing->user_id == $this->id && $this->canPostAlbums());
    }

    // Devices where phone notifications are on
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    // Emails and notifications sent to them (Mail::to($user), password reset) use their language
    public function preferredLocale(): string
    {
        return \App\Support\Locale::of($this);
    }

    // Trips the user is going on (planned or done)
    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class)->withTimestamps();
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
