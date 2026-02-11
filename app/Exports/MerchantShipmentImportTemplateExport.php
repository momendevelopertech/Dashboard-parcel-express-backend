<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MerchantShipmentImportTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            [
                'PE123456',
                'Oman',
                'Muscat',
                'Muscat',
                'Ahmed Al-Balushi',
                '96899123456',
                'Ruwi, Near City Centre Mall',
                '25.50',
                'COD',
                '100',
                'Electronics',
                '500',
            ],
            [
                'PE123457',
                'Oman',
                'Dhofar',
                'Salalah',
                'Fatima Al-Rashid',
                '96897123456',
                'Al-Dahariz, Building 15',
                '15.75',
                'PREPAID',
                '112',
                'Clothing',
                '300',
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'tracking_no',
            'recipient_country',
            'recipient_state',
            'recipient_city',
            'recipient_name',
            'recipient_cellphone',
            'recipient_street_address',
            'cod',
            'payment_type',
            'recipient_alternate_phone',
            'recipient_zipcode',
            'weight_g'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E7D32']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Sample data styling
        $sheet->getStyle('A2:L3')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F5E8']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Auto-fit row heights
        foreach (range(1, 3) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // tracking_no
            'B' => 18, // recipient_country
            'C' => 18, // recipient_state
            'D' => 15, // recipient_city
            'E' => 20, // recipient_name
            'F' => 18, // recipient_cellphone
            'G' => 30, // recipient_street_address
            'H' => 12, // cod
            'I' => 15, // payment_type
            'J' => 18, // recipient_alternate_phone
            'K' => 12, // recipient_zipcode
            'L' => 12, // weight_g
        ];
    }
} 