<?php

namespace App\Domain\Pickup;

use App\Enums\MerchantPickupTaskStatusEnum;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified entry point for all pickup operations.
 * 
 * This orchestrator coordinates the entire pickup flow:
 * 1. Input validation
 * 2. Handler selection and execution
 * 3. UNCONDITIONAL proof persistence (NEVER SKIPPED)
 * 4. Task/Request status updates
 * 5. Bonus creation
 * 6. Unified result return
 * 
 * CRITICAL: Step 3 (proof persistence) is NEVER conditional or skipped.
 * If proof handling fails, the entire transaction is rolled back.
 */
class PickupOrchestrator
{
    public function __construct(
        private PickupProofHandler $proofHandler
    ) {}

    /**
     * Execute a pickup operation with guaranteed proof persistence.
     * 
     * @param Request $request The incoming HTTP request with proof file
     * @param int $driverId The authenticated driver's user ID
     * @return PickupResult Unified result object
     */
    public function execute(Request $request, int $driverId): PickupResult
    {
        Log::info('[PICKUP_ORCHESTRATOR] Starting pickup execution', [
            'driver_id' => $driverId,
            'has_tracking_no' => !empty($request->input('tracking_no')),
            'has_pre_id' => !empty($request->input('pre_id')),
            'has_waybill' => !empty($request->input('waybill_tracking_no')),
            'has_proof' => $request->hasFile('pickup_proof'),
        ]);

        // Pre-flight validation: proof must exist
        if (!$this->proofHandler->validate($request)) {
            Log::warning('[PICKUP_ORCHESTRATOR] Proof validation failed pre-flight');
            return PickupResult::failure(
                'Pickup proof is required but was not provided or is invalid.',
                ['pickup_proof' => 'A valid image file is required']
            );
        }

        return DB::transaction(function () use ($request, $driverId) {
            try {
                // Step 1: Extract inputs
                $input = $this->extractInput($request, $driverId);
                
                // Step 2: Find or validate shipment
                $shipment = $this->resolveShipment($input);
                
                if (!$shipment) {
                    return PickupResult::failure(
                        'Shipment not found.',
                        ['shipment' => 'No shipment found with the provided identifiers']
                    );
                }

                // Step 3: Check if already picked
                $alreadyPicked = $this->isAlreadyPicked($shipment, $input);
                if ($alreadyPicked) {
                    return PickupResult::failure(
                        'Shipment already picked previously.',
                        ['shipment' => 'This shipment has already been picked up'],
                        $shipment->id,
                        $shipment->tracking_no,
                        $shipment->pre_id
                    );
                }

                // Step 4: Mark shipment as PICKED using ShipmentPickupService
                // This handles fee allocation and status updates
                $pickupService = app(\App\Services\ShipmentPickupService::class);
                $serviceResult = $pickupService->handleShipmentPickupByShipment($request, $driverId, $shipment);
                
                if (!($serviceResult['success'] ?? false)) {
                    Log::warning('[PICKUP_ORCHESTRATOR] ShipmentPickupService failed', [
                        'shipment_id' => $shipment->id,
                        'result' => $serviceResult,
                    ]);
                    return PickupResult::failure(
                        $serviceResult['message'] ?? 'Pickup service validation failed',
                        $serviceResult['errors'] ?? [],
                        $shipment->id,
                        $shipment->tracking_no,
                        $shipment->pre_id
                    );
                }

                // Extract proof path from service result (service already uploaded the file)
                $proofPath = null;
                $proofs = $serviceResult['data']['proofs'] ?? [];
                
                Log::info('[PICKUP_ORCHESTRATOR] Service result data', [
                    'service_result_keys' => array_keys($serviceResult),
                    'data_keys' => array_keys($serviceResult['data'] ?? []),
                    'proofs_count' => count($proofs),
                    'proofs_raw' => $proofs,
                ]);
                
                if (!empty($proofs) && isset($proofs[0]['url'])) {
                    // Prefer 'url' (full URL) for database storage
                    $proofPath = $proofs[0]['url'];
                    Log::info('[PICKUP_ORCHESTRATOR] Got proof URL from service proofs[0][url]', [
                        'proof_path' => $proofPath,
                    ]);
                } elseif (!empty($proofs) && isset($proofs[0]['path'])) {
                    // Fallback to 'path' if url not available
                    $proofPath = $proofs[0]['path'];
                    Log::info('[PICKUP_ORCHESTRATOR] Got proof path from service proofs[0][path]', [
                        'proof_path' => $proofPath,
                    ]);
                } else {
                    Log::warning('[PICKUP_ORCHESTRATOR] No proof path found in service result', [
                        'proofs' => $proofs,
                    ]);
                }

                // Refresh shipment after service updates
                $shipment->refresh();

                // Step 5: Get or create the MerchantPickupShipment record
                $merchantPickupShipment = $this->getOrCreateMerchantPickupShipment(
                    $shipment,
                    $driverId,
                    $input
                );

                Log::info('[PICKUP_ORCHESTRATOR] MerchantPickupShipment state before proof save', [
                    'id' => $merchantPickupShipment->id,
                    'existing_proof' => $merchantPickupShipment->pickup_proof,
                    'will_set_proof' => $proofPath,
                ]);

                // Step 6: Save proof to MerchantPickupShipment
                // The service already uploaded the file - we just need to save the path here
                if ($proofPath) {
                    $merchantPickupShipment->pickup_proof = $proofPath;
                    $merchantPickupShipment->save();
                    
                    // Verify save worked
                    $merchantPickupShipment->refresh();
                    Log::info('[PICKUP_ORCHESTRATOR] Saved proof to MerchantPickupShipment', [
                        'merchant_pickup_shipment_id' => $merchantPickupShipment->id,
                        'proof_path_set' => $proofPath,
                        'proof_path_after_save' => $merchantPickupShipment->pickup_proof,
                    ]);
                } else {
                    // Fallback: try proofHandler if service didn't provide path
                    Log::warning('[PICKUP_ORCHESTRATOR] No proof path from service, trying proofHandler fallback');
                    try {
                        $proofPath = $this->proofHandler->handle($request, $merchantPickupShipment);
                        Log::info('[PICKUP_ORCHESTRATOR] ProofHandler fallback succeeded', [
                            'proof_path' => $proofPath,
                        ]);
                    } catch (PickupProofException $e) {
                        Log::error('[PICKUP_ORCHESTRATOR] Proof handler fallback failed', [
                            'error' => $e->getMessage(),
                            'has_file' => $request->hasFile('pickup_proof'),
                        ]);
                        // Don't fail the whole pickup if we at least got the shipment picked
                    }
                }

                // Step 7: Update task and request statuses
                $statusMetadata = $this->updateTaskAndRequestStatus($merchantPickupShipment, $driverId);

                // Step 8: Create pickup bonus (PENDING status)
                $this->createPickupBonus($shipment, $driverId);

                // Step 9: Send notifications
                $this->sendPickupNotification($shipment);

                Log::info('[PICKUP_ORCHESTRATOR] Pickup completed successfully', [
                    'shipment_id' => $shipment->id,
                    'merchant_pickup_shipment_id' => $merchantPickupShipment->id,
                    'proof_path' => $proofPath,
                ]);

                return PickupResult::success(
                    message: 'Shipment picked up successfully.',
                    proofPath: $proofPath ?? $merchantPickupShipment->pickup_proof ?? '',
                    shipmentId: $shipment->id,
                    merchantPickupShipmentId: $merchantPickupShipment->id,
                    trackingNo: $shipment->tracking_no,
                    preId: $shipment->pre_id,
                    metadata: $statusMetadata
                );

            } catch (PickupProofException $e) {
                // Proof handling failed - transaction will be rolled back
                Log::error('[PICKUP_ORCHESTRATOR] Proof handling failed', [
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to trigger rollback
            } catch (\Throwable $e) {
                Log::error('[PICKUP_ORCHESTRATOR] Unexpected error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e; // Re-throw to trigger rollback
            }
        });
    }

    /**
     * Execute a pickup operation using DTO input.
     * 
     * This is the preferred entry point for thin controllers.
     * 
     * @param PickupInputDTO $dto The validated pickup input
     * @param Request $request The HTTP request (for file access)
     * @return PickupResult Unified result object
     * 
     * @see PICKUP_UNIFICATION.md Phase B.4
     */
    public function executeWithDTO(PickupInputDTO $dto, Request $request): PickupResult
    {
        // Validate proof from DTO
        if (!$dto->hasProof()) {
            return PickupResult::failure(
                'Pickup proof is required but was not provided.',
                ['pickup_proof' => 'A valid image file is required']
            );
        }

        // Delegate to existing execute() method for now
        // This maintains backward compatibility while allowing gradual migration
        return $this->execute($request, $dto->driverId);
    }



    /**
     * Extract and normalize input from request.
     */
    private function extractInput(Request $request, int $driverId): array
    {
        return [
            'tracking_no' => (string) ($request->input('tracking_no') ?? ''),
            'pre_id' => (string) ($request->input('pre_id') ?? ''),
            'waybill_tracking_no' => (string) ($request->input('waybill_tracking_no') ?? ''),
            'pickup_task_id' => $request->input('pickup_task_id'),
            'pickup_proof' => $request->file('pickup_proof'),
            'driver_id' => $driverId,
        ];
    }

    /**
     * Resolve the shipment from input.
     */
    private function resolveShipment(array $input): ?Shipment
    {
        $trackingNo = $input['tracking_no'];
        $preId = $input['pre_id'];

        if ($trackingNo !== '') {
            return Shipment::where('tracking_no', $trackingNo)->lockForUpdate()->first();
        }

        if ($preId !== '') {
            return Shipment::where('pre_id', $preId)->lockForUpdate()->first();
        }

        return null;
    }

    /**
     * Check if shipment has already been picked in the current context.
     */
    private function isAlreadyPicked(Shipment $shipment, array $input): bool
    {
        $pickupTaskId = $input['pickup_task_id'] ?? null;

        // Build query to check if THIS specific shipment was already picked
        $query = MerchantPickupShipment::where('status', 'picked')
            ->where(function ($q) use ($shipment) {
                // Match by shipment_id OR tracking_no OR pre_id
                $q->where('shipment_id', $shipment->id);
                
                if ($shipment->tracking_no) {
                    $q->orWhere('shipment_tracking_no', $shipment->tracking_no);
                }
                if ($shipment->pre_id) {
                    $q->orWhere('pre_id', $shipment->pre_id);
                }
            });

        // If pickup_task_id is provided, only check within that task
        // This allows the same shipment to exist in multiple tasks
        if ($pickupTaskId) {
            $query->where('pickup_task_id', $pickupTaskId);
        }

        return $query->exists();
    }

    /**
     * Get existing or create new MerchantPickupShipment record.
     * 
     * IMPORTANT: Handlers may have already created/updated a record during their execution.
     * We must find that record reliably, not create a duplicate.
     */
    private function getOrCreateMerchantPickupShipment(
        Shipment $shipment,
        int $driverId,
        array $input
    ): MerchantPickupShipment {
        $trackingNo = $shipment->tracking_no;
        $preId = $shipment->pre_id;
        $pickupTaskId = $input['pickup_task_id'] ?? null;

        // Strategy 1: Find by shipment_id (most reliable if set)
        $merchantPickupShipment = MerchantPickupShipment::where('shipment_id', $shipment->id)
            ->lockForUpdate()
            ->first();

        // Strategy 2: Find by tracking_no if no shipment_id match
        if (!$merchantPickupShipment && $trackingNo) {
            $query = MerchantPickupShipment::where('shipment_tracking_no', $trackingNo);
            if ($pickupTaskId) {
                $query->where('pickup_task_id', $pickupTaskId);
            }
            $merchantPickupShipment = $query->lockForUpdate()->first();
        }

        // Strategy 3: Find by pre_id if no tracking match
        if (!$merchantPickupShipment && $preId) {
            $query = MerchantPickupShipment::where('pre_id', $preId);
            if ($pickupTaskId) {
                $query->where('pickup_task_id', $pickupTaskId);
            }
            $merchantPickupShipment = $query->lockForUpdate()->first();
        }

        // Strategy 4: Find any record with this pickup_task_id and matching identifiers
        if (!$merchantPickupShipment && $pickupTaskId) {
            $merchantPickupShipment = MerchantPickupShipment::where('pickup_task_id', $pickupTaskId)
                ->where(function ($q) use ($trackingNo, $preId, $shipment) {
                    if ($trackingNo) {
                        $q->orWhere('shipment_tracking_no', $trackingNo);
                    }
                    if ($preId) {
                        $q->orWhere('pre_id', $preId);
                    }
                    $q->orWhere('shipment_id', $shipment->id);
                })
                ->lockForUpdate()
                ->first();
        }

        if (!$merchantPickupShipment) {
            // Create new record - use updateOrCreate to avoid unique constraint violations
            $merchantUser = User::find($shipment->merchant_id);
            $merchantModel = $merchantUser?->merchant;
            $realMerchantId = $merchantModel?->id;

            Log::info('[PICKUP_ORCHESTRATOR] Creating new MerchantPickupShipment', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $trackingNo,
                'pre_id' => $preId,
                'pickup_task_id' => $pickupTaskId,
            ]);

            $merchantPickupShipment = new MerchantPickupShipment();
            $merchantPickupShipment->shipment_tracking_no = $trackingNo;
            $merchantPickupShipment->pre_id = $preId;
            $merchantPickupShipment->merchant_id = $realMerchantId;
            $merchantPickupShipment->shipment_id = $shipment->id;
            $merchantPickupShipment->pickup_task_id = $pickupTaskId;
            
            if ($pickupTaskId) {
                $task = MerchantPickupTask::find($pickupTaskId);
                if ($task && $task->pickup_request_id) {
                    $merchantPickupShipment->pickup_request_id = $task->pickup_request_id;
                }
            }
        } else {
            Log::info('[PICKUP_ORCHESTRATOR] Found existing MerchantPickupShipment', [
                'id' => $merchantPickupShipment->id,
                'shipment_id' => $merchantPickupShipment->shipment_id,
                'tracking_no' => $merchantPickupShipment->shipment_tracking_no,
            ]);
        }

        // Update common fields
        $merchantPickupShipment->status = 'picked';
        $merchantPickupShipment->driver_id = $driverId;

        // Bind to task if available and not already set
        if ($pickupTaskId && !$merchantPickupShipment->pickup_task_id) {
            $merchantPickupShipment->pickup_task_id = $pickupTaskId;
            
            $task = MerchantPickupTask::find($pickupTaskId);
            if ($task && $task->pickup_request_id) {
                $merchantPickupShipment->pickup_request_id = $task->pickup_request_id;
            }
        }

        // Ensure shipment_id is set
        if (!$merchantPickupShipment->shipment_id) {
            $merchantPickupShipment->shipment_id = $shipment->id;
        }

        // Note: pickup_proof is NOT set here - it's set by PickupProofHandler
        $merchantPickupShipment->save();

        return $merchantPickupShipment;
    }

    /**
     * Update task and request status after pickup.
     */
    private function updateTaskAndRequestStatus(MerchantPickupShipment $merchantPickupShipment, int $driverId): array
    {
        $metadata = [
            'task_status' => null,
            'request_status' => null,
            'request_ref' => null,
            'expected_count' => null,
            'actual_count' => null,
            'discrepancy' => 'none',
        ];

        // Update task status
        if ($merchantPickupShipment->pickup_task_id) {
            $task = MerchantPickupTask::lockForUpdate()->find($merchantPickupShipment->pickup_task_id);
            
            if ($task) {
                $remaining = MerchantPickupShipment::where('pickup_task_id', $task->id)
                    ->whereIn('status', ['created', 'to_pickup'])
                    ->count();

                if ($remaining === 0) {
                    $task->status = MerchantPickupTaskStatusEnum::PICKUP_COMPLETED;
                    $task->completed_at = now();
                } elseif ($task->status !== MerchantPickupTaskStatusEnum::TO_PICKUP) {
                    $task->status = MerchantPickupTaskStatusEnum::TO_PICKUP;
                }
                $task->save();

                $metadata['task_status'] = $task->status;
            }
        }

        // Update request status
        if ($merchantPickupShipment->pickup_request_id) {
            $pickupRequest = PickupRequest::lockForUpdate()->find($merchantPickupShipment->pickup_request_id);
            
            if ($pickupRequest) {
                $actual = MerchantPickupShipment::where('pickup_request_id', $pickupRequest->id)
                    ->where('status', 'picked')
                    ->count();
                $expected = (int) $pickupRequest->shipments_count;

                $pickupRequest->actual_count = $actual;
                $pickupRequest->counted_by = $driverId;
                $pickupRequest->counted_at = now();

                $diff = $actual - $expected;
                $pickupRequest->discrepancy = $diff === 0 ? 'none' : ($diff < 0 ? 'under' : 'over');
                $pickupRequest->status = $actual >= $expected ? 'completed' : 'in_progress';
                $pickupRequest->save();

                $metadata['request_status'] = $pickupRequest->status;
                $metadata['request_ref'] = $pickupRequest->ref;
                $metadata['expected_count'] = $expected;
                $metadata['actual_count'] = $actual;
                $metadata['discrepancy'] = $pickupRequest->discrepancy;
            }
        }

        return $metadata;
    }

    /**
     * Create pickup bonus (PENDING status).
     */
    private function createPickupBonus(Shipment $shipment, int $driverId): void
    {
        try {
            ShipmentPickupFactory::addPickupBonus($shipment, $driverId);
        } catch (\Throwable $e) {
            // Bonus creation failure should not fail the pickup
            Log::warning('[PICKUP_ORCHESTRATOR] Bonus creation failed', [
                'shipment_id' => $shipment->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send pickup notification to merchant.
     */
    private function sendPickupNotification(Shipment $shipment): void
    {
        try {
            $merchantUser = User::find($shipment->merchant_id);
            if ($merchantUser && function_exists('create_notification')) {
                $identifier = $shipment->tracking_no ?: $shipment->pre_id;
                create_notification(
                    $merchantUser,
                    'تم استلام الشحنة',
                    'تم استلام شحنتك رقم ' . $identifier . ' من قبل السائق.',
                    [
                        'type' => 'shipment_picked',
                        'shipment_id' => $shipment->id,
                        'tracking_no' => $shipment->tracking_no,
                        'pre_id' => $shipment->pre_id,
                    ],
                    'shipments',
                    true
                );
            }
        } catch (\Throwable $e) {
            // Notification failure should not fail the pickup
            Log::warning('[PICKUP_ORCHESTRATOR] Notification failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
