<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShipmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        "shipment_id",
        "trackNode",
        "originActionName",
        "operatorInfo",
        "operationHub",
        "operationHubType",
        "name",
        "description",
        "fromPkgId",
        "time",
        "type",
        "operatorId",
        "proof",
        "data",
        "c_show",
        "updated_at",
        "created_at",
    ];
    protected $appends = ['operation_hub_name', 'warehouse', 'attempt_date', 'proof_url'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
    public function operationHub()
    {
        return $this->morphTo();
    }

    public function getOperationHubNameAttribute()
    {
        if (!$this->operationHubType || !$this->operationHub)
            return null;
        $model = app($this->operationHubType)::find($this->operationHub);
        return $model?->name;
    }

    public function getWarehouseAttribute()
    {
        return [
            'id' => $this->operationHub,
            'name' => $this->operation_hub_name,
        ];
    }

    public function getAttemptDateAttribute()
    {
        $ts = $this->time ?: $this->updated_at;
        return optional($ts)->toDateString();
    }
    public function getProofUrlAttribute()
    {
        if (!$this->proof) {
            return null;
        }
        return Storage::url($this->proof);
    }
}
