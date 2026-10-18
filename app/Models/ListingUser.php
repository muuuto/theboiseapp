<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Listing;


class ListingUser extends Model
{
    use HasFactory;

    protected $table = 'listing_user';
    public $incrementing = false;
    protected $primaryKey = null;
    public $timestamps = false;

    // False right after a deploy, before the migration adding the personal versions has run:
    // album pages then show the general link/description instead of crashing
    public static function hasPersonalVersions(): bool
    {
        static $exists = null;

        return $exists ??= \Illuminate\Support\Facades\Schema::hasColumn('listing_user', 'custom_album_link');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id'); 
    }
    
    public function listing() {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}
