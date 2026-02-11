<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ScheduledMessage;
use App\Models\ScheduledMessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Other", description="Scheduled Message Management")
 * @OA\Server(url="api/")
 */
class ScheduledMessageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/messages/scheduled",
     *     summary="Retrieve all scheduled messages",
     *     description="Returns a list of all scheduled messages with their logs.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled messages retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $scheduledMessages = ScheduledMessage::with('logs')
            ->get();

        return sendResponse('Scheduled messages retrieved successfully', $scheduledMessages);
    }

    /**
     * @OA\Post(
     *     path="/messages/scheduled",
     *     summary="Schedule a new message",
     *     description="Schedules a new message with specified details.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="message_title", type="string", description="Title of the message", example="Important Announcement"),
     *             @OA\Property(property="channel", type="string", description="Channel to send the message (sms, email, whatsapp)", example="email"),
     *             @OA\Property(property="body", type="string", description="Body of the message", example="This is an important announcement."),
     *             @OA\Property(
     *                 property="recipients",
     *                 type="array",
     *                 description="Array of recipient contacts",
     *                 @OA\Items(type="string", description="Email address of a recipient", example="recipient1@example.com")
     *             ),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", description="Date and time to schedule the message", example="2024-07-27T10:00:00"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message scheduled successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to schedule message"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message_title' => 'required|string|max:255',
            'channel' => 'required|in:sms,email,whatsapp',
            'body' => 'required|string',
            'recipients' => 'required|array',
            'scheduled_at' => 'required|date|after:now',
        ]);
        $validated['recipients'] = array_filter($validated['recipients']);

        DB::beginTransaction();
        try {
            $scheduledMessage = ScheduledMessage::create([
                'message_title' => $validated['message_title'],
                'channel' => $validated['channel'],
                'body' => $validated['body'],
                'recipients' => $validated['recipients'],
                'scheduled_at' => $validated['scheduled_at'],
            ]);

            // Create logs for each recipient
            foreach ($validated['recipients'] as $recipient) {
                ScheduledMessageLog::create([
                    'scheduled_message_id' => $scheduledMessage->id,
                    'recipient_contact' => $recipient,
                    'status' => 'pending',
                ]);
            }

            DB::commit();
            return sendResponse('Message scheduled successfully', $scheduledMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse('Failed to schedule message', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/messages/scheduled",
     *     summary="Update a scheduled message",
     *     description="Updates a scheduled message with new details.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the scheduled message to update"),
     *             @OA\Property(property="message_title", type="string", description="Title of the message"),
     *             @OA\Property(property="channel", type="string", description="Channel to send the message (sms, email, whatsapp)"),
     *             @OA\Property(property="body", type="string", description="Body of the message"),
     *             @OA\Property(property="recipients", type="array", description="Array of recipient contacts",
     *             @OA\Items(type="string", description="Email address of a recipient", example="recipient1@example.com")
     *             ),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", description="Date and time to schedule the message"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update message"
     *     )
     * )
     */
    public function update(Request $request)
    {
        $id = $request->input("id");
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        $validated = $request->validate([
            'message_title' => 'string|max:255',
            'channel' => 'in:sms,email,whatsapp',
            'body' => 'string',
            'recipients' => 'array',
            'scheduled_at' => 'date|after:now',
        ]);

        DB::beginTransaction();
        try {
            $scheduledMessage->update($validated);

            if (isset($validated['recipients'])) {
                // Delete existing logs and create new ones
                $scheduledMessage->logs()->delete();
                foreach ($validated['recipients'] as $recipient) {
                    ScheduledMessageLog::create([
                        'scheduled_message_id' => $scheduledMessage->id,
                        'recipient_contact' => $recipient,
                        'status' => 'pending',
                    ]);
                }
            }

            DB::commit();
            return sendResponse('Message updated successfully', $scheduledMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse('Failed to update message', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/messages/scheduled/delete",
     *     summary="Cancel a scheduled message",
     *     description="Cancels a scheduled message.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the scheduled message to cancel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message cancelled successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled message not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function destroy(Request $request)
    {
        $id = $request->input("id");
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        $scheduledMessage->delete();
        return sendResponse('Message cancelled successfully', []);
    }

    /**
     * @OA\Get(
     *     path="/messages/scheduled/logs/{id}",
     *     summary="Retrieve logs for a scheduled message",
     *     description="Returns the logs for a specific scheduled message.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scheduled message",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message logs retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled message not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function logs(Request $request, $id)
    {
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        $logs = $scheduledMessage->logs()->get();
        return sendResponse('Message logs retrieved successfully', $logs);
    }
}
