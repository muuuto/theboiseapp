<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// An album tag of the library: one spelling, other spellings as aliases, maybe someone's account
class Tag extends Model
{
    protected $casts = ['aliases' => 'array', 'is_new' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
