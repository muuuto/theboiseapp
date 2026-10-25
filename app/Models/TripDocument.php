<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// A ticket, booking or PDF of a trip (the file is private: only the trip's members open it)
class TripDocument extends Model
{
    public const DISK = 'local';

    protected static function booted(): void
    {
        static::deleted(fn (TripDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'for_user_id');
    }
}
