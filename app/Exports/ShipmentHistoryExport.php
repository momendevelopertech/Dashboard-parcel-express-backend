<?php

namespace App\Exports;

use Exception;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Carbon;

class ShipmentHistoryExport implements FromCollection, WithHeadings
{
    protected $shipments;
    protected $columns;

    public function __construct($shipments, $columns)
    {
        $this->shipments = $shipments;
        $this->columns = $columns;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->shipments as $shipment) {
            $row = [];
            foreach ($this->columns as $column) {
                if (in_array($column, ['shipment_date', 'delivery_date', 'created_at', 'updated_at'])) {
                    if (isset($shipment->$column) && !is_null($shipment->$column)) {
                        try {
                            $dateValue = $shipment->$column instanceof Carbon
                                ? $shipment->$column
                                : new Carbon($shipment->$column);
                            $row[] = $dateValue->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            logger()->error('Date parsing error', ['error' => $e->getMessage(), 'value' => $shipment->$column]);
                            $row[] = '';
                        }
                    } else {
                        $row[] = '';
                    }
                } else {
                    $value = $shipment->$column ?? '';
                    if (is_numeric($value)) {
                        $row[] = number_format($value, 2);
                    } else {
                        $row[] = $value;
                    }
                }
            }
            $rows[] = $row;
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $headings = array_map(function ($column) {
            return ucwords(str_replace('_', ' ', $column));
        }, $this->columns);
        return $headings;
    }
}
