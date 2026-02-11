<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ShipmentImportTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            // Sample data rows only (headers will be added by headings() method)
            [
                'PE' . date('Y') . '001',
                'Oman',
                'Muscat',
                'Ruwi',
                'Ahmed Al Rashid',
                '+968 9123 4567',
                'Building 123, Way 456, Al Khuwair',
                '25.500',
                'COD',
                '+968 2456 7890',
                '100',
                '25.0',
                '500',
                '1'
            ],
            [
                'PE' . date('Y') . '002',
                'UAE',
                'Dubai',
                'Deira',
                'Sara Mohammed',
                '+971 50 123 4567',
                'Al Rigga Street, Deira',
                '15.750',
                'COD',
                '',
                '',
                '15.0',
                '300',
                '1'
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
            'declare',
            'weight_g',
            'ofd_times'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => '366092']
                ]
            ],
            // Description row styling
            2 => [
                'font' => [
                    'italic' => true,
                    'color' => ['argb' => '666666'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => 'F2F2F2']
                ]
            ],
            // Sample data styling
            4 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => 'E8F4FD']
                ]
            ],
            5 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => 'E8F4FD']
                ]
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // tracking_no
            'B' => 15, // recipient_country
            'C' => 18, // recipient_state
            'D' => 15, // recipient_city
            'E' => 20, // recipient_name
            'F' => 18, // recipient_cellphone
            'G' => 30, // recipient_street_address
            'H' => 12, // cod
            'I' => 15, // payment_type
            'J' => 18, // recipient_alternate_phone
            'K' => 12, // recipient_zipcode
            'L' => 15, // declare
            'M' => 12, // weight_g
            'N' => 12, // ofd_times
        ];
    }
} 