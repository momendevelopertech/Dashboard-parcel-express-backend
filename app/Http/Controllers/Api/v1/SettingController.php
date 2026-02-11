<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreSettingRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="WMS", description="Manage settings")
 * @OA\Server(url="/api/v1")
 */
class SettingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/settings/get/{key}",
     *     summary="Get a setting by key",
     *     description="Retrieve a single setting by its key.",
     *     tags={"WMS"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         description="The key of the setting to retrieve",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occured."
     *     )
     * )
     */
    public function get($key)
    {
        $setting = Setting::where('key', $key)->get();
        return sendResponse("Settings reterived successfully.", new SettingResource($setting), []);
    }


    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $settings = Setting::query();

        $settings = $settings->orderBy('id', 'desc');

        if (request()->has('query')) {
            $query = strtolower(request()->input('query'));
            $settings->where('key', 'like', '%' . $query . '%');
        }

        $settings = $settings->paginate($perPage);

        return sendResponse("Settings retrieved successfully.", new SettingResource($settings), []);
    }


    public function getDefaultSettings()
    {
        $settings = Setting::whereIn('key', ['default_decimal_precision', 'currency'])->get();
        $defaultPrecision = $settings->where('key', 'default_decimal_precision')->first();
        $currency = $settings->where('key', 'currency')->first();
        if (!$defaultPrecision || !$currency) {
            return sendResponse("Some Required Settings not exist", [], false);
        }
        $responseData = [
            'default_settings' => [
                'decimal_precision' => [
                    'key' => $defaultPrecision->key,
                    'value' => $defaultPrecision->value,
                    'type' => $defaultPrecision->type ?? 'integer'
                ],
                'currency' => [
                    'key' => $currency->key,
                    'value' => $currency->value,
                    'type' => $currency->type ?? 'string'
                ]
            ]
        ];
        return sendResponse("Default Settings Fetched Successfully", $responseData, []);
    }

    /**
     * @OA\Post(
     *     path="/settings/store",
     *     summary="Create a new setting",
     *     description="Create a new setting.",
     *     tags={"WMS"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="key",
     *                     description="The key of the setting (required)",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="value",
     *                     description="The value of the setting (required)",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     description="The type of the setting",
     *                     type="string",
     *                     enum={"boolean", "integer", "select"},
     *                     example="boolean"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occured."
     *     )
     * )
     */
    public function store(StoreSettingRequest $request)
    {
        $request->validated();
        try {
            $setting = Setting::create($request->all());
            activityLog('setting created', "new setting created called {$setting->key}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], false, [$e->getMessage()], 422);
        }
        return sendResponse("Setting created successfully.", new SettingResource($setting));
    }


    public function update(UpdateSettingRequest $request)
    {
        $request->validated();
        try {
            $setting = Setting::find($request->id);
            $setting->update($request->all());
            activityLog('setting updated', "setting updated called {$setting->key}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Setting updated successfully.", new SettingResource($setting));
    }

    /**
     * @OA\Post(
     *     path="/settings/delete",
     *     summary="Delete a setting",
     *     description="Delete an existing setting.",
     *     tags={"WMS"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     description="The ID of the setting to delete (required)",
     *                     type="integer"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occured."
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            $setting = Setting::findOrFail($request->id);
            $setting->delete();
            activityLog('setting deleted', "setting deleted called {$setting->key}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Setting deleted successfully.", []);
    }

    /**
     * @OA\Post(
     *     path="/settings/change_status",
     *     summary="Change setting status",
     *     description="Change the status of an existing setting.",
     *     tags={"WMS"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     description="The ID of the setting to update (required)",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     description="The status of the setting (required)",
     *                     type="boolean"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting status updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occured."
     *     )
     * )
     */
    public function change_status(Request $request)
    {
        try {
            $setting = Setting::find($request->id)->update([
                'status' => $request->status
            ]);
            activityLog('setting status updated', "setting status updated called {$setting->key}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return sendResponse("Setting status updated successfully.", []);
    }
}
