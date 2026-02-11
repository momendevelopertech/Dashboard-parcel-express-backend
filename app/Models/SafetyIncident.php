<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafetyIncident extends Model
{
    /** @use HasFactory<\Database\Factories\SafetyIncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'occurred_at',
        'location',
        'description',
        'severity',
        'status',
        'reported_by',
        'assigned_to',
        'investigation_notes'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments()
    {
        return $this->hasMany(SafetyIncidentAttachment::class);
    }
}
