<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCountryChannelRequest;
use App\Http\Resources\CountryChannelResource;
use Illuminate\Database\QueryException;
use App\Models\CountryChannel;
use Database\Factories\CountryChannelFactory;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Other", description="Country Channel Management")
 * @OA\Server(url="/api")
 */
class CountryChannelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/country_channels/{shipper_id}",
     *     summary="Get a list of country channels",
     *     description="Retrieves a paginated list of country channels for a given shipper ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="shipper_id",
     *         in="path",
     *         description="ID of the shipper",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channels retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function index($shipper_id)
    {
        $channels = CountryChannel::where('shipper_id', $shipper_id);
        $channels = $channels->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Channels reterived successfully.", new CountryChannelResource($channels), []);
    }

    /**
     * @OA\Post(
     *     path="/country_channels/store",
     *     summary="Create a new country channel",
     *     description="Creates a new country channel.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipper_id", type="integer", description="Shipper ID", example=1),
     *             @OA\Property(property="internal_country_id", type="integer", description="Internal Country ID", example=1),
     *             @OA\Property(property="internal_country_name", type="string", description="Internal Country Name", example="USA"),
     *             @OA\Property(property="external_country_id", type="integer", description="External Country ID", example=1),
     *             @OA\Property(property="external_country_name", type="string", description="External Country Name", example="United States")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Channel created successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(StoreCountryChannelRequest $request)
    {
        $request->validated();
        try {
            $channel = CountryChannel::create($request->all());
            return sendResponse("Channel created successfully.", new CountryChannelResource($channel));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating channel.", [], false, [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/country_channels/delete",
     *     summary="Delete a country channel",
     *     description="Deletes a country channel.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the channel to delete", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel deleted successfully",
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            CountryChannel::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Channel deleted successfully.", []);
    }
}