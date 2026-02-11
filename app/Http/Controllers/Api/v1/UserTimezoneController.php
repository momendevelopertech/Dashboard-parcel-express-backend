<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\TimezoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTimezoneController extends Controller
{
    protected TimezoneService $timezoneService;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    /**
     * @OA\Get(
     *     path="/api/user/timezone",
     *     tags={"User Settings"},
     *     summary="Get current user's timezone preference",
     *     description="Returns the user's timezone preference and effective timezone",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Timezone retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User timezone retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="timezone", type="string", example="Asia/Muscat"),
     *                 @OA\Property(property="effective_timezone", type="string", example="Asia/Muscat"),
     *                 @OA\Property(
     *                     property="available_timezones",
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function show()
    {
        $user = Auth::user();

        return sendResponse('User timezone retrieved successfully.', [
            'timezone' => $user->timezone ?? config('app.timezone'),
            'effective_timezone' => $this->timezoneService->getEffectiveTimezone($user),
            'available_timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/user/timezone",
     *     tags={"User Settings"},
     *     summary="Update user's timezone preference",
     *     description="Updates the authenticated user's timezone preference",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="timezone", type="string", example="America/New_York", description="Valid timezone identifier")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Timezone updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timezone updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="timezone", type="string", example="America/New_York"),
     *                 @OA\Property(property="effective_timezone", type="string", example="America/New_York")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid timezone",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(Request $request)
    {
        $request->validate([
            'timezone' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$this->timezoneService->isValidTimezone($request->timezone)) {
            return sendResponse('Invalid timezone.', [], false, ['The provided timezone is not valid.'], 422);
        }

        $user->timezone = $request->timezone;
        $user->save();

        return sendResponse('Timezone updated successfully.', [
            'timezone' => $user->timezone,
            'effective_timezone' => $this->timezoneService->getEffectiveTimezone($user),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/timezones",
     *     tags={"User Settings"},
     *     summary="Get list of available timezones",
     *     description="Returns all available timezone identifiers with offset information",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Timezones retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timezones retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="identifier", type="string", example="Asia/Muscat"),
     *                     @OA\Property(property="offset", type="string", example="+04:00"),
     *                     @OA\Property(property="offset_seconds", type="integer", example=14400),
     *                     @OA\Property(property="display_name", type="string", example="Asia/Muscat (UTC+04:00)")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function timezones()
    {
        $timezones = $this->timezoneService->getAvailableTimezones();

        return sendResponse('Timezones retrieved successfully.', $timezones);
    }
}
