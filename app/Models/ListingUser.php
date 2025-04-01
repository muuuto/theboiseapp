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

    public function user() {
        return $this->belongsTo(User::class, 'user_id'); 
    }
    
    public function listing() {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}
