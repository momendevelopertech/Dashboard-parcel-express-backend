<?php

namespace App\Domain\Pickup;

interface ShipmentPickupHandlerInterface
{
    /**
     * Handle the specific shipment pickup logic for this handler.
     * @param array $input  The validated input from the request (tracking_no, pre_id, waybill_tracking_no, proof, proof, etc.)
     * @param \Illuminate\Http\Request $request
     * @return array [ 'success' => bool, 'message' => string, 'data' => array, ... ]
     */
    public function handle(array $input, \Illuminate\Http\Request $request): array;
}
