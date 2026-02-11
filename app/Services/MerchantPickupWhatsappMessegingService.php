<?php

namespace App\Services;

use App\Models\User;
use App\Models\Merchant;
use App\Models\MerchantPickupTask;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;

class MerchantPickupWhatsappMessegingService
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send WhatsApp notifications to driver and merchant for pickup task assignment
     *
     * @param MerchantPickupTask $task The pickup task model
     */
    public function pickup_task_whatsapp_notification(MerchantPickupTask $task): void
    {
        $notifyDriverOfNewTask = WhatsAppTemplate::where('name', 'NOTIFY_DRIVER_NEW_PICKUP_TASK')->first();
        $shpmentPickedNotifyMerchant = WhatsAppTemplate::where('name', 'SHIPMENT_PICKED_NOTIFY_MERCHANT')->first();
        $instagramAccount = Setting::where('key', 'instagram')->value('value');
        $driver = User::find($task->driver_id);
        $merchant = Merchant::with("user")->where('user_id', $task->merchant_id)->first();

        if (!$driver || !$merchant) {
            return;
        }

        $merchantName = $merchant->user->name;
        $driverName = $driver->name;
        $driverPhoneNumber = $driver->country_code . $driver->phone;
        $merchantPhoneNumber = $merchant->country_code . $merchant->contact_no;
        $merchantAddress = $merchant->address ?? 'غير محدد';

        // Format date and time
        $dateTimeFormatted = $task->created_at->format('Y-m-d H:i');

        $taskRef =  "#{$task->ref}";
        $commonData = [
            'task_ref'          => $task->ref,
            'merchant_name'     => $merchantName,
            'merchant_phone'    => $merchantPhoneNumber,
            'merchant_address'  => $merchantAddress,
            'driver_name'       => $driverName,
            'driver_phone'      => $driverPhoneNumber,
            'no_of_shipments'   => $task->no_of_shipments,
            'pickup_date'       => $dateTimeFormatted,
            'instagram_account' => $instagramAccount,
        ];
        $notificationContent = handelMessage($notifyDriverOfNewTask->message, $commonData);

        $notificationContentForMerchant = handelMessage($shpmentPickedNotifyMerchant->message, $commonData);
        
        $this->whatsappService->sendMessage($driverPhoneNumber, $notificationContent);

        create_notification(
            $driver,
            "📦 مهمة استلام جديدة",
            $notificationContent,
            [
                'task_id' => $task->id,
                'merchant_id' => $task->merchant_id,
                'merchant_name' => $merchantName,
                'no_of_shipments' => $task->no_of_shipments,
                'location' => $task->location ?? null,
                'timestamp' => now()->toDateTimeString(),
                'priority' => 'high'
            ],
            'pickup_task_assigned',
            false
        );

     

        $this->whatsappService->sendMessage($merchantPhoneNumber, $notificationContentForMerchant);

        create_notification(
            $merchant->user,
            "📦 مهمة استلام جديدة",
            $notificationContentForMerchant,
            [
                'task_id' => $task->id,
                'merchant_id' => $task->merchant_id,
                'merchant_name' => $merchantName,
                'no_of_shipments' => $task->no_of_shipments,
                'location' => $task->location ?? null,
                'timestamp' => now()->toDateTimeString(),
                'priority' => 'high'
            ],
            'pickup_task_assigned',
            false
        );
    }


    // private function handelMessage($message,array $data=[]):string
    // {
    //     return preg_replace_callback('/{{(.*?)}}/', function($matches) use ($data) {
    //         $key=trim($matches[1]);
    //         return $data[$key] ?? '';
    //     }, $message);
    // }


    public function renserse_pickup_task_whatsapp_notification($task)
    {
        $reversePickupNotifyMerchant = WhatsAppTemplate::where('name', 'REVERSE_PICKUP_NOTIFY_MERCHANT')->first();
        $instagramAccount = Setting::where('key', 'instagram')->value('value');
        $driver = User::find($task->driver_id);
        $merchant = Merchant::with("user")->where('user_id', $task->merchant_id)->first();

        if (!$merchant) {
            return;
        }

        $merchantName = $merchant->user->name;
        $driverName = $driver->name;
        $driverPhoneNumber = $driver->country_code . $driver->phone;
        $merchantPhoneNumber = $merchant->country_code . $merchant->contact_no;
        $merchantAddress = $merchant->address ?? 'غير محدد';

        // Format date and time
        $dateTimeFormatted = $task->created_at->format('Y-m-d H:i');

        $commonData = [
            'task_ref'          => $task->ref,
            'merchant_name'     => $merchantName,
            'merchant_phone'    => $merchantPhoneNumber,
            'merchant_address'  => $merchantAddress,
            'driver_name'       => $driverName,
            'driver_phone'      => $driverPhoneNumber,
            'no_of_shipments'   => $task->no_of_shipments,
            'pickup_date'       => $dateTimeFormatted,
            'instagram_account' => $instagramAccount,
        ];

        $notificationContentForMerchant = handelMessage($reversePickupNotifyMerchant->message, $commonData);

        $this->whatsappService->sendMessage($merchantPhoneNumber, $notificationContentForMerchant);

        create_notification(
            $merchant->user,
            "📦 مهمة ارجاع جديده",
            $notificationContentForMerchant,
            [
                'task_id' => $task->id,
                'merchant_id' => $task->merchant_id,
                'merchant_name' => $merchantName,
                'no_of_shipments' => $task->no_of_shipments,
                'location' => $task->location ?? null,
                'timestamp' => now()->toDateTimeString(),
                'priority' => 'high'
            ],
            'reverse_pickup_task_assigned',
            false
        );
    }
}
