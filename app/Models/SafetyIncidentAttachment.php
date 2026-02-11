<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafetyIncidentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'safety_incident_id',
        'file_path',
        'original_name'
    ];

    public function incident()
    {
        return $this->belongsTo(SafetyIncident::class, 'safety_incident_id');
    }
}
