<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\ShipmentRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Other", description="Shipment Rule Management")
 */
class ShipmentRuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipment-rules",
     *     tags={"Other"},
     *     summary="Get all shipment rules",
     *     description="Retrieves a list of all shipment rules.",
     *     @OA\Response(
     *         response=200,
     *         description="Shipment rules retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $rules = ShipmentRule::get();

        return sendResponse(
            'Shipment rules retrieved successfully',
            $rules
        );
    }

    /**
     * @OA\Post(
     *     path="/shipment-rules",
     *     summary="Create a new shipment rule",
     *     description="Creates a new shipment rule.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, description="Name of the shipment rule"),
     *             @OA\Property(property="condition_json", type="array", description="JSON representation of the condition",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="field", type="string", description="Field name"),
     *                 @OA\Property(property="operator", type="string", description="Comparison operator"),
     *                 @OA\Property(property="value", type="string", description="Value to compare against")
     *             )
     *             ),
     *             @OA\Property(property="action_json", type="array", description="JSON representation of the action",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="field", type="string", description="Field name"),
     *                     @OA\Property(property="operator", type="string", description="Comparison operator"),
     *                     @OA\Property(property="value", type="string", description="Value to set")
     *                 )
     *             ),
     *             @OA\Property(property="is_active", type="boolean", description="Whether the rule is active"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment rule created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'condition_json' => 'required|array',
            'action_json' => 'required|array',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }

        $rule = new ShipmentRule([
            'name' => $request->name,
            'condition_json' => $request->condition_json,
            'action_json' => $request->action_json,
            'is_active' => $request->is_active ?? true
        ]);

        if (!$rule->validateCondition($request->condition_json)) {
            return sendResponse(
                'Invalid condition format',
                [],
                false,
                ['condition_json' => 'Invalid condition format'],
                422
            );
        }

        if (!$rule->validateAction($request->action_json)) {
            return sendResponse(
                'Invalid action format',
                [],
                false,
                ['action_json' => 'Invalid action format'],
                422
            );
        }

        $rule->save();

        return sendResponse(
            'Shipment rule created successfully',
            $rule
        );
    }

    /**
     * @OA\Post(
     *     path="/shipment-rules/update",
     *     tags={"Other"},
     *     summary="Update a shipment rule",
     *     description="Updates an existing shipment rule.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the shipment rule to update"),
     *             @OA\Property(property="name", type="string", maxLength=255, description="Name of the shipment rule"),
     *             @OA\Property(property="condition_json", type="array", description="JSON representation of the condition",
                @OA\Items(type="object",
                    @OA\Property(property="field", type="string", description="Field name"),
                    @OA\Property(property="operator", type="string", description="Comparison operator"),
                    @OA\Property(property="value", type="string", description="Value to compare against")
                )
            ),
     *         @OA\Property(property="action_json", type="array", description="JSON representation of the action",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="field", type="string", description="Field name"),
     *                 @OA\Property(property="operator", type="string", description="Comparison operator"),
     *                 @OA\Property(property="value", type="string", description="Value to set")
     *             )
     *),
     *             @OA\Property(property="is_active", type="boolean", description="Whether the rule is active"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment rule updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(Request $request)
    {
        $id = $request->input("id");
        $rule = ShipmentRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'condition_json' => 'array',
            'action_json' => 'array',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }

        $rule->fill($request->all());

        if (isset($request->condition_json) && !$rule->validateCondition($request->condition_json)) {
            return sendResponse(
                'Invalid condition format',
                [],
                false,
                ['condition_json' => 'Invalid condition format'],
                422
            );
        }

        if (isset($request->action_json) && !$rule->validateAction($request->action_json)) {
            return sendResponse(
                'Invalid action format',
                [],
                false,
                ['action_json' => 'Invalid action format'],
                422
            );
        }

        $rule->save();

        return sendResponse(
            'Shipment rule updated successfully',
            $rule
        );
    }

    /**
     * @OA\Post(
     *     path="/shipment-rules/delete",
     *     summary="Delete a shipment rule",
     *     tags={"Other"},
     *     description="Deletes an existing shipment rule.",
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the shipment rule to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment rule deleted successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function destroy(Request $request)
    {
        $id = $request->input("id");
        $rule = ShipmentRule::findOrFail($id);
        $rule->delete();
        return sendResponse(
            'Shipment rule deleted successfully',
            []
        );
    }
}
