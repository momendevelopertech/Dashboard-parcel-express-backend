<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\UpdateDriverAppSettingRequest;
use App\Http\Resources\DriverAppSettingResource;
use App\Models\DriverAppSetting;
use Illuminate\Database\QueryException;

class DriverAppSettingController extends Controller
{
    /**
     * Get All Settings
     *
     * @OA\Get(
     *   path="/driver_app_settings/",
     *   tags={"Driver App"},
     *   summary="Get all driver application settings",
     *   @OA\Response(
     *     response=200,
     *     description="Settings retrieved successfully",
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="No settings found"
     *   )
     * )
     */
    public function index()
    {
        $settings = DriverAppSetting::first();

        if (!$settings) {
            return sendResponse("Settings not found.", [], [], 404);
        }

        return sendResponse("Settings retrieved successfully.", new DriverAppSettingResource($settings));
    }

    /**
     * Update Settings
     *
     * @OA\Patch(
     *   path="/driver_app_settings/",
     *   tags={"Driver App"},
     *   summary="Update driver application settings",
     *   description="Accepts multipart/form-data for image updates",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Settings updated successfully",
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error"
     *   )
     * )
     */
    public function update(UpdateDriverAppSettingRequest $request)
    {
        $request->validated();

        try {
            $data = $request->all();
            $setting = DriverAppSetting::first();
            if ($request->has('main_screen_image')) {
                $data['main_screen_image'] = uploadFile($request->main_screen_image);
            }

            $setting->update($data);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }

        return sendResponse("Settings updated successfully.", new DriverAppSettingResource($setting));
    }

    /**
     * Get Specific Setting
     *
     * @OA\Get(
     *   path="/driver_app_settings/{key}",
     *   tags={"Driver App"},
     *   summary="Get specific setting value by key",
     *   @OA\Parameter(
     *     name="key",
     *     in="path",
     *     required=true,
     *     description="Setting key to retrieve",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Value retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="value", type="string")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Settings not found"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid key"
     *   )
     * )
     */
    public function get($key)
    {
        $settings = DriverAppSetting::first();

        if (!$settings) {
            return sendResponse("Settings not found.", [], [], 404);
        }
        if (!array_key_exists($key, $settings->getAttributes())) {
            return sendResponse("Invalid key.", [], ["Requested key '{$key}' does not exist"], 422);
        }

        return sendResponse("Value retrieved successfully.", $settings->$key);
    }
}
