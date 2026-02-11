<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomsDeclaration extends Model
{
    protected $fillable = [
        'description',
        'declared_value',
        'hs_code',
        'origin_country',
        'export_reason',
        'invoice_path',
        'status'
    ];

    protected $casts = [
        'declared_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'draft'
    ];
}
