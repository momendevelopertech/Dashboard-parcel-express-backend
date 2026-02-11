<?php

namespace App\Models;

use App\Observers\LeaveObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[ObservedBy(LeaveObserver::class)]
class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'status',
        'current_approver_id',
        'reason_id',
        'approved_by',
        'approved_at',
        'owner_id',
        'owner_type',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationship with User (Leave Requester)
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with User (Current Approver)
     */
    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    /**
     * Relationship with User (Final Approver)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship with Leave Reason
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(LeaveReason::class);
    }

    /**
     * Morph Relationship for Ownership
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');
    
        // Allow Super Admins to bypass all restrictions
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }
    
        // Check workspace restrictions if a workspace is selected
        if ($user && $selectedWorkspaceId) {
            $query->where(function ($query) use ($user, $selectedWorkspaceId) {
                if ($user->branch_user) {
                    $query->where('owner_type', Branch::class)
                        ->where('owner_id', $selectedWorkspaceId);
                }
    
                if ($user->station_user) {
                    $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                        $query->where('owner_type', Station::class)
                            ->where('owner_id', $selectedWorkspaceId);
                    });
                }
    
                if ($user->hub_user) {
                    $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                        $query->where('owner_type', Hub::class)
                            ->where('owner_id', $selectedWorkspaceId);
                    });
                }
    
                // Ensure the user sees leave requests they need to approve
                $query->orWhere('current_approver_id', $user->id)
                    ->orWhereNull('current_approver_id'); // Show unassigned requests
            });
        } else {
            // If no workspace is selected, only show leave requests assigned to the user
            $query->where('current_approver_id', $user->id)
                  ->orWhereNull('current_approver_id');
        }
    
        return $query;
    }
    
}
