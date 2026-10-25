<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A pin of an album on the map (an album can have several: "Londra e Dublino")
class ListingPlace extends Model
{
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'elevation' => 'integer', // metres, null until looked up
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
