<?php

namespace App\Domain\Pickup;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * PickupInputDTO - Immutable input for pickup operations.
 * 
 * This DTO standardizes all pickup inputs, replacing the various array
 * structures previously passed through the system.
 * 
 * @see PICKUP_UNIFICATION.md Phase B.3
 */
final class PickupInputDTO
{
    public function __construct(
        public readonly ?string $trackingNo,
        public readonly ?string $preId,
        public readonly ?string $waybillTrackingNo,
        public readonly ?int $pickupTaskId,
        public readonly ?int $merchantId,
        public readonly int $driverId,
        public readonly ?UploadedFile $proofFile,
    ) {}

    /**
     * Create DTO from HTTP request.
     */
    public static function fromRequest(Request $request, int $driverId): self
    {
        return new self(
            trackingNo: self::normalizeString($request->input('tracking_no')),
            preId: self::normalizeString($request->input('pre_id')),
            waybillTrackingNo: self::normalizeString($request->input('waybill_tracking_no')),
            pickupTaskId: $request->filled('pickup_task_id') ? (int) $request->input('pickup_task_id') : null,
            merchantId: $request->filled('merchant_id') ? (int) $request->input('merchant_id') : null,
            driverId: $driverId,
            proofFile: $request->file('pickup_proof'),
        );
    }

    /**
     * Create DTO from array (for backward compatibility with handlers).
     */
    public static function fromArray(array $input, int $driverId, ?UploadedFile $proofFile = null): self
    {
        return new self(
            trackingNo: self::normalizeString($input['tracking_no'] ?? null),
            preId: self::normalizeString($input['pre_id'] ?? null),
            waybillTrackingNo: self::normalizeString($input['waybill_tracking_no'] ?? null),
            pickupTaskId: isset($input['pickup_task_id']) ? (int) $input['pickup_task_id'] : null,
            merchantId: isset($input['merchant_id']) ? (int) $input['merchant_id'] : null,
            driverId: $driverId,
            proofFile: $proofFile ?? ($input['pickup_proof'] ?? null),
        );
    }

    /**
     * Check if at least one identifier is provided.
     */
    public function hasIdentifier(): bool
    {
        return $this->trackingNo !== null 
            || $this->preId !== null 
            || $this->waybillTrackingNo !== null
            || $this->pickupTaskId !== null;
    }

    /**
     * Check if proof file is present.
     */
    public function hasProof(): bool
    {
        return $this->proofFile !== null;
    }

    /**
     * Convert to array for handler compatibility.
     */
    public function toArray(): array
    {
        return [
            'tracking_no' => $this->trackingNo,
            'pre_id' => $this->preId,
            'waybill_tracking_no' => $this->waybillTrackingNo,
            'pickup_task_id' => $this->pickupTaskId,
            'merchant_id' => $this->merchantId,
            'driver_id' => $this->driverId,
            'pickup_proof' => $this->proofFile,
        ];
    }

    /**
     * Normalize string input (trim and convert empty to null).
     */
    private static function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
