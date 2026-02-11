<?php

namespace App\Traits;

use App\Models\UserAction;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Auth;

trait LogsUserActions
{
    protected static function bootLogsUserActions()
    {
        static::created(function ($model) {
            self::logAction('create', $model);
        });

        static::updated(function ($model) {
            self::logAction('update', $model);
        });

        static::deleted(function ($model) {
            self::logAction('delete', $model);
        });
    }

    protected static function logAction($actionType, $model)
    {

        if (!Auth::check()) {
            return;
        }

        info(Auth::check());

        $allowed = [
            'Super Admin',
            'Supervisor',
            'StationAdmin',
            'HubAdmin',
            'BranchAdmin',
        ];

        if (!Auth::user()->hasAnyRole($allowed)) {
            return;
        }

        $agent = new Agent();
        $userId = Auth::id();
        $ipAddress = request()->ip();
        $actionType = $actionType . '_' . strtolower(class_basename($model));

        UserAction::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => get_class($model),
            'entity_id' => $model->id,
            'details' => $actionType === 'update' ? $model->getDirty() : $model->toArray(),
            'ip_address' => $ipAddress,
            'device' => $agent->device() . ' ' . $agent->browser(),
            'is_suspicious' => self::isSuspiciousAction($actionType, $userId, $ipAddress)
        ]);
    }

    protected static function isSuspiciousAction($actionType, $userId, $ipAddress): bool
    {
        $recentActionsCount = UserAction::where('action_type', $actionType)
            ->where('user_id', $userId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->count();

        if ($recentActionsCount > 10) {
            return true;
        }

        $rapidFireActionsCount = UserAction::where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($rapidFireActionsCount > 30) {
            return true;
        }

        return false;
    }
}
