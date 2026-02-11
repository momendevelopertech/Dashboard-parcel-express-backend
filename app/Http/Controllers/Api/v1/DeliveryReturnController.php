<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\DeliveryExceptionEnum;
use App\Models\Consignee;
use App\Models\DeliveryException;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Shipment;
// use App\Models\CrmTask;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CrmTask;
use App\Models\DriverBonusesTransaction;
use App\Models\DriverShipmentAssignment;
use App\Models\DriverRunsheetShipment;
use App\Models\ShipmentHistory;
use App\Models\Transaction;
use App\Notifications\ShipmentFutureDeliveryNotification;
use App\Services\AddressService;
use App\Services\ShipmentValidationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeliveryReturnController extends Controller
{
    /**
     * Handle Shipment Delivery Exception
     *
     * Process delivery exceptions when shipments cannot be delivered (no answer, cancellation, future delivery).
     * Creates exception history, updates shipment status, and manages runsheet assignments.
     *
     * @OA\Post(
     *     path="/driver/shipments/return",
     *     summary="Handle shipment delivery exception",
     *     description="Process delivery exceptions with proof and create exception history",
     *     operationId="handleShipmentException",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *                 @OA\Property(property="delivery_exception", type="string", enum={"NO_ANSWER", "CANCELLED", "FUTURE_DELIVERY", "WRONG_CITY", "WRONG_NUMBER", "WRONG_ADDRESS", "DELIVER_LATER_TODAY", "TOMORROW"}, example="NO_ANSWER", description="Exception type"),
     *                 @OA\Property(property="future_delivery_date", type="string", format="date", example="2024-12-30", description="Required when exception is FUTURE_DELIVERY"),
     *                 @OA\Property(property="proof", type="string", format="binary", description="Proof of exception (photo/document)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exception handled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment marked with exception."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid future date",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database transaction error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */

    /**
     * رجّع آخر subtype محفوظ (من history.data أو من الوصف)،
     * بنفضّل سجلات type = DELIVERY_EXCEPTION، ولو ملقيناش بنعمل fallback للآخر عموماً.
     */
    private function getLastExceptionSubtype(Shipment $shipment): ?string
    {
        $last = ShipmentHistory::where('shipment_id', $shipment->id)
            ->where('type', 'DELIVERY_EXCEPTION')
            ->latest()
            ->first();

        // fallback لو عندك سجلات قديمة كان type فيها = subtype
        if (!$last) {
            $last = ShipmentHistory::where('shipment_id', $shipment->id)
                ->latest()
                ->first();
        }

        if (!$last)
            return null;

        // 1) جرّب من JSON data
        if (!empty($last->data)) {
            try {
                $payload = is_array($last->data) ? $last->data : json_decode($last->data, true);
                if (!empty($payload['exception_subtype'])) {
                    return $payload['exception_subtype'];
                }
            } catch (\Throwable $e) {
            }
        }

        // 2) fallback: طلع اللي بين الأقواس من الـ description
        if (!empty($last->description) && preg_match('/\[(.*?)\]/', $last->description, $m)) {
            return $m[1] ?? null;
        }

        // 3) fallback أخير: لو type نفسه واحد من الأنواع المعروفة
        $known = [
            DeliveryExceptionEnum::DELIVER_LATER_TODAY,
            DeliveryExceptionEnum::FUTURE_DELIVERY,
            DeliveryExceptionEnum::CANCELLED,
        ];
        if (!empty($last->type) && in_array($last->type, $known, true)) {
            return $last->type;
        }

        return null;
    }
    protected function resolveShipmentTimezone(\App\Models\Shipment $shipment): string
    {
        $candidates = [
            optional($shipment->consignee)->timezone,
            optional($shipment->merchant)->timezone,
            optional($shipment->shipper)->timezone,
            optional($shipment->shipment_delivery)->timezone,
            config('app.timezone'),
            date_default_timezone_get(),
            'UTC',
        ];
        foreach ($candidates as $tz) {
            if (is_string($tz) && $tz !== '') {
                try {
                    \Carbon\Carbon::now($tz);
                    return $tz;
                } catch (\Throwable $e) {
                }
            }
        }
        return 'UTC';
    }


    public function handleShipmentException(Request $request)
    {
        $request->validate([
            'proof' => 'required|file|image|max:10240',
            'tracking_no' => 'required|string',
            'delivery_exception' => 'required|string',
            'deliver_later_minutes' => 'nullable|integer',
            'deliver_later_until' => 'nullable|date|after:now',
            'deliver_later_reason' => 'nullable|string',
            'future_delivery_date' => 'nullable|date|after:today',
            'correct_address_link' => 'nullable|string',
        ]);

        // Normalize exception using enum
        $newException = DeliveryExceptionEnum::normalize($request->delivery_exception);

        if ($newException === DeliveryExceptionEnum::DELIVER_LATER_TODAY) {
            if (!$request->filled('deliver_later_minutes') && !$request->filled('deliver_later_until')) {
                return sendResponse("Validation error", [], false, [
                    "You must provide either deliver_later_minutes or deliver_later_until."
                ], 422);
            }
        }
        if ($newException === DeliveryExceptionEnum::FUTURE_DELIVERY && !$request->filled('future_delivery_date')) {
            return sendResponse("Validation error", [], false, [
                "Future delivery date is required."
            ], 422);
        }

        DB::beginTransaction();
        try {
            /** @var \App\Models\Shipment $shipment */
            $shipment = Shipment::with(['consignee', 'deliveryAddress'])
                ->where('tracking_no', trim($request->tracking_no))
                ->first();
            if (!$shipment) {
                return sendResponse("Shipment not found", [], false, ["Invalid tracking number"], 404);
            }

            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = uploadFile($request->file('proof'), 'public/return_proofs');
            }

            $validationService = new ShipmentValidationService();
            $lastSubtype = $this->getLastExceptionSubtypeLoose($shipment);

            if ($shipment->in_exception) {
                if ($lastSubtype !== DeliveryExceptionEnum::DELIVER_LATER_TODAY && $newException !== $lastSubtype) {
                    return sendResponse(
                        "Current exception is locked. Only DELIVER_LATER_TODAY can be changed.",
                        [],
                        false,
                        ["Only DELIVER_LATER_TODAY can be changed"],
                        422
                    );
                }
                if ($newException === $lastSubtype) {
                    return sendResponse("Shipment already marked with the same exception.", [], true, []);
                }
            }

            // --- Override: لو آخر استثناء كان DELIVER_LATER_TODAY اسمح بتغيير حتى لو الخدمة تمنع
            if (!$validationService->canCreateDeliveryException($shipment)) {
                if (!($shipment->in_exception && $lastSubtype === DeliveryExceptionEnum::DELIVER_LATER_TODAY)) {
                    $validationMessage = $validationService->getDeliveryExceptionValidationMessage($shipment);
                    return sendResponse($validationMessage, [], false, [$validationMessage], 422);
                }
            }

            $dynamicException = DeliveryException::where('name', $newException)->first();
            $status = "DELIVERY_EXCEPTION";
            $ofdCountLimit = setting("ofd_count");

            // ====== EXECUTION BLOCKS ======

            $customerTz = config('app.timezone') ?? 'Asia/Muscat';

            $historyDescription = "Marked as DELIVERY_EXCEPTION [$newException]";
            $deferStr = null;
            $futureDayStr = null;

            if ($newException === DeliveryExceptionEnum::NO_ANSWER) {
                $tz = $this->resolveShipmentTimezone($shipment);
                $dayStartUtc = \Carbon\Carbon::now($tz)->startOfDay()->utc();
                $dayEndUtc = \Carbon\Carbon::now($tz)->endOfDay()->utc();

                $hasContactSameDay = ShipmentHistory::query()
                    ->where('shipment_id', $shipment->id)
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(name) = ?', ['CONTACT'])
                            ->orWhereRaw('UPPER(type) = ?', ['CONTACT']);
                    })
                    ->whereBetween(\DB::raw('COALESCE(time, created_at, updated_at)'), [$dayStartUtc, $dayEndUtc])
                    ->exists();

                $callCount = (int) optional($shipment->shipment_delivery)->driver_call_count;

                if (!$hasContactSameDay && $callCount <= 0) {
                    return sendResponse(
                        "You must log a same-day call attempt before marking NO_ANSWER.",
                        [],
                        false,
                        ["Please log a CONTACT today for this shipment before NO_ANSWER."],
                        422
                    );
                }

                $user = auth()->user();
                $actorId = auth()->id();
                $userIsDriver = $user->hasRole('driver');

                // جِب السائق المعيّن الآن (user_id)
                $assignedDriverUserId = optional($shipment->current_driver)->user_id
                    ?? $shipment->driver_id
                    ?? null;

                $isAssignedDriver = $userIsDriver && $actorId && ($actorId == $assignedDriverUserId);

                $baseNoAnswerQ = ShipmentHistory::query()
                    ->where('shipment_id', $shipment->id)
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(type) = ?', [DeliveryExceptionEnum::NO_ANSWER])
                            ->orWhereRaw('UPPER(name) = ?', [DeliveryExceptionEnum::NO_ANSWER]);
                    })
                    ->select('id')
                    ->lockForUpdate();

                $prevNoAnswerByAssignedDriver = (clone $baseNoAnswerQ)
                    ->when($assignedDriverUserId, fn($q) => $q->where('operatorId', $assignedDriverUserId))
                    ->get()->count();

                $prevNoAnswerByOthers = (clone $baseNoAnswerQ)
                    ->when($assignedDriverUserId, function ($q) use ($assignedDriverUserId) {
                        $q->where(function ($qq) use ($assignedDriverUserId) {
                            $qq->whereNull('operatorId')
                                ->orWhere('operatorId', '!=', $assignedDriverUserId);
                        });
                    })
                    ->get()->count();

                $noAnswerCount = $isAssignedDriver
                    ? ($prevNoAnswerByAssignedDriver + 1)
                    : ($prevNoAnswerByOthers + 1);

                $historyDescription .= " | NO_ANSWER count: {$noAnswerCount}"
                    . ($isAssignedDriver ? " (by assigned driver)" : " (by non-assigned user)");

            }




            if ($newException === DeliveryExceptionEnum::DELIVER_LATER_TODAY) {
                // امسح future_delivery_date لو بتحول لـ later-today
                if ($shipment->shipment_delivery) {
                    $shipment->shipment_delivery->future_delivery_date = null;
                }

                if ($request->filled('deliver_later_until')) {
                    $deferLocal = \Carbon\Carbon::parse($request->deliver_later_until, $customerTz);
                    if (!$deferLocal->isFuture()) {
                        return sendResponse("Invalid Date", [], false, ["deliver_later_until must be in the future"], 422);
                    }
                } else {
                    $mins = (int) $request->deliver_later_minutes;
                    if ($mins < 0) {
                        return sendResponse("Invalid minutes", [], false, ["deliver_later_minutes must more than 0"], 422);
                    }
                    $deferLocal = \Carbon\Carbon::now($customerTz)->addMinutes($mins);
                }

                // خزّن UTC
                $deferUtc = $deferLocal->clone()->utc();
                if ($shipment->shipment_delivery) {
                    $shipment->shipment_delivery->deliver_later_until = $deferUtc;
                    if ($request->filled('deliver_later_reason')) {
                        $shipment->shipment_delivery->deliver_later_reason = $request->deliver_later_reason;
                    }
                    $shipment->shipment_delivery->save();
                }

                // History text (local + tz)
                $deferStr = $deferLocal->format('Y-m-d H:i:s');
                $historyDescription = "Marked as DELIVERY_EXCEPTION [DELIVER_LATER_TODAY] until {$deferStr} ({$customerTz})";

                // إشعار واتساب
                if ($shipment->consignee) {
                    $shipment->consignee->notify(new \App\Notifications\ShipmentDeliverLaterTodayNotification($shipment, $deferUtc));
                }
            }

            if ($newException === DeliveryExceptionEnum::FUTURE_DELIVERY) {
                $futureLocal = \Carbon\Carbon::parse($request->future_delivery_date, $customerTz)->startOfDay();
                if (!$futureLocal->isFuture()) {
                    return sendResponse("Invalid Date", [], false, ["Future delivery date must be in the future"], 422);
                }
                $futureUtc = $futureLocal->clone()->utc();

                if ($shipment->shipment_delivery) {
                    // امسح later-today لو بتحول لـ future
                    $shipment->shipment_delivery->deliver_later_until = null;
                    $shipment->shipment_delivery->deliver_later_reason = null;

                    // خزّن التاريخ مرة واحدة (UTC)
                    $shipment->shipment_delivery->future_delivery_date = $futureUtc;
                    $shipment->shipment_delivery->save();
                }

                $createdAt = $shipment->created_at instanceof \Carbon\Carbon ? $shipment->created_at : \Carbon\Carbon::parse($shipment->created_at);
                $daysDifference = $futureUtc->diffInDays($createdAt);

                $futureDayStr = $futureLocal->format('l d F Y');
                $historyDescription = "Marked as DELIVERY_EXCEPTION [FUTURE_DELIVERY] (Scheduled day: {$futureDayStr} {$customerTz})";

                if ($shipment->consignee) {
                    $shipment->consignee->notify(new \App\Notifications\ShipmentFutureDeliveryNotification($shipment, $futureUtc));
                }

                if ($shipment->consignee) {
                    $title = "📦 Shipment #{$shipment->tracking_no} - Future Delivery";
                    $consigneeContent = "📦 Shipment #{$shipment->tracking_no}\n" .
                        "📅 Future Delivery Scheduled\n" .
                        "📆 Scheduled in: {$daysDifference} day(s)\n" .
                        "📍 We'll deliver to: {$shipment->consignee->address}";

                    create_notification(
                        $shipment->consignee,
                        $title,
                        $consigneeContent,
                        [
                            'shipment_id' => $shipment->id,
                            'tracking_no' => $shipment->tracking_no,
                            'scheduled_date' => optional($shipment->shipment_delivery)->future_delivery_date,
                            'days_until_delivery' => $daysDifference,
                            'delivery_address' => $shipment->consignee->address,
                            'priority' => 'medium',
                            'action_url' => '/tracking/' . $shipment->tracking_no
                        ],
                        'future_delivery_scheduled',
                        false
                    );
                }
            }
            $hasAddressUpdate = \DB::table('old_addresses')
                ->where('shipment_id', $shipment->id)
                ->where(function ($q) {
                    // اعتبره "تم الإرسال" لو Approved = 1
                    // أو لو pending (لسه لا approved ولا rejected)
                    $q->where('approved', 1)
                        ->orWhere(function ($qq) {
                        $qq->where('approved', 0)->where('rejected', 0);
                    });
                })
                ->exists();


            try {
                $forceCrmFor = DeliveryExceptionEnum::getCrmEscalationTypes();

                $shouldMoveToCrm = in_array($newException, $forceCrmFor, true);

                if ((int) (optional($shipment->shipment_delivery)->ofd_count) > (int) $ofdCountLimit) {
                    $shouldMoveToCrm = true;
                }

                if (!$shouldMoveToCrm && $dynamicException) {
                    $shouldMoveToCrm = (bool) $dynamicException->move_to_crm;
                }

                if ($newException === DeliveryExceptionEnum::WRONG_CITY && $hasAddressUpdate) {
                    $shouldMoveToCrm = false;
                }

                if ($shouldMoveToCrm) {
                    $openTask = \App\Models\CrmTask::where('shipment_id', $shipment->id)
                        ->whereIn('status', ['created', 'to_call', 'hold'])
                        ->latest('id')
                        ->first();

                    if ($openTask) {
                        $openTask->update([
                            'title' => $newException,
                            'note' => $historyDescription,
                            'updated_by' => auth()->id(),
                        ]);
                    } else {
                        \App\Models\CrmTask::create([
                            'shipment_id' => $shipment->id,
                            'title' => $newException,
                            'note' => $historyDescription,
                            'updated_by' => auth()->id(),
                            'status' => 'created',
                            'owner_id' => $shipment->owner_id,
                            'owner_type' => $shipment->owner_type,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed creating CRM task', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }



            $shipment->in_exception = true;
            $shipment->owner_type = facility("type");
            $shipment->owner_id = facility("id");
            $shipment->save();
            $noAnswerCount = null;

            if ($newException === DeliveryExceptionEnum::NO_ANSWER) {
                $hasAnyContact = \App\Models\ShipmentHistory::query()
                    ->where('shipment_id', $shipment->id)
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(name) = ?', ['CONTACT'])
                            ->orWhereRaw('UPPER(type) = ?', ['CONTACT']);
                    })
                    ->exists();

                $callCount = (int) optional($shipment->shipment_delivery)->driver_call_count;

                if (!$hasAnyContact && $callCount <= 0) {
                    return sendResponse(
                        "You must log a call attempt before marking NO_ANSWER.",
                        [],
                        false,
                        ["Please use the call logging endpoint before NO_ANSWER."],
                        422
                    );
                }

                $prevNoAnswer = \App\Models\ShipmentHistory::query()
                    ->where('shipment_id', $shipment->id)
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(type) = ?', [DeliveryExceptionEnum::NO_ANSWER])
                            ->orWhereRaw('UPPER(name) = ?', [DeliveryExceptionEnum::NO_ANSWER]);
                    })
                    ->select('id')
                    ->lockForUpdate()
                    ->get()
                    ->count();

                $noAnswerCount = $prevNoAnswer + 1;
                $historyDescription .= " | NO_ANSWER count: {$noAnswerCount}";
            }
            
            $shipment->update(['exception_type' => $newException]);

            // ====== History
            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => "DELIVERY_EXCEPTION",
                "description" => $historyDescription,
                "type" => $newException, // subtype
                "proof" => $proofPath,
                "operationHub" => facility("name") ?? null,
                "operationHubType" => facility("type") ?? null,
                "operatorId" => auth()->id() ?? null,
                "operatorInfo" => (auth()->user()->name ?? 'system'),
                "data" => json_encode([
                    "exception_subtype" => $newException,
                    "deliver_later_until_utc" => $newException === DeliveryExceptionEnum::DELIVER_LATER_TODAY
                        ? optional($shipment->shipment_delivery)->deliver_later_until?->toDateTimeString()
                        : null,
                    "deliver_later_until_local" => $newException === DeliveryExceptionEnum::DELIVER_LATER_TODAY ? $deferStr : null,
                    "future_delivery_date_utc" => $newException === DeliveryExceptionEnum::FUTURE_DELIVERY
                        ? optional($shipment->shipment_delivery)->future_delivery_date?->toDateTimeString()
                        : null,
                    "future_delivery_day_local" => $newException === DeliveryExceptionEnum::FUTURE_DELIVERY ? $futureDayStr : null,
                    "timezone" => $customerTz,
                    "no_answer_count" => $noAnswerCount,
                ]),
            ]);

            // ====== إدراج في delivery_exceptions للحالات المطلوبة

            // $persistableExceptions = ['WRONG_CITY', 'WRONG_NUMBER', 'NO_ANSWER', 'CANCELLED'];

            // if (
            //     in_array($newException, $persistableExceptions, true)
            //     && !($newException === 'WRONG_CITY' && $hasAddressUpdate) // تجنّب الإنشاء لو فيه Update Address
            // ) {
            //     CrmTask::create([
            //         'shipment_id' => $shipment->id,
            //         'title' => $newException,
            //         'note' => $historyDescription,
            //         'updated_by' => auth()->id(),
            //         'status' => 'created',
            //         'owner_id' => $shipment->owner_id,
            //         'owner_type' => $shipment->owner_type,
            //     ]);
            // }


            // ====== تحديثات السائق / حالة الأوردر
            updateShipmentStatus($shipment->id, "DELIVERY_EXCEPTION");

            \App\Models\DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                "status" => "not_delivered"
            ]);

            \App\Models\DriverShipmentAssignment::where('shipment_id', $shipment->id)->update([
                'delivered_at' => null,
                "confirmed_at" => null
            ]);
            if ($newException === 'CANCELLED') {
                DriverBonusesTransaction::where('shipment_id', $shipment->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'description' => 'Pickup bonus was cancelled.',
                    ]);
            }


            // ====== Transaction مرتبطة بالاستثناء (باستخدام نفس الـ proof)
            try {
                $userId = auth()->id();
                if (!$userId) {
                    throw new \RuntimeException('No authenticated user to attribute the exception to.');
                }

                \App\Models\Transaction::create([
                    'from_type' => \App\Models\User::class,
                    'from_id' => $userId,

                    'to_type' => null,
                    'to_id' => null,

                    'shipment_id' => $shipment->id,

                    'amount' => 0,
                    'type' => 'Exception',
                    'reference' => $newException,
                    'description' => $historyDescription,

                    'created_by' => $userId,
                    'receipt_path' => $proofPath,
                    'receipt_uploaded_by' => $userId,
                    'receipt_uploaded_at' => now(),
                ]);
            } catch (\Throwable $txe) {
                \Log::warning('Failed creating exception transaction', [
                    'shipment_id' => $shipment->id,
                    'error' => $txe->getMessage(),
                ]);
            }

            DB::commit();

            // ====== Handle Address Update for WRONG_CITY with correct_address_link
            $addressUpdateResult = null;
            if ($newException === DeliveryExceptionEnum::WRONG_CITY && $request->filled('correct_address_link')) {
                /** @var \App\Models\Shipment $shipment */
                $shipment = Shipment::with(['consignee', 'deliveryAddress'])
                    ->where('tracking_no', $request->tracking_no)
                    ->firstOrFail();

                // Use the shared service method to create address update request
                $addressService = new AddressService();
                $addressUpdateResult = $addressService->createAddressUpdateRequest(
                    $shipment,
                    $request->input('correct_address_link'),
                    'Driver reported WRONG_CITY and provided correct address',
                    'driver_app_wrong_city',
                    $proofPath // Pass the uploaded proof
                );
            }

            // Build response message and data
            $responseMessage = "Shipment marked with exception.";
            $responseData = [];

            if ($addressUpdateResult) {
                $responseMessage .= " Address update request submitted for approval.";
                $responseData['address_update'] = $addressUpdateResult;
            }

            return sendResponse($responseMessage, $responseData, true, []);

        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error.", [], false, [$e->getMessage()], 500);
        }
    }


    // public function handleShipmentException(Request $request)
    // {
    //     $request->validate([
    //         'proof' => 'required|file|image|max:10240',
    //         'tracking_no' => 'required|string',
    //         'delivery_exception' => 'required|string',
    //         'deliver_later_minutes' => 'nullable|integer',
    //         'deliver_later_until' => 'nullable|date|after:now',
    //         'deliver_later_reason' => 'nullable|string',
    //         'future_delivery_date' => 'nullable|date|after:today',
    //     ]);

    //     $exception = trim($request->delivery_exception);
    //     if ($exception === 'DELIVER_LATER_TODAY') {
    //         if (!$request->filled('deliver_later_minutes') && !$request->filled('deliver_later_until')) {
    //             return sendResponse("Validation error", [], false, [
    //                 "You must provide either deliver_later_minutes or deliver_later_until."
    //             ], 422);
    //         }
    //     }
    //     if ($exception === 'FUTURE_DELIVERY' && !$request->filled('future_delivery_date')) {
    //         return sendResponse("Validation error", [], false, [
    //             "Future delivery date is required."
    //         ], 422);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $shipment = Shipment::where('tracking_no', trim($request->tracking_no))->first();
    //         if (!$shipment) {
    //             return sendResponse("Shipment not found", [], false, ["Invalid tracking number"], 404);
    //         }
    //         $mapToUpper = [
    //             'wrong_city' => 'WRONG_CITY',
    //             'wrong_answer' => 'WRONG_ANSWER',
    //             'no_answer' => 'NO_ANSWER',
    //             'cancelled' => 'CANCELLED',
    //             'deliver_later_today' => 'DELIVER_LATER_TODAY',
    //             'future_delivery' => 'FUTURE_DELIVERY',
    //         ];

    //         $validationService = new ShipmentValidationService();

    //         $newException = $exception;
    //         $lastSubtype = $this->getLastExceptionSubtypeLoose($shipment); // helper موجود عندك
    //         $proofPath = null;
    //         if ($request->hasFile('proof')) {
    //             $proofPath = uploadFile($request->file('proof'), 'public/return_proofs');
    //         }
    //         // --- Lock logic: only DELIVER_LATER_TODAY is mutable
    //         if ($shipment->in_exception) {
    //             if ($lastSubtype !== 'DELIVER_LATER_TODAY' && $newException !== $lastSubtype) {
    //                 return sendResponse(
    //                     "Current exception is locked. Only DELIVER_LATER_TODAY can be changed.",
    //                     [],
    //                     false,
    //                     ["Only DELIVER_LATER_TODAY can be changed"],
    //                     422
    //                 );
    //             }
    //             if ($newException === $lastSubtype) {
    //                 return sendResponse("Shipment already marked with the same exception.", [], true, []);
    //             }
    //         }

    //         // --- Validation service override if last was DELIVER_LATER_TODAY
    //         if (!$validationService->canCreateDeliveryException($shipment)) {
    //             if (!($shipment->in_exception && $lastSubtype === 'DELIVER_LATER_TODAY')) {
    //                 $validationMessage = $validationService->getDeliveryExceptionValidationMessage($shipment);
    //                 return sendResponse($validationMessage, [], false, [$validationMessage], 422);
    //             }
    //         }

    //         $originalStatus = $newException;
    //         $dynamicException = DeliveryException::where('name', $originalStatus)->first();
    //         $status = "DELIVERY_EXCEPTION";
    //         $ofdCountLimit = setting("ofd_count");

    //         // ====== EXECUTION BLOCKS ======

    //         // Common customer timezone
    //         $customerTz = $shipment->consignee->timezone ?? 'Asia/Muscat';

    //         // Prepare variables for history text
    //         $historyDescription = "Marked as DELIVERY_EXCEPTION [$newException]";
    //         $deferStr = null;
    //         $futureDayStr = null;

    //         if ($originalStatus === 'DELIVER_LATER_TODAY') {
    //             // clear future when switching to later-today
    //             $shipment->shipment_delivery->future_delivery_date = null;

    //             if ($request->filled('deliver_later_until')) {
    //                 $deferLocal = Carbon::parse($request->deliver_later_until, $customerTz);
    //                 if (!$deferLocal->isFuture()) {
    //                     return sendResponse("Invalid Date", [], false, ["deliver_later_until must be in the future"], 422);
    //                 }
    //             } else {
    //                 $mins = (int) $request->deliver_later_minutes;
    //                 if ($mins < 0) {
    //                     return sendResponse("Invalid minutes", [], false, ["deliver_later_minutes must more than 0"], 422);
    //                 }
    //                 $deferLocal = Carbon::now($customerTz)->addMinutes($mins);
    //             }

    //             // Store UTC
    //             $deferUtc = $deferLocal->clone()->utc();
    //             $shipment->shipment_delivery->deliver_later_until = $deferUtc;
    //             if ($request->filled('deliver_later_reason')) {
    //                 $shipment->shipment_delivery->deliver_later_reason = $request->deliver_later_reason;
    //             }
    //             $shipment->shipment_delivery->save();

    //             // History text (local + tz)
    //             $deferStr = $deferLocal->format('Y-m-d H:i:s');
    //             $historyDescription = "Marked as DELIVERY_EXCEPTION [DELIVER_LATER_TODAY] until {$deferStr} ({$customerTz})";

    //             // WhatsApp notify (existing class)
    //             if ($shipment->consignee) {
    //                 $shipment->consignee->notify(new \App\Notifications\ShipmentDeliverLaterTodayNotification($shipment, $deferUtc));
    //             }
    //         }

    //         if ($originalStatus === 'FUTURE_DELIVERY') {
    //             // Normalize: convert merchant's chosen day to UTC start-of-day
    //             $futureLocal = Carbon::parse($request->future_delivery_date, $customerTz)->startOfDay();
    //             if (!$futureLocal->isFuture()) {
    //                 return sendResponse("Invalid Date", [], false, ["Future delivery date must be in the future"], 422);
    //             }
    //             $futureUtc = $futureLocal->clone()->utc();

    //             // clear later-today when switching to future
    //             $shipment->shipment_delivery->deliver_later_until = null;
    //             $shipment->shipment_delivery->deliver_later_reason = null;

    //             // store UTC once
    //             $shipment->shipment_delivery->future_delivery_date = $futureUtc;
    //             $shipment->shipment_delivery->save();

    //             // Days diff (as before)
    //             $createdAt = $shipment->created_at instanceof Carbon ? $shipment->created_at : Carbon::parse($shipment->created_at);
    //             $daysDifference = $futureUtc->diffInDays($createdAt);

    //             $futureDayStr = $futureLocal->format('l d F Y'); // English
    //             $historyDescription = "Marked as DELIVERY_EXCEPTION [FUTURE_DELIVERY] (Scheduled day: {$futureDayStr} {$customerTz})";

    //             // WhatsApp for future delivery (same chosen day)
    //             if ($shipment->consignee) {
    //                 $shipment->consignee->notify(new ShipmentFutureDeliveryNotification($shipment, $futureUtc));
    //             }

    //             // keep your existing customer notification
    //             if ($shipment->consignee) {
    //                 $title = "📦 Shipment #{$shipment->tracking_no} - Future Delivery";
    //                 $consigneeContent = "📦 Shipment #{$shipment->tracking_no}\n" .
    //                     "📅 Future Delivery Scheduled\n" .
    //                     "📆 Scheduled in: {$daysDifference} day(s)\n" .
    //                     "📍 We'll deliver to: {$shipment->consignee->address}";

    //                 create_notification(
    //                     $shipment->consignee,
    //                     $title,
    //                     $consigneeContent,
    //                     [
    //                         'shipment_id' => $shipment->id,
    //                         'tracking_no' => $shipment->tracking_no,
    //                         'scheduled_date' => $shipment->shipment_delivery->future_delivery_date,
    //                         'days_until_delivery' => $daysDifference,
    //                         'delivery_address' => $shipment->consignee->address,
    //                         'priority' => 'medium',
    //                         'action_url' => '/tracking/' . $shipment->tracking_no
    //                     ],
    //                     'future_delivery_scheduled',
    //                     false
    //                 );
    //             }
    //         }

    //         // ====== CRM Escalation (unchanged)
    //         if (
    //             $shipment->shipment_delivery->ofd_count > $ofdCountLimit ||
    //             $originalStatus === 'CANCELLED' ||
    //             ($dynamicException && $dynamicException->move_to_crm)
    //         ) {
    //             CrmTask::insert([
    //                 'title' => "Shipment requires CRM attention: $originalStatus",
    //                 'shipment_id' => $shipment->id,
    //                 'status' => 'created',
    //                 'owner_id' => $shipment->owner_id,
    //                 'owner_type' => $shipment->owner_type,
    //             ]);
    //         }

    //         // ====== Flags (unchanged)
    //         $shipment->in_exception = true;
    //         $shipment->owner_type = facility("type");
    //         $shipment->owner_id = facility("id");
    //         $shipment->save();

    //         // ====== History (keep your keys + add correct time/day text)
    //         shipmentHistory([
    //             "shipment_id" => $shipment->id,
    //             "status" => "DELIVERY_EXCEPTION",
    //             "description" => $historyDescription,
    //             "type" => $newException, // you store subtype here
    //             "proof" => uploadFile($request->proof, 'public/return_proofs'),
    //             "operationHub" => facility("name") ?? null,
    //             "operationHubType" => facility("type") ?? null,
    //             "operatorId" => auth()->id() ?? null,
    //             "operatorInfo" => (auth()->user()->name ?? 'system'),
    //             "data" => json_encode([
    //                 "exception_subtype" => $newException,
    //                 "deliver_later_until_utc" => $newException === 'DELIVER_LATER_TODAY'
    //                     ? optional($shipment->shipment_delivery->deliver_later_until)->toDateTimeString()
    //                     : null,
    //                 "deliver_later_until_local" => $newException === 'DELIVER_LATER_TODAY' ? $deferStr : null,
    //                 "future_delivery_date_utc" => $newException === 'FUTURE_DELIVERY'
    //                     ? optional($shipment->shipment_delivery->future_delivery_date)->toDateTimeString()
    //                     : null,
    //                 "future_delivery_day_local" => $newException === 'FUTURE_DELIVERY' ? $futureDayStr : null,
    //                 "timezone" => $customerTz,
    //             ]),
    //         ]);

    //         // ====== Driver updates (unchanged)
    //         updateShipmentStatus($shipment->id, "DELIVERY_EXCEPTION");

    //         DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
    //             "status" => "not_delivered"
    //         ]);

    //         DriverShipmentAssignment::where('shipment_id', $shipment->id)->update([
    //             'delivered_at' => null,
    //             "confirmed_at" => null
    //         ]);
    //         try {
    //             $userId = auth()->id();
    //             if (!$userId) {

    //                 throw new \RuntimeException('No authenticated user to attribute the exception to.');
    //             }

    //             $proofPath = null;
    //             if ($request->hasFile('proof')) {
    //                 $proofPath = uploadFile($request->file('proof'), 'public/return_proofs');
    //             }

    //             Transaction::create([
    //                 'from_type' => \App\Models\User::class,
    //                 'from_id' => $userId,

    //                 'to_type' => null,
    //                 'to_id' => null,

    //                 'shipment_id' => $shipment->id,

    //                 'amount' => 0,
    //                 'type' => 'Exception',
    //                 'reference' => $newException,
    //                 'description' => $historyDescription,

    //                 'created_by' => $userId,
    //                 'receipt_path' => $proofPath,
    //                 'receipt_uploaded_by' => $userId,
    //                 'receipt_uploaded_at' => now(),

    //             ]);
    //         } catch (\Throwable $txe) {
    //             \Log::warning('Failed creating exception transaction', [
    //                 'shipment_id' => $shipment->id,
    //                 'error' => $txe->getMessage(),
    //             ]);
    //         }


    //         DB::commit();
    //         return sendResponse("Shipment marked with exception.", [], true, []);

    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return sendResponse("Error.", [], false, [$e->getMessage()], 500);
    //     }
    // }

    private function getLastExceptionSubtypeLoose(Shipment $shipment): ?string
    {
        $last = ShipmentHistory::where('shipment_id', $shipment->id)
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return null;
        }

        if (!empty($last->data)) {
            try {
                $payload = is_array($last->data) ? $last->data : json_decode($last->data, true);
                if (!empty($payload['exception_subtype'])) {
                    return $payload['exception_subtype'];
                }
            } catch (\Throwable $e) {
            }
        }

        if (!empty($last->type)) {
            return $last->type;
        }

        if (!empty($last->description) && preg_match('/\[(.*?)\]/', $last->description, $m)) {
            return $m[1] ?? null;
        }

        return null;
    }




}
