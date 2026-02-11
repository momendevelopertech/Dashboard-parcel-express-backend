<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Support\Facades\Log;

use App\Http\Helpers\helpers;
use App\Models\CustomsDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\Snappy\PdfWrapper;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * @OA\Tag(name="Other", description="Customs Declaration Management")
 */
class CustomsDeclarationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/customs",
     *     summary="Get all customs declarations",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Customs declarations retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function index()
    {
        $customs = CustomsDeclaration::get();
        return sendResponse('Customs declarations retrieved successfully', $customs);
    }

    /**
     * @OA\Post(
     *     path="/customs",
     *     summary="Create a new customs declaration",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", description="Description", example="test"),
     *             @OA\Property(property="declared_value", type="number", format="float", description="Declared value", example=100.00),
     *             @OA\Property(property="hs_code", type="string", description="HS Code", example="123456"),
     *             @OA\Property(property="origin_country", type="string", description="Origin country", example="US"),
     *             @OA\Property(property="export_reason", type="string", description="Export reason", example="test"),
     *             @OA\Property(property="invoice", type="string", format="binary", description="Invoice (PDF, JPG, JPEG, PNG)", example="path/to/invoice.pdf")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Customs declaration created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     ),
     *      @OA\Parameter(
     *          name="description",
     *          in="query",
     *          description="Description",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="declared_value",
     *          in="query",
     *          description="Declared value",
     *          required=true,
     *          @OA\Schema(type="number", format="float")
     *      ),
     *      @OA\Parameter(
     *          name="hs_code",
     *          in="query",
     *          description="HS Code",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="origin_country",
     *          in="query",
     *          description="Origin country",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="export_reason",
     *          in="query",
     *          description="Export reason",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="invoice",
     *          in="query",
     *          description="Invoice (PDF, JPG, JPEG, PNG)",
     *          required=false,
     *          @OA\Schema(type="string", format="binary")
     *      )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'declared_value' => 'required|numeric|min:0',
            'hs_code' => 'required|string',
            'origin_country' => 'required|string',
            'export_reason' => 'required|string',
            'invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        $customs = new CustomsDeclaration();
        $customs->description = $validated['description'];
        $customs->declared_value = $validated['declared_value'];
        $customs->hs_code = $validated['hs_code'];
        $customs->origin_country = $validated['origin_country'];
        $customs->export_reason = $validated['export_reason'];

        if ($request->hasFile('invoice')) {
            $path = uploadFile($request->file('invoice'), 'public/customs_invoices');

            $customs->invoice_path = $path;
        }

        $customs->save();

        return sendResponse('Customs declaration created successfully', $customs);
    }

    /**
     * @OA\Post(
     *     path="/customs/update/{id}",
     *     summary="Update a customs declaration",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the customs declaration to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", description="Description"),
     *             @OA\Property(property="declared_value", type="number", format="float", description="Declared value"),
     *             @OA\Property(property="hs_code", type="string", description="HS Code"),
     *             @OA\Property(property="origin_country", type="string", description="Origin country"),
     *             @OA\Property(property="export_reason", type="string", description="Export reason"),
     *             @OA\Property(property="invoice", type="string", format="binary", description="Invoice (PDF, JPG, JPEG, PNG)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customs declaration updated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customs declaration not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $customs = CustomsDeclaration::findOrFail($id);

            // Log the request data
            Log::info('Update request received:', [
                'id' => $id,
                'request_data' => $request->all()
            ]);

            // Get all input data
            $data = $request->all();

            // Handle decimal value
            if (isset($data['declared_value'])) {
                $data['declared_value'] = floatval($data['declared_value']);
            }

            // Validate the data
            $validated = validator($data, [
                'description' => 'nullable|string',
                'declared_value' => 'nullable|numeric|min:0',
                'hs_code' => 'nullable|string',
                'origin_country' => 'nullable|string',
                'export_reason' => 'nullable|string',
                'invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ])->validate();

            // Log the validated data
            Log::info('Validated data:', [
                'validated' => $validated
            ]);

            // Update each field individually
            $customs->description = $validated['description'] ?? $customs->description;
            $customs->declared_value = $validated['declared_value'] ?? $customs->declared_value;
            $customs->hs_code = $validated['hs_code'] ?? $customs->hs_code;
            $customs->origin_country = $validated['origin_country'] ?? $customs->origin_country;
            $customs->export_reason = $validated['export_reason'] ?? $customs->export_reason;

            // Log the updated model before save
            Log::info('Model before save:', [
                'model' => $customs->toArray()
            ]);

            // Save the changes to the database
            $customs->save();

            // Handle file upload
            if ($request->hasFile('invoice')) {
                // Delete old file if exists
                if ($customs->invoice_path) {
                    Storage::disk('public')->delete($customs->invoice_path);
                }

                // Upload new file
                $path = uploadFile($request->file('invoice'), 'public/customs_invoices');
                $customs->invoice_path = $path;
            }

            $customs->save();

            return sendResponse('Customs declaration updated successfully', $customs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the customs declaration.',
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/customs/delete",
     *     summary="Delete a customs declaration",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the customs declaration to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customs declaration deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customs declaration not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function destroy(Request $request)
    {
        $id = $request->input("id");
        $customs = CustomsDeclaration::findOrFail($id);
        if ($customs->invoice_path) {
            Storage::disk('public')->delete($customs->invoice_path);
        }
        $customs->delete();
        return sendResponse('Customs declaration deleted successfully', null);
    }

    /**
     * @OA\Get(
     *     path="/customs/{id}/pdf",
     *     summary="Generate PDF for a customs declaration",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the customs declaration",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customs declaration not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function generatePdf($id)
    {
        $customs = CustomsDeclaration::findOrFail($id);

        $pdf = app('snappy.pdf.wrapper');
        $html = view('customs.pdf', compact('customs'))->render();

        return $pdf->loadHTML($html)
            ->download('customs_declaration_' . $customs->id . '.pdf');
    }
}
