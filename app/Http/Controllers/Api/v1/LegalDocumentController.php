<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreLegalDocumentRequest;
use App\Http\Requests\UpdateLegalDocumentRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Models\LegalDocument;
use App\Traits\Searchable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Other", description="Legal Document Management")
 */
class LegalDocumentController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return LegalDocument::query()->select('id', 'document_id', 'document_name', 'type', 'expiry_date', 'uploaded_by', 'file_path', 'created_at', 'updated_at');
    }

    /**
     * @OA\Get(
     *     path="/legal-documents",
     *     summary="Get a list of legal documents",
     *     description="Retrieves a list of legal documents with pagination and search capabilities.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Documents retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $documents = $this->handleSearch(
            searchColumns: ['document_name', 'type', 'document_id'],
            withRelationships: [
                'uploader:id,name'
            ],
            perPage: request()->input('per_page', 15),
            shipmentColumn: 'expiry_date',
            shipmentDirection: 'asc'
        );

        return sendResponse("Legal Documents retrieved successfully.", new LegalDocumentResource($documents), []);
    }

    /**
     * @OA\Post(
     *     path="/legal-documents/store",
     *     summary="Store a new legal document",
     *     description="Creates a new legal document.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="document_id", type="string", description="Document ID (required, unique)", example="doc-123"),
     *             @OA\Property(property="document_name", type="string", description="Document Name (required, max 255 characters)", example="Legal Document 1"),
     *             @OA\Property(property="type", type="string", description="Document Type (required, max 255 characters)", example="Contract"),
     *             @OA\Property(property="expiry_date", type="string", format="date", description="Expiry Date (required, must be after today)", example="2024-12-31"),
     *             @OA\Property(property="file", type="file", description="Document File (required, PDF, DOC, DOCX, JPG, JPEG, PNG, max 10MB)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error Occurred"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreLegalDocumentRequest $request)
    {
        $request->validated();
        try {
            $filePath = uploadFile($request->file('file'), 'public/legal_documents');

            $document = LegalDocument::create([
                'document_id'   => $request->document_id,
                'document_name' => $request->document_name,
                'type'          => $request->type,
                'expiry_date'   => $request->expiry_date,
                'uploaded_by'   => $request->user()->id,
                'file_path'     => $filePath,
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Legal Document created successfully.", new LegalDocumentResource($document));
    }

    /**
     * @OA\Post(
     *     path="/legal-documents/update",
     *     summary="Update a legal document",
     *     description="Updates an existing legal document.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the legal document (required, exists)", example=1),
     *             @OA\Property(property="document_id", type="string", description="Document ID (unique)"),
     *             @OA\Property(property="document_name", type="string", description="Document Name (max 255 characters)"),
     *             @OA\Property(property="type", type="string", description="Document Type (max 255 characters)"),
     *             @OA\Property(property="expiry_date", type="string", format="date", description="Expiry Date (must be after today)"),
     *             @OA\Property(property="file", type="file", description="Document File (PDF, DOC, DOCX, JPG, JPEG, PNG, max 10MB)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error Occurred"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateLegalDocumentRequest $request)
    {
        $request->validated();
        try {
            $document = LegalDocument::find($request->id);

            $data = [
                'document_id'   => $request->document_id ?? $document->document_id,
                'document_name' => $request->document_name ?? $document->document_name,
                'type'          => $request->type ?? $document->type,
                'expiry_date'   => $request->expiry_date ?? $document->expiry_date,
            ];

            if ($request->hasFile('file')) {
                Storage::delete($document->file_path);
                $data['file_path'] = uploadFile($request->file('file'), 'public/legal_documents');
            }

            $document->update($data);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Legal Document updated successfully.", new LegalDocumentResource($document));
    }

    /**
     * @OA\Post(
     *     path="/legal-documents/delete",
     *     summary="Delete a legal document",
     *     description="Deletes an existing legal document.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the legal document to delete (required)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            $document = LegalDocument::find($request->id);

            if ($document) {
                Storage::delete($document->file_path);
                $document->delete();
            }
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Legal Document deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/legal-documents/all",
     *     summary="Get all legal documents",
     *     description="Retrieves all legal documents.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Legal Documents"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Legal Documents", new LegalDocumentResource(LegalDocument::with('uploader')->get()));
    }

    /**
     * @OA\Get(
     *     path="/legal-documents/edit/{id}",
     *     summary="Get a legal document by ID",
     *     description="Retrieves a legal document by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the legal document",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function edit($id)
    {
        $document = LegalDocument::find($id);
        return sendResponse("Legal Document", $document);
    }

    /**
     * @OA\Get(
     *     path="/legal-documents/{document}",
     *     summary="Get a legal document by ID",
     *     description="Retrieves a legal document by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="document",
     *         in="path",
     *         description="ID of the legal document",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(LegalDocument $document)
    {
        return sendResponse("Legal Document", $document);
    }

    /**
     * @OA\Delete(
     *     path="/legal-documents/{document}",
     *     summary="Delete a legal document",
     *     description="Deletes a legal document by its ID.",
     *     tags={"Other"},
     *      @OA\Parameter(
     *         name="document",
     *         in="path",
     *         description="ID of the legal document",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function destroy(LegalDocument $document)
    {
        try {
            Storage::delete($document->file_path);
            $document->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Legal Document deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/legal-documents/{document}/download",
     *     summary="Download a legal document",
     *     description="Downloads a legal document.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="document",
     *         in="path",
     *         description="ID of the legal document",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Legal Document downloaded successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function download(LegalDocument $document)
    {
        return Storage::download($document->file_path, $document->document_name);
    }
}
