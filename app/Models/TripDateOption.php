<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// A possible date range in a trip's poll; members tick the ones that work for them
class TripDateOption extends Model
{
    protected $casts = ['date_from' => 'date', 'date_to' => 'date'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_date_votes');
    }
}
