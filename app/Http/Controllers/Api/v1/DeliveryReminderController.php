<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DeliveryReminder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Jobs\SendDeliveryReminder;
use App\Helpers\Helpers;

/**
 * @OA\Tag(name="Other", description="Manage delivery reminders")
 */
class DeliveryReminderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/delivery-reminders",
     *     summary="Retrieve delivery reminders",
     *     description="Retrieve a list of delivery reminders.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="shipment_id",
     *         in="query",
     *         description="Filter by shipment ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="recipient",
     *         in="query",
     *         description="Filter by recipient (driver or customer)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, sent, failed)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery reminders retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = DeliveryReminder::query();

        if ($o = $request->query('shipment_id'))   $query->where('shipment_id', $o);
        if ($r = $request->query('recipient'))  $query->where('recipient', $r);
        if ($s = $request->query('status'))     $query->where('status', $s);

        $reminders = $query->orderBy('reminder_time')->paginate(15);
        
        return sendResponse(
            'Delivery reminders retrieved successfully',
            $reminders,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/delivery-reminders",
     *     summary="Create a delivery reminder",
     *     description="Create a new delivery reminder.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="string", description="Shipment ID"),
     *             @OA\Property(property="recipient", type="string", description="Recipient (driver or customer)", enum={"driver", "customer"}),
     *             @OA\Property(property="recipient_id", type="integer", description="Recipient ID"),
     *             @OA\Property(property="reminder_time", type="string", format="date", description="Reminder time"),
     *             @OA\Property(property="methods", type="array", description="Reminder methods (email, sms, in_system)", @OA\Items(type="string")),
     *             @OA\Property(property="enabled", type="boolean", description="Enabled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Delivery reminder created successfully"
     *     ),
     *      @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shipment_id'      => 'required|string',
            'recipient'     => ['required', Rule::in(['driver','customer'])],
            'recipient_id'  => 'nullable|integer',
            'reminder_time' => 'required|date',
            'methods'       => 'required|array',
            'methods.*'     => 'in:email,sms,in_system',
            'enabled'       => 'boolean',
        ]);

        $reminder = DeliveryReminder::create($data);

        if ($reminder->enabled) {
            SendDeliveryReminder::dispatch($reminder)
                ->delay($reminder->reminder_time);
        }

        return sendResponse(
            'Delivery reminder created successfully',
            $reminder,
            true,
            [],
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/delivery-reminders",
     *     summary="Update a delivery reminder",
     *     description="Update an existing delivery reminder.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", description="Status (pending, sent, failed)"),
     *             @OA\Property(property="enabled", type="boolean", description="Enabled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery reminder updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'status'  => ['sometimes', Rule::in(['pending','sent','failed'])],
            'enabled' => 'sometimes|boolean',
        ]);

        $reminder = DeliveryReminder::findOrFail($request->id);
        $reminder->update($data);
        
        return sendResponse(
            'Delivery reminder updated successfully',
            $reminder,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/delivery-reminders/toggle",
     *     summary="Toggle a delivery reminder",
     *     description="Toggle the enabled status of a delivery reminder.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the reminder to toggle")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery reminder toggled successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function toggle(Request $request)
    {
        $reminder = DeliveryReminder::findOrFail($request->id);
        $reminder->enabled = !$reminder->enabled;
        if ($reminder->enabled && $reminder->status === 'pending') {
            SendDeliveryReminder::dispatch($reminder)
                ->delay($reminder->reminder_time);
        }
        $reminder->save();
        
        return sendResponse(
            'Delivery reminder toggled successfully',
            $reminder,
            true,
            [],
            200
        );
    }
}
