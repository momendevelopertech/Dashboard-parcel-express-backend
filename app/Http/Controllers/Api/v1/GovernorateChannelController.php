<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GovernorateChannelsExport;
use App\Http\Requests\StoreGovernorateChannelRequest;
use App\Http\Resources\GovernorateChannelResource;
use App\Models\GovernorateChannel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use App\Imports\GovernorateChannelsImport;
use App\Exports\GovernorateChannelsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="Other", description="Governorate Channel Management")
 * @OA\Controller(description="Handles Governorate Channel operations.")
 */
class GovernorateChannelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/governorate_channels/{shipper_id}",
     *     summary="Get all governorate channels for a specific shipper",
     *     description="Retrieves a list of governorate channels associated with a given shipper ID.",
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
     *         response=422,
     *         description="Error occurred while retrieving channels",
     *     )
     * )
     */
    public function index($shipper_id)
    {
        $channels = GovernorateChannel::where('shipper_id', $shipper_id);
        $channels = $channels->orderBy('id', 'desc')->get();
        return sendResponse("Channels reterived successfully.", new GovernorateChannelResource($channels), []);
    }

    /**
     * @OA\Post(
     *     path="/governorate_channels/store",
     *     summary="Create a new governorate channel",
     *     description="Creates a new governorate channel.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipper_id", type="integer", description="Shipper ID", example=1),
     *             @OA\Property(property="internal_governorate_id", type="integer", description="Internal Governorate ID", example=1),
     *             @OA\Property(property="external_governorate_id", type="integer", description="External Governorate ID (nullable)", example=null),
     *             @OA\Property(property="external_governorate_name", type="string", description="External Governorate Name (nullable)", example="External Governorate"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating channel",
     *     ),
     *      @OA\Parameter(
     *          name="shipper_id",
     *          in="query",
     *          description="Shipper ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="internal_governorate_id",
     *          in="query",
     *          description="Internal Governorate ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="external_governorate_id",
     *          in="query",
     *          description="External Governorate ID",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="external_governorate_name",
     *          in="query",
     *          description="External Governorate Name",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     * )
     */
    public function store(StoreGovernorateChannelRequest $request)
    {
        $request->validated();
        try {
            $channel = GovernorateChannel::create($request->all());
            return sendResponse("Channel created successfully.", new GovernorateChannelResource($channel));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating channel.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/governorate_channels/delete",
     *     summary="Delete a governorate channel",
     *     description="Deletes a governorate channel.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the channel to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting channel",
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the channel to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            GovernorateChannel::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Channel deleted successfully.", []);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'shipper_id' => ['nullable', 'integer', 'exists:shippers,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'string'],
        ]);

        $shipperId = $validated['shipper_id'] ?? null;
        $search = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'id';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $filename = 'governorate_channels'
            . ($shipperId ? "_shipper_{$shipperId}" : '_all')
            . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new GovernorateChannelsExport($shipperId, $search, $sortBy, $sortDir),
            $filename
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'shipper_id' => 'required|integer|exists:shippers,id',
        ]);

        try {
            $import = new GovernorateChannelsImport($request->integer('shipper_id'));
            Excel::import($import, $request->file('file'));

            $count = $import->getImportedCount();
            $errors = $import->failures(); // built-in from SkipsFailures

            if ($errors && count($errors)) {
                return sendResponse(
                    "Import completed with some errors",
                    ['imported_count' => $count, 'errors' => $errors],
                    true,
                    [],
                    207
                );
            }

            return sendResponse("Successfully imported {$count} governorate channels", ['imported_count' => $count]);
        } catch (\Throwable $e) {
            return sendResponse("Error occurred during import", [], false, [$e->getMessage()], 422);
        }
    }
}