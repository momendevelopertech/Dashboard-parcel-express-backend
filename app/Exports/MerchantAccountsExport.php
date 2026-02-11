<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MerchantAccountsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $transactions;

    public function __construct(Collection $transactions)
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
            'Merchant Name',
            'Date',
            'Reference',
            'Type',
            'Description',
            'Amount',
        ];
    }

    public function map($row): array
    {
        // تم تعديل طريقة الوصول إلى البيانات من $row['key'] إلى $row->key
        return [
            $row->merchant_name,
            $row->created_at,
            $row->reference,
            $row->type,
            $row->description,
            $row->amount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '22C55E']]],
            'F' => [
                'font' => ['color' => [
                    'callback' => function (\Maatwebsite\Excel\Row $row) {
                        return $row->getCell('F')->getValue() < 0 ? 'FF0000' : '006400';
                    },
                ]],
            ],
        ];
    }
}
