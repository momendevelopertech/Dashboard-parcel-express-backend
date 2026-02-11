<?php

namespace App\Imports;

use App\Models\DriverCommission;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CommissionImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $driverId = $row['driver_id'] ?? null;

        return DriverCommission::updateOrCreate(
            [
                'driver_id' => $driverId, 
                'state_id'  => $row['state_id'],
            ],
            [
                'delivery_fee' => $row['delivery_fee'],
                'pickup_fee'   => $row['pickup_fee'],
            ]
        );
    }
}
