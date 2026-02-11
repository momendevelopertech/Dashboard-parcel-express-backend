<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernorateChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'internal_governorate_id',
        'internal_governorate_name',
        'external_governorate_id',
        'external_governorate_name',
    ];

    public function shipper()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'internal_governorate_id');
    }
}
