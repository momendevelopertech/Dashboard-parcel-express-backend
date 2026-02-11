<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ShipmentExport implements FromCollection, WithHeadings
{
    protected $shipments;
    protected $columns;

    public function __construct($shipments, $columns)
    {
        info($shipments);
        $this->shipments = $shipments;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->shipments->map(function ($shipment) {
            return collect($this->columns)->mapWithKeys(function ($column) use ($shipment) {
                $parts = explode('.', $column);
                $attribute = array_pop($parts);
                $currentValue = $shipment;

                foreach ($parts as $part) {
                    $currentValue = optional($currentValue)->{$part};
                }

                return [$column => optional($currentValue)->{$attribute}];
            });
        });
    }

    public function headings(): array
    {
        return collect($this->columns)->map(function ($column) {
            return match ($column) {
                'consignee.name' => 'Consignee Name',
                'consignee.country_key_cellphone' => 'Consignee Code Phone',
                'consignee.cellphone' => 'Consignee Phone',
                'consignee.country_key_alternatePhone' => 'Consignee Code Alternate Phone',
                'consignee.alernatePhone' => 'Consignee Alternate Phone',
                'consignee.country.name' => 'Consignee Country',
                'consignee.governorate.en_name' => 'Consignee Governorate',
                'consignee.state.en_name' => 'Consignee State',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }
}
