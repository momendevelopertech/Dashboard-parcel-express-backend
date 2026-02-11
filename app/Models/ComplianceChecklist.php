<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ComplianceChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\ComplianceChecklistFactory> */
    use HasFactory;

    protected $fillable = ['name', 'category', 'last_completed', 'status'];

    protected $casts = [
        'last_completed' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ChecklistItem::class, 'checklist_id');
    }

    // Accessor for formatted last_completed
    public function getLastCompletedFormattedAttribute()
    {
        return $this->last_completed ? $this->last_completed->format('Y-m-d H:i:s') : null;
    }

    // Check if all items are completed
    public function isFullyCompleted(): bool
    {
        return $this->items()->count() > 0 && $this->items()->where('is_completed', false)->count() === 0;
    }
}
