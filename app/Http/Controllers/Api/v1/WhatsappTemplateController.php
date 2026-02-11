<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\WhatsAppTemplate;
use App\Http\Requests\StoreWhatsAppTemplateRequest;
use App\Http\Requests\UpdateWhatsAppTemplateRequest;
use App\Http\Resources\WhatsappTemplateResource;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @OA\Tag(name="Other", description="Api's Not Assigned To Any Module Yet")
 * @OA\Controller(description="Manage WhatsApp Templates")
 */
class WhatsappTemplateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/whatsapp_templates",
     *     summary="Get all WhatsApp templates",
     *     description="Retrieve a list of WhatsApp templates. Optionally search using the 'query' parameter.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for template name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="States reterived successfully."
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $templates = WhatsAppTemplate::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $templates = $templates
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $templates = $templates->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("States reterived successfully.",WhatsappTemplateResource::collection(resource: $templates), []);
    }

    /**
     * @OA\Post(
     *     path="/whatsapp_templates/store",
     *     summary="Create a new WhatsApp template",
     *     description="Create a new WhatsApp template.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Template created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error creating template"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreWhatsAppTemplateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            $template = WhatsAppTemplate::create($validated);

            DB::commit();
            return sendResponse("Template created successfully", $template, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error creating template", [], false, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/whatsapp_templates/update",
     *     summary="Update a WhatsApp template",
     *     description="Update an existing WhatsApp template.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template updated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error updating template"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateWhatsAppTemplateRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            $template = WhatsAppTemplate::find($request->id);

            $template->update($request->all());
            $template->save();
            activityLog('whatsapp template update',"whatsapp template updated called {$template->name}");
            DB::commit();
            return sendResponse("Template updated successfully", $template);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error updating template", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/whatsapp_templates/delete",
     *     summary="Delete a WhatsApp template",
     *     description="Delete a WhatsApp template by its key.",
     *     tags={"Other"},
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="key", type="string", description="The key of the template to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error deleting template"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete($key)
    {
        DB::beginTransaction();
        try {
            $template = WhatsAppTemplate::where('key', $key)->firstOrFail();
            $template->delete();

            DB::commit();
            return sendResponse("Template deleted successfully", []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error deleting template", [], false, $e->getMessage(), 500);
        }
    }
}