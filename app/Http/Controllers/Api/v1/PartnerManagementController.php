<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Partner;
use App\Models\Shipment;
use App\Models\PartnerKey;
use Illuminate\Http\Request;
use App\Models\PartnerWebhook;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Http\Resources\PartnerResource;
use Illuminate\Database\QueryException;
use App\Http\Requests\StorePartnerRequest;

class PartnerManagementController extends Controller
{
    /**
     * Get all partners
     *
     * @OA\Get(
     *   path="/partners/all",
     *   tags={"Partner Management"},
     *   summary="Get all partners",
     *   description="Get a list of all partners",
     *   operationId="getAllPartners",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Number of items per page",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         default=8
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         default=1
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Partners retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Partners retrieved successfully."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *   )
     * )
     */
    public function all()
    {
        return sendResponse("Partners", new PartnerResource(Partner::get()));
    }

    /**
     * Create partner
     *
     * @OA\Post(
     *   path="/partners/store",
     *   tags={"Partner Management"},
     *   summary="Create a new partner",
     *   description="Create a new partner with specified details",
     *   operationId="createPartner",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Partner creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Partner Name", maxLength=255),
     *       @OA\Property(property="contact_email", type="string", example="partner@example.com", maxLength=255),
     *       @OA\Property(property="allowed_scopes", type="array", @OA\Items(type="string"), example={"read:shipments", "write:shipments"}),
     *       @OA\Property(property="is_active", type="boolean", example=true),
     *       @OA\Property(property="rate_limit", type="object", example={"requests_per_minute": 100, "requests_per_hour": 5000})
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Partner created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Partner created successfully."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StorePartnerRequest $request)
    {
        $request->validated();
        try {
            $partner = Partner::create($request->all());

            // Generate both sandbox and production keys for the new partner
            PartnerKey::generateBoth($partner);
            activityLog('partner create',"new partner created called {$partner->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Partner created successfully.", new PartnerResource($partner));
    }

    /**
     * Get partner keys
     *
     * @OA\Get(
     *   path="/partners/{id}/keys",
     *   tags={"Partner Management"},
     *   summary="Get partner API keys",
     *   description="Get all API keys for a specific partner",
     *   operationId="getPartnerKeys",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Partner ID",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Partner keys retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Partner keys retrieved successfully."),
     *       @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Partner not found"
     *   )
     * )
     */
    public function keys($id)
    {
        $partner = Partner::with('keys')->findOrFail($id);

        // Sort keys: sandbox first, then production
        $sortedKeys = $partner->keys->sortBy(function ($key) {
            return $key->key_type === 'sandbox' ? 0 : 1;
        });

        $keys = $sortedKeys->map(function ($key) {
            $data = [
                'id' => $key->id,
                'key_type' => $key->key_type,
                'key_id' => $key->key_id,
                'is_active' => $key->is_active,
                'expires_at' => $key->expires_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
            ];

            // Return both encrypted hash and decrypted secret for admin users
            $data['secret_hash'] = $key->secret_hash ?? null;
            if ($key->secret_hash) {
                // Try to decrypt first (for backward compatibility with encrypted secrets)
                // If decryption fails, treat it as plain text (max 10 chars bypass secret)
                try {
                    $decrypted = decrypt($key->secret_hash);
                    $data['secret'] = $decrypted;
                } catch (\Exception $e) {
                    // If decryption fails, use as plain text (for plain text secrets max 10 chars)
                    $data['secret'] = $key->secret_hash;
                }
            } else {
                $data['secret'] = null;
            }

            return $data;
        });

        return sendResponse("Partner keys retrieved successfully.", $keys);
    }

    /**
     * Get partner webhooks
     *
     * @OA\Get(
     *   path="/partners/{id}/webhooks",
     *   tags={"Partner Management"},
     *   summary="Get partner webhooks",
     *   description="Get all webhooks for a specific partner",
     *   operationId="getPartnerWebhooks",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Partner ID",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Partner webhooks retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Partner webhooks retrieved successfully."),
     *       @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Partner not found"
     *   )
     * )
     */
    public function webhooks($id)
    {
        $partner = Partner::with('webhooks')->findOrFail($id);

        $webhooks = $partner->webhooks->map(function ($webhook) {
            return [
                'id' => $webhook->id,
                'url' => $webhook->url,
                'events' => $webhook->events ?? [],
                'is_active' => $webhook->is_active,
                'last_success_at' => $webhook->last_success_at?->toIso8601String(),
                'failure_count' => $webhook->failure_count,
                'created_at' => $webhook->created_at?->toIso8601String(),
            ];
        });

        return sendResponse("Partner webhooks retrieved successfully.", $webhooks);
    }

    /**
     * Update partner allowed scopes
     */
    public function updateScopes($id, Request $request)
    {
        $request->validate([
            'allowed_scopes' => 'nullable|array',
            'allowed_scopes.*' => 'string'
        ]);

        $partner = Partner::findOrFail($id);
        $partner->allowed_scopes = $request->input('allowed_scopes', []);
        $partner->save();
        activityLog('partner updated',"partner with name {$partner->name} updated  with scopes {$partner->allowed_scopes}");

        return sendResponse("Partner scopes updated successfully.", [
            'id' => $partner->id,
            'allowed_scopes' => $partner->allowed_scopes,
        ]);
    }
}

