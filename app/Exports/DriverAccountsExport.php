<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverAccountsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $transactions;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
    }

    public function collection()
    {
        return $this->transactions;
    }

    public function headings(): array
    {
        return [
            'Driver Name', // أضفنا هذا العمود
            'Date',
            'Reference',
            'Type',
            'Description',
            'Amount'
        ];
    }

    public function map($row): array // تم تغيير $transaction إلى $row لتكون أكثر دلالة
    {
        // الوصول إلى البيانات كمصفوفة بدلاً من كائن
        return [
            $row['driver_name'],
            $row['created_at'],
            $row['reference'],
            $row['type'],
            $row['description'],
            $row['amount']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
