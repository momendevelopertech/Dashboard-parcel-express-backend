<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    public function collection()
    {
        return $this->users;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Country Code',
            'Role',
            'Branches',
            'Stations',
            'Hubs',
            'Created At'
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->phone,
            $user->country_code,
            $user->roles->first()->name ?? 'N/A',
            $user->branch_users->pluck('branch.name')->implode(', '),
            $user->station_users->pluck('station.name')->implode(', '),
            $user->hub_users->pluck('hub.name')->implode(', '),
            $user->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

            // Set auto size for columns
            'A' => ['width' => 25],
            'B' => ['width' => 30],
            'C' => ['width' => 15],
            'D' => ['width' => 15],
            'E' => ['width' => 20],
            'F' => ['width' => 30],
            'G' => ['width' => 30],
            'H' => ['width' => 30],
            'I' => ['width' => 20],
        ];
    }
}
