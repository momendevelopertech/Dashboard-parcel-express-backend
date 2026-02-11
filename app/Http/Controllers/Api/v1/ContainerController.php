<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Container;
use App\Models\User;
use App\Models\Shipment;
use App\Services\ContainerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class ContainerController extends Controller
{
    protected ContainerService $containerService;

    public function __construct(ContainerService $containerService)
    {
        $this->containerService = $containerService;
    }

    /**
     * Get all containers with filters and pagination
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'container_type', 'facility_type', 'facility_id', 'search']);
        $perPage = $request->input('per_page', 15);


        $containers = $this->containerService->getContainers($filters, $perPage);

        return response()->json([
            'message' => 'Containers retrieved successfully',
            'data' => $containers,
        ], Response::HTTP_OK);
    }

    /**
     * Create a new container
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|unique:containers,code',
            'container_number' => 'nullable|string|unique:containers,container_number',
            'tracking_no' => 'nullable|string|unique:containers,tracking_no',
            'container_type' => 'required|string',
            'max_weight' => 'nullable|numeric|min:0',
            'max_volume' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Routing
            'from_hub_id' => 'nullable|exists:hubs,id',
            'current_hub_id' => 'nullable|exists:hubs,id',
            'target_hub_id' => 'nullable|exists:hubs,id',
            'final_hub_id' => 'nullable|exists:hubs,id',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['facility_type'] = facility()->type ?? null;
        $validated['facility_id'] = facility()->id ?? null;

        // Auto-generate handled in service

        $container = $this->containerService->createContainer($validated);

        return response()->json([
            'message' => 'Container created successfully',
            'data' => $container,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get container details with statistics
     */
    public function show(Container $container)
    {
        $details = $this->containerService->getContainerDetails($container);

        return response()->json([
            'message' => 'Container details retrieved successfully',
            'data' => $details,
        ], Response::HTTP_OK);
    }

    /**
     * Update container
     */
    public function update(Request $request, Container $container)
    {
        $validated = $request->validate([
            'container_type' => 'nullable|string',
            'max_weight' => 'nullable|numeric|min:0',
            'max_volume' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'from_hub_id' => 'nullable|exists:hubs,id',
            'current_hub_id' => 'nullable|exists:hubs,id',
            'target_hub_id' => 'nullable|exists:hubs,id',
            'final_hub_id' => 'nullable|exists:hubs,id',
        ]);

        $container->update($validated);

        return response()->json([
            'message' => 'Container updated successfully',
            'data' => $container,
        ], Response::HTTP_OK);
    }

    /**
     * Delete a container
     */
    public function destroy(Container $container)
    {
        $container->delete();

        return response()->json([
            'message' => 'Container deleted successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Add a shipment to a container
     */
    public function addShipment(Request $request, Container $container)
    {
        $validated = $request->validate([
            'shipment_id' => 'required|integer|exists:shipments,id',
        ]);

        try {
            $shipment = Shipment::findOrFail($validated['shipment_id']);
            $user=User::find(auth()->user()->id);
            $this->containerService->addShipment($container, $shipment, $user);

            return response()->json([
                'message' => 'Shipment added to container successfully',
                'data' => [
                    'container' => $container->fresh(),
                    'shipment' => $shipment->fresh(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add shipment to container',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove a shipment from a container
     */
    public function removeShipment(Request $request, Container $container)
    {
        $validated = $request->validate([
            'shipment_id' => 'required|integer|exists:shipments,id',
        ]);

        try {
            $shipment = Shipment::findOrFail($validated['shipment_id']);
            $this->containerService->removeShipment($container, $shipment, auth()->user());

            return response()->json([
                'message' => 'Shipment removed from container successfully',
                'data' => [
                    'container' => $container->fresh(),
                    'shipment' => $shipment->fresh(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove shipment from container',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
    
    /**
     * Add multiple shipments to a container
     */
    public function addMultipleShipments(Request $request, Container $container)
    {
        $validated = $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
        ]);

        // Service could be updated to accept user, or rely on default auth()->id() internal to service calls
        // Since we didn't update service signature for bulk yet, it relies on default. 
        // We should ideally pass user, but service needs update. 
        // For now, restoring as previous functionality (which works via default auth() in service)
        $results = $this->containerService->addMultipleShipments($container, $validated['shipment_ids']);

        if (count($results['failed']) > 0) {
            return response()->json([
                'message' => 'Some shipments failed to be added',
                'data' => $results,
                'container' => $container->fresh(),
            ], Response::HTTP_PARTIAL_CONTENT);
        }

        return response()->json([
            'message' => 'All shipments added to container successfully',
            'data' => $results,
            'container' => $container->fresh(),
        ], Response::HTTP_OK);
    }

    /**
     * Remove multiple shipments from a container
     */
    public function removeMultipleShipments(Request $request, Container $container)
    {
        $validated = $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
        ]);

        $results = $this->containerService->removeMultipleShipments($container, $validated['shipment_ids']);

        if (count($results['failed']) > 0) {
            return response()->json([
                'message' => 'Some shipments failed to be removed',
                'data' => $results,
                'container' => $container->fresh(),
            ], Response::HTTP_PARTIAL_CONTENT);
        }

        return response()->json([
            'message' => 'All shipments removed from container successfully',
            'data' => $results,
            'container' => $container->fresh(),
        ], Response::HTTP_OK);
    }

    /**
     * Move shipments from one container to another
     */
    public function moveShipments(Request $request, Container $container)
    {
        $validated = $request->validate([
            'to_container_id' => 'required|integer|exists:containers,id|different:container',
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
        ]);

        try {
            $toContainer = Container::findOrFail($validated['to_container_id']);
            $results = $this->containerService->moveShipments($container, $toContainer, $validated['shipment_ids']);

            if (count($results['failed']) > 0) {
                return response()->json([
                    'message' => 'Some shipments failed to be moved',
                    'data' => $results,
                    'from_container' => $container->fresh(),
                    'to_container' => $toContainer->fresh(),
                ], Response::HTTP_PARTIAL_CONTENT);
            }

            return response()->json([
                'message' => 'All shipments moved successfully',
                'data' => $results,
                'from_container' => $container->fresh(),
                'to_container' => $toContainer->fresh(),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to move shipments',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get all shipments in a container
     */
    public function getShipments(Container $container)
    {
        $shipments = $this->containerService->getContainerShipments($container);

        return response()->json([
            'message' => 'Container shipments retrieved successfully',
            'data' => $shipments,
        ], Response::HTTP_OK);
    }

    /**
     * Empty a container (remove all shipments)
     */
    public function empty(Container $container)
    {
        try {
            $removedCount = $this->containerService->emptyContainer($container);

            return response()->json([
                'message' => 'Container emptied successfully',
                'data' => [
                    'container' => $container->fresh(),
                    'removed_shipments_count' => $removedCount,
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to empty container',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
    
    /**
     * Seal container
     */
    public function seal(Container $container)
    {
        try {
            $this->containerService->seal($container, auth()->user());
            return response()->json(['message' => 'Container sealed successfully', 'data' => $container->fresh()]);
        } catch (\Exception $e) {
             return response()->json(['message' => 'Failed to seal container', 'error' => $e->getMessage()], 422);
        }
    }



    /**
     * Close container
     */
    public function close(Container $container)
    {
        try {
            $this->containerService->close($container); // close doesn't take user currently in service signature I wrote? Let's check. 
            // close(Container $container) in service I wrote. Correct.
            return response()->json(['message' => 'Container closed successfully', 'data' => $container->fresh()]);
        } catch (\Exception $e) {
             return response()->json(['message' => 'Failed to close container', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update container status (Generic)
     */
    public function updateStatus(Request $request, Container $container)
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $container = $this->containerService->updateStatus($container, $validated['status']);

        return response()->json([
            'message' => 'Container status updated successfully',
            'data' => $container,
        ], Response::HTTP_OK);
    }

    /**
     * Generate print-ready HTML for container waybill
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function printContainer(Request $request)
    {
        $request->validate([
            "tracking_no" => "required|exists:containers,tracking_no"
        ]);

        $tracking_no = request('tracking_no');
        $container = Container::where('tracking_no', $tracking_no)->first();
        
        $html = view('printContainerWaybill', ['container' => $container])->render();

        return response()->json(['html' => $html]);
    }


       public function printMultipleContainers(Request $request)
{
    $data = $request->validate([
        'tracking_numbers' => ['required', 'array', 'min:1'],
        'tracking_numbers.*' => ['string', 'exists:containers,tracking_no'],
    ]);

    $trackingNumbers = $data['tracking_numbers'];

    $containers = Container::with([
        // add relations if you have them
        // 'shipments',
        // 'originWarehouse',
        // 'destinationWarehouse',
    ])
        ->whereIn('tracking_no', $trackingNumbers)
        ->get()
        // keep same order as request
        ->sortBy(fn ($c) => array_search($c->tracking_no, $trackingNumbers))
        ->values();

    $html = view('printContainersWaybills', [
        'containers' => $containers
    ])->render();

    return response()->json(['html' => $html]);
}
}
