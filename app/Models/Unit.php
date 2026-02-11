<?php

namespace App\Models;

use App\Traits\LogsUserActions;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use LogsUserActions;

    protected $fillable = [
        'name',
        'description'
    ];
}
