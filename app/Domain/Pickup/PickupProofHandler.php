<?php

namespace App\Domain\Pickup;

use App\Models\MerchantPickupShipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Single point of pickup proof persistence.
 * 
 * This class is the ONLY location where pickup_proof should be uploaded and saved.
 * It is designed to be called unconditionally by the PickupOrchestrator - never skipped.
 * 
 * CRITICAL: This handler throws exceptions if proof is missing or upload fails,
 * ensuring the transaction is rolled back and no pickup succeeds without proof.
 */
final class PickupProofHandler
{
    private const STORAGE_PATH = 'public/pickup_proofs';
    private const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_SIZE_KB = 8192;

    /**
     * Validate, upload, and persist the pickup proof to the MerchantPickupShipment.
     * 
     * @param Request $request The HTTP request containing the pickup_proof file
     * @param MerchantPickupShipment $merchantPickupShipment The record to update
     * @return string The stored file path
     * @throws PickupProofException If validation fails or upload fails
     */
    public function handle(Request $request, MerchantPickupShipment $merchantPickupShipment): string
    {
        // Step 1: Validate proof exists
        if (!$request->hasFile('pickup_proof')) {
            Log::error('[PICKUP_PROOF] No pickup_proof file in request', [
                'merchant_pickup_shipment_id' => $merchantPickupShipment->id,
                'request_files' => array_keys($request->allFiles()),
            ]);
            throw new PickupProofException('Pickup proof is required but was not provided.');
        }

        // Step 2: Validate file
        $this->validateFile($request);

        // Step 3: Upload file
        $file = $request->file('pickup_proof');
        $path = $this->uploadFile($file);

        // Step 4: Persist to MerchantPickupShipment
        $merchantPickupShipment->pickup_proof = $path;
        $merchantPickupShipment->save();

        Log::info('[PICKUP_PROOF] Successfully persisted pickup proof', [
            'merchant_pickup_shipment_id' => $merchantPickupShipment->id,
            'path' => $path,
        ]);

        return $path;
    }

    /**
     * Validate the proof file meets requirements.
     * 
     * @throws PickupProofException If validation fails
     */
    public function validate(Request $request): bool
    {
        if (!$request->hasFile('pickup_proof')) {
            return false;
        }

        $validator = Validator::make($request->all(), [
            'pickup_proof' => [
                'required',
                'image',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'max:' . self::MAX_SIZE_KB,
            ],
        ]);

        return !$validator->fails();
    }

    /**
     * Validate and throw exception on failure.
     * 
     * @throws PickupProofException
     */
    private function validateFile(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'pickup_proof' => [
                'required',
                'image',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'max:' . self::MAX_SIZE_KB,
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            Log::error('[PICKUP_PROOF] Validation failed', [
                'errors' => $errors,
            ]);
            throw new PickupProofException(
                'Pickup proof validation failed: ' . implode(', ', $errors)
            );
        }
    }

    /**
     * Upload the file to storage.
     * 
     * @return string The stored path
     * @throws PickupProofException If upload fails
     */
    private function uploadFile($file): string
    {
        try {
            // Use the existing uploadFile helper if available
            if (function_exists('uploadFile')) {
                return uploadFile($file, self::STORAGE_PATH);
            }

            // Fallback to direct storage
            $path = $file->store(self::STORAGE_PATH);
            
            if (!$path) {
                throw new Exception('Storage returned empty path');
            }

            return $path;
        } catch (Exception $e) {
            Log::error('[PICKUP_PROOF] File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PickupProofException(
                'Failed to upload pickup proof: ' . $e->getMessage()
            );
        }
    }
}
