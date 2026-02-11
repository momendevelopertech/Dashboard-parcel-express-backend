<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use App\Models\CustomAlert;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(name="Other", description="Custom Alert Management")
 */
class CustomAlertController extends Controller
{
    /**
     * @OA\Get(
     *     path="/custom-alerts",
     *     summary="Get a list of custom alerts",
     *     description="Retrieves a paginated list of custom alerts.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (active or inactive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by alert name or condition",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custom alerts retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Request $request)
    {
        $query = CustomAlert::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where('alert_name', 'like', "%{$search}%")
                ->orWhere('condition', 'like', "%{$search}%");
        }

        $alerts = $query->orderBy('created_at', 'desc')->paginate(15);

        return sendResponse(
            'Custom alerts retrieved successfully',
            $alerts,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/custom-alerts/store",
     *     summary="Create a new custom alert",
     *     description="Creates a new custom alert.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="alert_name", type="string", description="Alert name", example="High Temperature Alert"),
     *             @OA\Property(property="condition", type="string", description="Condition", example="temperature > 100"),
     *             @OA\Property(property="status", type="boolean", description="Status (true for active, false for inactive)"),
     *             @OA\Property(property="recipients", type="array", description="Recipients", @OA\Items(type="string", enum={"admin", "driver", "logistics_manager"})),
     *             @OA\Property(property="notification_methods", type="array", description="Notification methods", @OA\Items(type="string", enum={"email", "in_system", "sms"}))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Custom alert created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'alert_name'           => 'required|string|max:255',
                'condition'            => 'required|string',
                'status'               => 'boolean',
                'recipients'           => 'required|array|min:1',
                'recipients.*'         => 'in:admin,driver,logistics_manager',
                'notification_methods' => 'required|array|min:1',
                'notification_methods.*' => 'in:email,in_system,sms',
            ]);
            $data['status'] = $request->input('status', true) ? 'active' : 'inactive';
            $alert = CustomAlert::create($data);
            return sendResponse(
                'Custom alert created successfully',
                $alert,
                true,
                [],
                201
            );
        } catch (ValidationException $e) {
            return sendResponse(
                'Validation failed',
                [],
                false,
                $e->errors(),
                422
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/custom-alerts/show",
     *     summary="Get a custom alert by ID",
     *     description="Retrieves a custom alert by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the custom alert",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custom alert retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Custom alert not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function show(Request $request)
    {
        $alert = CustomAlert::find($request->id);
        if (!$alert) {
            return sendResponse(
                'Custom alert not found',
                [],
                false,
                [],
                404
            );
        }
        return sendResponse(
            'Custom alert retrieved successfully',
            $alert,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/custom-alerts/update",
     *     summary="Update a custom alert",
     *     description="Updates a custom alert.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the custom alert to update"),
     *             @OA\Property(property="alert_name", type="string", description="Alert name"),
     *             @OA\Property(property="condition", type="string", description="Condition"),
     *             @OA\Property(property="status", type="boolean", description="Status (true for active, false for inactive)"),
     *             @OA\Property(property="recipients", type="array", description="Recipients", @OA\Items(type="string", enum={"admin", "driver", "logistics_manager"})),
     *             @OA\Property(property="notification_methods", type="array", description="Notification methods", @OA\Items(type="string", enum={"email", "in_system", "sms"}))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custom alert updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Custom alert not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(Request $request)
    {
        try {
            $alert = CustomAlert::find($request->id);
            if (!$alert) {
                return sendResponse(
                    'Custom alert not found',
                    [],
                    false,
                    [],
                    404
                );
            }
            $data = $request->validate([
                'alert_name'           => 'sometimes|string|max:255',
                'condition'            => 'sometimes|string',
                'status'               => 'sometimes|boolean',
                'recipients'           => 'sometimes|array|min:1',
                'recipients.*'         => 'in:admin,driver,logistics_manager',
                'notification_methods' => 'sometimes|array|min:1',
                'notification_methods.*' => 'in:email,in_system,sms',
            ]);
            $data['status'] = $request->input('status', true) ? 'active' : 'inactive';
            $alert->update($data);
            return sendResponse(
                'Custom alert updated successfully',
                $alert,
                true,
                [],
                200
            );
        } catch (ValidationException $e) {
            return sendResponse(
                'Validation failed',
                [],
                false,
                $e->errors(),
                422
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/custom-alerts/delete",
     *     summary="Delete a custom alert",
     *     description="Deletes a custom alert.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the custom alert to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Custom alert deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Custom alert not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function destroy(Request $request)
    {
        $alert = CustomAlert::find($request->id);
        if (!$alert) {
            return sendResponse(
                'Custom alert not found',
                [],
                false,
                [],
                404
            );
        }
        $alert->delete();
        return sendResponse(
            'Custom alert deleted successfully',
            [],
            true,
            [],
            204
        );
    }
}
