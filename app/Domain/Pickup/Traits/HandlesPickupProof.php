<?php

namespace App\Domain\Pickup\Traits;

use App\Models\ShipmentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared proof handling logic for pickup handlers.
 * Extracts common patterns to avoid code duplication.
 */
trait HandlesPickupProof
{
    /**
     * Upload pickup proof from request and optionally create ShipmentProof record.
     *
     * @param Request $request
     * @param int|null $shipmentId For creating ShipmentProof record (optional)
     * @param int $uploadedBy User ID who uploaded the file
     * @param string $proofType Type of proof ('pickup', 'pickup_2', etc.)
     * @param string $uploadPath Path in storage to upload to
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    protected function uploadProofFromRequest(
        Request $request,
        ?int $shipmentId = null,
        int $uploadedBy = 0,
        string $proofType = 'pickup',
        string $uploadPath = 'public/pickup_proofs'
    ): array {
        if (!$request->hasFile('pickup_proof')) {
            return ['success' => true, 'path' => null, 'error' => null];
        }

        $proofPath = uploadFile($request->file('pickup_proof'), $uploadPath);

        if (!$proofPath) {
            return [
                'success' => false,
                'path' => null,
                'error' => 'Failed to upload proof image to S3.'
            ];
        }

        // Create ShipmentProof record if shipmentId provided
        if ($shipmentId) {
            $existingProof = ShipmentProof::where('shipment_id', $shipmentId)
                ->where('type', $proofType)
                ->exists();
                
            if (!$existingProof) {
                ShipmentProof::create([
                    'shipment_id' => $shipmentId,
                    'type' => $proofType,
                    'path' => $proofPath,
                    'uploaded_by' => $uploadedBy,
                ]);
            }
        }

        return ['success' => true, 'path' => $proofPath, 'error' => null];
    }

    /**
     * Return error response for failed proof upload.
     */
    protected function proofUploadFailedResponse(string $message = 'Failed to upload proof image to S3.'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => ['File upload failed'],
            'status' => 500,
        ];
    }

    /**
     * Return error response for pickup task assignment issues.
     */
    protected function pickupTaskAssignmentError(): array
    {
        return [
            'success' => false,
            'message' => 'Pickup task assignment error.',
            'errors' => ['Could not find or create pickup assignment'],
            'status' => 500,
        ];
    }
}
