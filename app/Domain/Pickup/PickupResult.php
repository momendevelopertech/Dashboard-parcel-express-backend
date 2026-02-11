<?php

namespace App\Domain\Pickup;

/**
 * Unified result object for all pickup operations.
 * 
 * INVARIANT: If $success === true, then $proofPath MUST be non-null.
 * This guarantees that proof is always persisted for successful pickups.
 */
final class PickupResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $shipmentId,
        public readonly ?int $merchantPickupShipmentId,
        public readonly ?string $trackingNo,
        public readonly ?string $preId,
        public readonly ?string $proofPath,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly array $metadata,
    ) {}

    /**
     * Create a successful pickup result.
     * 
     * @param string $proofPath REQUIRED - proof must be persisted for success
     */
    public static function success(
        string $message,
        string $proofPath,
        ?int $shipmentId = null,
        ?int $merchantPickupShipmentId = null,
        ?string $trackingNo = null,
        ?string $preId = null,
        array $warnings = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            message: $message,
            shipmentId: $shipmentId,
            merchantPickupShipmentId: $merchantPickupShipmentId,
            trackingNo: $trackingNo,
            preId: $preId,
            proofPath: $proofPath,
            errors: [],
            warnings: $warnings,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed pickup result.
     */
    public static function failure(
        string $message,
        array $errors = [],
        ?int $shipmentId = null,
        ?string $trackingNo = null,
        ?string $preId = null,
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            message: $message,
            shipmentId: $shipmentId,
            merchantPickupShipmentId: null,
            trackingNo: $trackingNo,
            preId: $preId,
            proofPath: null,
            errors: $errors,
            warnings: [],
            metadata: $metadata,
        );
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => [
                'shipment_id' => $this->shipmentId,
                'merchant_pickup_shipment_id' => $this->merchantPickupShipmentId,
                'tracking_no' => $this->trackingNo,
                'pre_id' => $this->preId,
                'proof_path' => $this->proofPath,
                'warnings' => $this->warnings,
                ...$this->metadata,
            ],
            'errors' => $this->errors,
        ];
    }
}
