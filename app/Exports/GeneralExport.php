<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GeneralExport implements FromCollection, WithHeadings
{
    protected $rows;
    protected $columns;

    public function __construct($rows, $columns)
    {
        info($rows);
        $this->rows = $rows;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return collect($this->columns)->mapWithKeys(function ($column) use ($row) {
                $parts = explode('.', $column);
                $attribute = array_pop($parts);
                $currentValue = $row;

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
                'country.name' => 'Country',
                'governorate.en_name' => 'Governorate',
                'state.en_name' => 'State',
                'place.en_name' => 'Place',
                'hub.name' => 'Hub',
                'driver.phone' => 'Phone',
                'driver.company.name' => 'Company',
                'authenticatable.name' => 'Name',
                'user.name' => 'Name',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }
}
