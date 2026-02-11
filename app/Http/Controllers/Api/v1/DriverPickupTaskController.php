<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MerchantPickupTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Services\WhatsAppService;
use App\Models\Transaction;
use App\Models\User;
use App\Models\PickuptaskTransaction;
use App\Models\WhatsAppTemplate;

class DriverPickupTaskController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function endTask(Request $request, $task)
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'completed_count' => 'nullable|integer|min:0',
            'failed_count' => 'nullable|integer|min:0',
            'received_amount' => 'nullable|numeric|min:0',
        ]);

        // Validate that the pickup task exists
        $task = MerchantPickupTask::find($task);
        if (!$task) {
            return sendResponse(
                'The selected pickup task is invalid.',
                [],
                false,
                ['task' => ['The selected pickup task does not exist.']],
                422
            );
        }

        $driver = Auth::user();

        if ($task->driver_id !== $driver->id) {
            return sendResponse(
                'You are not allowed to end this task.',
                [],
                false,
                ['forbidden'],
                403
            );
        }

        if (in_array($task->status, [MerchantPickupTaskStatusEnum::PICKUP_COMPLETED, MerchantPickupTaskStatusEnum::CANCELLED])) {
            return sendResponse(
                'Task cannot be ended in current status.',
                [],
                false,
                ['invalid_status'],
                422
            );
        }



        DB::beginTransaction();

        try {
            $task->status = MerchantPickupTaskStatusEnum::PICKED;
            $task->completed_at = now();

            if (isset($data['notes'])) {
                $task->note = $data['notes'];
            }

            if (isset($data['completed_count'])) {
                $task->confirmed_shipments_count = $data['completed_count'];
            }
            $shipmentsCount = $task->shipments()->count();

            $task->received_amount = $request->received_amount;

            $task->save();
            $commanData = [
                "merchant_name" => $task->merchant->name,
                'amount' => $task->received_amount,
                "driver_name" => $task->driver->name,
                "no_of_shipments" => $task->no_of_shipments,
                "date" => $task->created_at,
                "task_ref" => $task->ref,

            ];
            $merchantPaymentConfirmedTemplate = WhatsAppTemplate::where('name', 'MERCHANT_PAYMENT_CONFIRMED')->first();
            $merchantPaymentRejectedTemplate = WhatsAppTemplate::where('name', 'MERCHANT_PAYMENT_REJECTED')->first();
            if ($task->received_amount > 0) {

                $ptt = PickuptaskTransaction::updateOrCreate(
                    ['pickuptask_id' => $task->id],
                    ['amount' => $task->received_amount]
                );

                app(\App\Services\MerchantTransactionService::class)->recordPickupDeposit($ptt);

                // Notify the merchant with amount received form driver from their side
                DB::afterCommit(function () use ($task, $commanData, $merchantPaymentConfirmedTemplate) {
                    // Only send WhatsApp message if merchant has a phone number
                    if ($task->merchant && $task->merchant->phone) {
                        $notificationContentForMerchant = handelMessage($merchantPaymentConfirmedTemplate->message, $commanData);

                        $this->whatsappService->sendMessage(
                            $task->merchant->phone,
                            $notificationContentForMerchant
                        );
                    }
                });
            } else {

                // Notify the merchant with amount received form driver from their side
                DB::afterCommit(function () use ($task, $merchantPaymentRejectedTemplate, $commanData) {
                    // Only send WhatsApp message if merchant has a phone number
                    if ($task->merchant && $task->merchant->phone) {
                        $notificationContentForMerchant = handelMessage($merchantPaymentRejectedTemplate->message, $commanData);

                        $this->whatsappService->sendMessage(
                            $task->merchant->phone,
                            $notificationContentForMerchant
                        );
                    }
                });
            }




            DB::commit();

            return sendResponse(
                'Pickup task ended successfully.',
                [
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_at' => $task->completed_at,
                    // 'confirmed_shipments_count' => $task->confirmed_shipments_count,
                    'registered_shipments_no' => $task->registered_shipments_no,
                    'total_shipments_no' => $task->total_shipments_no,
                    'picked_shipments_no' => $task->picked_shipments_no,
                    'to_pickup_shipments_no' => $task->to_pickup_shipments()->count(),
                    'cached_shipment_step' => $task->cached_shipment_step,
                    'ref' => $task->ref,
                    'received_amount' => $task->received_amount,
                ]
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return sendResponse(
                $e->getMessage(),
                [],
                false,
                ['server_error'],
                500
            );
        }
    }
}
