<?php

namespace App\Exports;

use Exception;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DriverPerformanceExport implements FromCollection, WithHeadings
{
    protected $drivers;
    protected $columns;

    public function __construct($drivers, $columns)
    {
        $this->drivers = $drivers;
        $this->columns = $columns;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->drivers as $driver) {
            $row = [];
            foreach ($this->columns as $column) {
                // Special handling for date-related columns
                if (in_array($column, ['created_at', 'updated_at'])) {
                    if (isset($driver[$column]) && !is_null($driver[$column])) {
                        try {
                            $dateValue = $driver[$column] instanceof Carbon
                                ? $driver[$column]
                                : new Carbon($driver[$column]);
                            $row[] = $dateValue->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            logger()->error('Date parsing error', ['error' => $e->getMessage(), 'value' => $driver[$column]]);
                            $row[] = '';
                        }
                    } else {
                        $row[] = '';
                    }
                } else {
                    // For other columns, use the value directly
                    $row[] = $driver[$column] ?? '';
                }
            }
            $rows[] = $row;
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $headings = array_map(function ($column) {
            // Convert snake_case to Title Case
            return ucwords(str_replace('_', ' ', $column));
        }, $this->columns);
        return $headings;
    }
}
