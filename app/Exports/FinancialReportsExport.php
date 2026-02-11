<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinancialReportsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $reports;

    public function __construct(array $reports)
    {
        $this->reports = $reports;
    }

    public function collection()
    {
        return collect($this->reports);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Total Revenue',
            'COD Collected',
            'Expenses',
            'Net Profit'
        ];
    }

    public function map($report): array
    {
        return [
            $report['date'],
            $report['total_revenue'],
            $report['cod_collected'],
            $report['expenses'],
            $report['net_profit']
        ];
    }
}
