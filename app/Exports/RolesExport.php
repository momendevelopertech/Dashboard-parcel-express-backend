<?php

namespace App\Exports;

use App\Models\Role;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RolesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $roles;

    public function __construct($roles)
    {
        $this->roles = $roles;
    }

    public function collection()
    {
        return $this->roles;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Permissions',
            'Created At',
            'Updated At'
        ];
    }

    public function map($role): array
    {
        return [
            $role->name,
            $role->permissions->pluck('name')->implode(', '),
            $role->created_at->format('Y-m-d H:i:s'),
            $role->updated_at->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

            // Set auto size for columns
            'A' => ['width' => 25],
            'B' => ['width' => 40],
            'C' => ['width' => 20],
            'D' => ['width' => 20],
        ];
    }
}
