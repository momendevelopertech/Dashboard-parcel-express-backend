<?php

namespace App\Exports;

use App\Models\CompanyCommission;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CompanyCommissionExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return CompanyCommission::with('state')->get()->map(function ($commission) {
            return [
                'company' => $commission->company->name,
                'state_id' => $commission->state_id,
                'state_name' => $commission->state->en_name,
                'delivery_fee' => $commission->delivery_fee,
                'pickup_fee' => $commission->pickup_fee,
            ];
        });
    }
    public function headings(): array
    {
        return ['Company', 'State ID', 'State Name', 'Delivery Fee', 'Pickup Fee'];
    }
}
