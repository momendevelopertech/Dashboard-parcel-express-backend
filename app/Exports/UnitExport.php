<?php

namespace App\Exports;

use Exception;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class UnitExport implements FromCollection, WithHeadings //, WithCustomCsvSettings
{
    protected $units;
    protected $columns;

    public function __construct($units, $columns)
    {
        $this->units = $units;
        $this->columns = $columns;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->units as $unit) {
            $row = [];
            foreach ($this->columns as $column) {
                if (in_array($column, ['created_at', 'updated_at'])) {
                    if (isset($unit->$column) && !is_null($unit->$column)) {
                        try {
                            $dateValue = $unit->$column instanceof Carbon
                                ? $unit->$column
                                : new Carbon($unit->$column);
                            $row[] = $dateValue->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            logger()->error('Date parsing error', ['error' => $e->getMessage(), 'value' => $unit->$column]);
                            $row[] = '';
                        }
                    } else {
                        $row[] = '';
                    }
                } else {
                    $row[] = $unit->$column ?? '';
                }
            }
            $rows[] = $row;
        }
        info($row);
        return collect($rows);
    }

    public function headings(): array
    {
        $headings = array_map(function ($column) {
            return ucwords(str_replace('_', ' ', $column));
        }, $this->columns);
        return $headings;
    }

    // public function getCsvSettings(): array
    // {
    //     return [
    //         'use_bom' => true,
    //     ];
    // }
}
