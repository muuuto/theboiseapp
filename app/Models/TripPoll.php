<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "Pizza or sushi?": a question with options on a trip or event; everyone ticks the ones they like
class TripPoll extends Model
{
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(TripPollOption::class)->orderBy('id');
    }
}
