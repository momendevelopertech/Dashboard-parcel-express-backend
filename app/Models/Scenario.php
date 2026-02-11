<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scenario extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'steps',
        'tags',
        'category',
        'is_reviewed'
    ];

    protected $casts = [
        'steps' => 'array',
        'tags' => 'array',
        'is_reviewed' => 'boolean'
    ];
}
