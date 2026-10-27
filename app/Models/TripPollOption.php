<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TripPollOption extends Model
{
    public function poll(): BelongsTo
    {
        return $this->belongsTo(TripPoll::class, 'trip_poll_id');
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_poll_votes');
    }
}
