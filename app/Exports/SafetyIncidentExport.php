<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SafetyIncidentExport implements FromCollection, WithHeadings, WithMapping
{
    protected $incidents;
    protected $columns;

    public function __construct($incidents, $columns)
    {
        $this->incidents = $incidents;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->incidents;
    }

    public function headings(): array
    {
        $headings = [];
        foreach ($this->columns as $column) {
            $headings[] = ucfirst(str_replace(['_', '.'], [' ', ' '], $column));
        }
        return $headings;
    }

    public function map($incident): array
    {
        $row = [];
        foreach ($this->columns as $column) {
            if (strpos($column, '.') !== false) {
                $relations = explode('.', $column);
                $value = $incident;
                foreach ($relations as $relation) {
                    $value = $value->{$relation} ?? '';
                }
                $row[] = $value;
            } else {
                $row[] = $incident->{$column} ?? '';
            }
        }
        return $row;
    }
} 