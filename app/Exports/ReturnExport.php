<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class ReturnExport implements FromCollection, WithHeadings, WithMapping
{
    protected $returns;
    protected $columns;

    public function __construct($returns, $columns)
    {
        $this->returns = $returns;
        $this->columns = $columns;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->returns;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return collect($this->columns)->map(function ($column) {
            return match ($column) {
                'customer_name' => 'Customer Name',
                'shipment_tracking_no' => 'Tracking Number',
                'return_id' => 'Return ID',
                'created_at' => 'Date Created',
                'updated_at' => 'Last Updated',
                'reason' => 'Reason for Return',
                'status' => 'Status',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        $data = [];
        
        foreach ($this->columns as $column) {
            // Handle date columns
            if ($column === 'created_at' || $column === 'updated_at') {
                if (is_object($row) && isset($row->{$column}) && $row->{$column}) {
                    $data[] = $row->{$column}->format('Y-m-d H:i:s');
                } elseif (is_array($row) && isset($row[$column])) {
                    if (is_string($row[$column])) {
                        $data[] = $row[$column];
                    } elseif (is_object($row[$column]) && method_exists($row[$column], 'format')) {
                        $data[] = $row[$column]->format('Y-m-d H:i:s');
                    } else {
                        $data[] = '';
                    }
                } else {
                    $data[] = '';
                }
            } 
            // Handle regular columns
            else {
                if (is_object($row) && isset($row->{$column})) {
                    $data[] = $row->{$column};
                } elseif (is_array($row) && isset($row[$column])) {
                    $data[] = $row[$column];
                } else {
                    $data[] = '';
                }
            }
        }
        
        return $data;
    }
}
