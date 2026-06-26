<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EveType extends Model
{
    protected $table = 'eve_types';

    protected $primaryKey = 'type_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];
}
