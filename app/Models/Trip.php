<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

// A trip being planned: who's going, a date poll, a checklist, shared expenses; then its album
class Trip extends Model
{
    protected $casts = ['date_from' => 'date', 'date_to' => 'date'];

    // The rows go with the trip (database), the files with this
    protected static function booted(): void
    {
        static::deleted(fn (Trip $trip) => Storage::disk(TripDocument::DISK)->deleteDirectory("trip-documents/{$trip->id}"));
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TripDateOption::class)->orderBy('date_from');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TripItem::class)->orderBy('done')->orderBy('id');
    }

    // Tickets, bookings, PDFs
    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function expenseGroup(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members->contains('id', $user->id);
    }

    // Title, people, dates, delete: who created it, and admins
    public function isManagedBy(User $user): bool
    {
        return $this->created_by == $user->id || $user->isAdmin == 1;
    }

    // planning (dates not decided), upcoming, ongoing, finished (no album yet), album
    public function status(): string
    {
        return match (true) {
            (bool) $this->listing_id => 'album',
            ! $this->date_from => 'planning',
            $this->date_from->isFuture() => 'upcoming',
            ($this->date_to ?? $this->date_from)->endOfDay()->isFuture() => 'ongoing',
            default => 'finished',
        };
    }
}
