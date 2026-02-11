<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreMerchantBranchRequest;
use App\Http\Requests\UpdateMerchantBranchRequest;
use App\Http\Resources\MerchantBranchResource;
use App\Models\MerchantBranch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantBranchController extends Controller
{
    /**
     * Get Merchant Branches
     *
     * @OA\Get(
     *     path="/merchant/branches",
     *     summary="Get merchant branches with search support",
     *     description="
     * Retrieve branches for authenticated merchant with search and pagination capabilities.
     * 
     * **Features:**
     * - Search by name, contact, or location
     * - Pagination support
     * - Location data included
     * - Shipment creation optimization
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantBranches",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for name, contact, or location",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branches retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branches retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $merchantId = Auth::id();
        
        $branches = MerchantBranch::where('merchant_id', $merchantId);
        
        if ($query) {
            $branches = $branches->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                  ->orWhere('contact', 'LIKE', '%' . $query . '%')
                  ->orWhere('location', 'LIKE', '%' . $query . '%');
            })
            ->with([
                'country:id,name', 
                'governorate:id,en_name,ar_name', 
                'state:id,en_name,ar_name', 
                'place:id,en_name,ar_name',
                'city:id,name'
            ])
            ->orderBy('name', 'asc')
            ->get();
        } else {
            $branches = $branches
                ->with([
                    'country:id,name', 
                    'governorate:id,en_name,ar_name', 
                    'state:id,en_name,ar_name', 
                    'place:id,en_name,ar_name',
                    'city:id,name'
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }
        
        return sendResponse(
            "Merchant branches retrieved successfully.", 
            new MerchantBranchResource($branches)
        );
    }

    /**
     * Create Merchant Branch
     *
     * @OA\Post(
     *     path="/merchant/branches/store",
     *     summary="Create new merchant branch",
     *     description="
     * Create a new branch for the authenticated merchant.
     * 
     * **Features:**
     * - Complete branch validation
     * - Location data linking
     * - Auto merchant association
     * - Duplicate prevention
     * 
     * **Security:**
     * - Merchant authentication required
     * - Request validation applied
     * ",
     *     operationId="createMerchantBranch",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Branch creation data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branch created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branch created successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function store(StoreMerchantBranchRequest $request)
    {
        $validated = $request->validated();
        $validated['merchant_id'] = Auth::id();
        
        try {
            $branch = MerchantBranch::create($validated);
            $branch->load([
                'country:id,name', 
                'governorate:id,en_name,ar_name', 
                'state:id,en_name,ar_name', 
                'place:id,en_name,ar_name',
                'city:id,name'
            ]);
            
            return sendResponse(
                "Merchant branch created successfully.", 
                new MerchantBranchResource($branch)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating branch.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * Get Merchant Branch Details
     *
     * @OA\Get(
     *     path="/merchant/branches/{id}",
     *     summary="Get specific merchant branch",
     *     description="Get detailed information for a specific merchant branch.",
     *     operationId="getMerchantBranchDetails",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branch retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branch retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant branch not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $branch = MerchantBranch::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->with([
                    'country:id,name', 
                    'governorate:id,en_name,ar_name', 
                    'state:id,en_name,ar_name', 
                    'place:id,en_name,ar_name',
                    'city:id,name'
                ])
                ->firstOrFail();
                
            return sendResponse(
                "Merchant branch retrieved successfully.", 
                new MerchantBranchResource($branch)
            );
        } catch (\Exception $e) {
            return sendResponse("Merchant branch not found.", [], ["Merchant branch not found"], 404);
        }
    }

    /**
     * Update Merchant Branch
     *
     * @OA\Post(
     *     path="/merchant/branches/{id}/update",
     *     summary="Update merchant branch",
     *     description="Update an existing merchant branch with new data.",
     *     operationId="updateMerchantBranch",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated branch data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branch updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branch updated successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant branch not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(UpdateMerchantBranchRequest $request, $id)
    {
        try {
            $branch = MerchantBranch::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();

            $branch->update($request->validated());

            $branch->load([
                'country:id,name', 
                'governorate:id,en_name,ar_name', 
                'state:id,en_name,ar_name', 
                'place:id,en_name,ar_name',
                'city:id,name'
            ]);
            
            return sendResponse(
                "Merchant branch updated successfully.", 
                new MerchantBranchResource($branch)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating branch.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Merchant branch not found.", [], ["Merchant branch not found"], 404);
        }
    }

    /**
     * Delete Merchant Branch
     *
     * @OA\Post(
     *     path="/merchant/branches/{id}/delete",
     *     summary="Delete merchant branch",
     *     description="Delete an existing merchant branch.",
     *     operationId="deleteMerchantBranch",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branch deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branch deleted successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant branch not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $branch = MerchantBranch::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();
                
            $branch->delete();
            
            return sendResponse("Merchant branch deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while deleting branch.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Merchant branch not found.", [], ["Merchant branch not found"], 404);
        }
    }

    /**
     * Get All Active Merchant Branches
     *
     * @OA\Get(
     *     path="/merchant/branches/all",
     *     summary="Get all active merchant branches",
     *     description="
     * Retrieve all active branches for the authenticated merchant sorted by name.
     * 
     * **Features:**
     * - Only active branches
     * - Name alphabetical sorting
     * - Location data included
     * - No pagination applied
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getAllActiveMerchantBranches",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="All merchant branches retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All merchant branches retrieved successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function all()
    {
        $merchantId = Auth::id();
        
        $branches = MerchantBranch::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->with([
                'country:id,name', 
                'governorate:id,en_name,ar_name', 
                'state:id,en_name,ar_name', 
                'place:id,en_name,ar_name',
                'city:id,name'
            ])
            ->orderBy('name', 'asc')
            ->get();
            
        return sendResponse(
            "All merchant branches retrieved successfully.", 
            MerchantBranchResource::collection($branches)
        );
    }

    /**
     * Get Merchant Branch for Editing
     *
     * @OA\Get(
     *     path="/merchant/branches/edit/{id}",
     *     summary="Get merchant branch data for editing",
     *     description="Retrieve merchant branch data formatted for editing form.",
     *     operationId="getMerchantBranchForEditing",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Branch ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant branch retrieved for editing",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant branch retrieved for editing."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant branch not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function edit($id)
    {
        try {
            $branch = MerchantBranch::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->with([
                    'country:id,name', 
                    'governorate:id,en_name,ar_name', 
                    'state:id,en_name,ar_name', 
                    'place:id,en_name,ar_name',
                    'city:id,name'
                ])
                ->firstOrFail();
                
            return sendResponse("Merchant branch retrieved for editing.", new MerchantBranchResource($branch));
        } catch (\Exception $e) {
            return sendResponse("Merchant branch not found.", [], ["Merchant branch not found"], 404);
        }
    }
}
