<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

// The history of the albums: created, edited (which fields, before and after), people added or
// removed, deleted, and who can post albums. Only admins read it
class AlbumEvent extends Model
{
    public const UPDATED_AT = null;

    protected $casts = ['details' => 'array'];

    private static ?bool $ready = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Skipped (no error) before the migration runs
    public static function record(string $action, ?Listing $listing = null, array $details = [], ?User $by = null): void
    {
        if (! (self::$ready ??= Schema::hasTable('album_events'))) {
            return;
        }

        self::create([
            'listing_id' => $listing?->id,
            'listing_title' => $listing?->title,
            'user_id' => ($by ?? auth()->user())?->id,
            'action' => $action,
            'details' => $details ?: null,
        ]);
    }
}
