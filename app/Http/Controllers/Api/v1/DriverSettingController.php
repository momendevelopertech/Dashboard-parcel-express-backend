<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\DriverSetting;
use Illuminate\Http\Request;

class DriverSettingController extends Controller
{
    protected $id;

    public function __construct(Request $request)
    {
        if (!$request->has('id') || empty($request->id)) {
            abort(response()->json([
                'message' => 'Validation Error.',
                'errors' => ['id' => ['The id field is required.']]
            ], 422));
        }

        $this->id = $request->id;
    }

    /**
     * Get Driver Settings
     *
     * Retrieve all settings for the specified driver.
     * Driver ID must be provided in the request.
     *
     * @OA\Get(
     *     path="/driver_settings",
     *     summary="Get driver settings",
     *     description="Retrieve all settings for specified driver",
     *     operationId="getDriverSettings",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Driver ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings retrieved successfully."),
     *             @OA\Property(
                 property="data", 
                 type="object",
                 description="Driver settings object with configuration values"
             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Settings not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Driver ID required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index()
    {
        $settings = DriverSetting::where('driver_id', $this->id)->first();

        if (!$settings) {
            return sendResponse("Settings not found.", [], [], 404);
        }

        return sendResponse("Settings retrieved successfully.", new GeneralResource($settings));
    }

    /**
     * Get Specific Driver Setting
     *
     * Retrieve a specific setting value for the driver by key name.
     * Returns the value of the requested setting key.
     *
     * @OA\Get(
     *     path="/driver_settings/get/{key}",
     *     summary="Get specific driver setting",
     *     description="Retrieve specific setting value by key for driver",
     *     operationId="getDriverSetting",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Setting key name"
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Driver ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting value retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Value retrieved successfully."),
     *             @OA\Property(property="data", description="Setting value (type varies)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Settings not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid key or missing driver ID",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function get($key)
    {
        $settings = DriverSetting::where('driver_id', $this->id)->first();

        if (!$settings) {
            return sendResponse("Settings not found.", [], [], 404);
        }
        if (!array_key_exists($key, $settings->getAttributes())) {
            return sendResponse("Invalid key.", [], ["Requested key '{$key}' does not exist"], 422);
        }

        return sendResponse("Value retrieved successfully.", $settings->$key);
    }
}
