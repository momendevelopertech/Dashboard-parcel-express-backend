<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentRule extends Model
{
    protected $fillable = [
        'name',
        'condition_json',
        'action_json',
        'is_active'
    ];

    protected $casts = [
        'condition_json' => 'array',
        'action_json' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function validateCondition($condition)
    {
        // Basic validation for condition structure
        if (!isset($condition['type']) || !isset($condition['operator']) || !isset($condition['value'])) {
            return false;
        }
        
        return true;
    }

    public function validateAction($action)
    {
        // Basic validation for action structure
        if (!isset($action['type'])) {
            return false;
        }
        
        return true;
    }
}
