<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'app_code',
        'min_supported_version',
        'min_supported_build',
        'latest_version',
        'latest_build',
        'force_all',
        'store_url',
        'changelog',
    ];

    protected $casts = [
        'min_supported_build' => 'integer',
        'latest_build' => 'integer',
        'force_all' => 'boolean',
    ];
}
