<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $primaryKey = 'location_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];
}
