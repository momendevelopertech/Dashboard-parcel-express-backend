<?php

namespace App\Observers;

use App\Models\Notification;
use App\Events\NotificationCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationObserver
{
    private static $processedNotifications = [];

    /**
     * Handle the Notification "created" event.
     */
    public function created(Notification $notification): void
    {
        // منع معالجة نفس الإشعار多次 مرات
        $notificationKey = 'notification_' . $notification->id;

        if (in_array($notificationKey, self::$processedNotifications)) {
            Log::warning('Notification already processed, skipping event', [
                'notification_id' => $notification->id
            ]);
            return;
        }

        // إضافة الإشعار إلى القائمة المعالجة
        self::$processedNotifications[] = $notificationKey;

        // الحد الأقصى للقائمة لتجنب تسرب الذاكرة
        if (count(self::$processedNotifications) > 100) {
            array_shift(self::$processedNotifications);
        }

        try {
            $event = new NotificationCreated($notification);
            event($event);

            Log::info('NotificationCreated event dispatched', [
                'notification_id' => $notification->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch NotificationCreated event', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id
            ]);
        }
    }
}
