<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StateExport implements FromCollection, WithHeadings
{
    protected $states;
    protected $columns;

    public function __construct($states, $columns)
    {
        $this->states = $states;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->states->map(function ($state) {
            $row = [];
            foreach ($this->columns as $column) {
                if (str_contains($column, 'governorate.')) {
                    $attr = str_replace('governorate.', '', $column);
                    $value = optional($state->governorate)->{$attr};
                } else {
                    $value = $state->{$column} ?? null;
                }
                if (in_array($column, ['created_at', 'updated_at']) && $value) {
                    $value = $state->{$column}->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
                }
                $row[$column] = $value;
            }
            return collect($row);
        });
    }

    public function headings(): array
    {
        return collect($this->columns)->map(function ($column) {
            return match ($column) {
                'governorate.en_name' => 'Governorate (English)',
                'governorate.ar_name' => 'Governorate (Arabic)',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }
}
