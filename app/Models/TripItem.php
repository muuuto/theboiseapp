<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Something to sort out for a trip ("Tent", "Book the ferry"), maybe with who takes care of it
class TripItem extends Model
{
    protected $casts = ['done' => 'boolean'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
