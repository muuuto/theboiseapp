<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingUser extends Model
{
    use HasFactory;

    protected $table = 'listing_user';
    public $incrementing = false;
    protected $primaryKey = null;
    public $timestamps = false;
}
