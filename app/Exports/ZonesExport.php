<?php

namespace App\Exports;

use App\Models\Zone;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;

class ZonesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $zones;

    public function __construct($zones)
    {
        $this->zones = $zones;
    }

    public function collection()
    {
        return $this->zones;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Coordinates',
            'Governorate IDs',
            'State IDs',
            'Place IDs',
            'Owner Type',
            'Owner ID',
            'Created At'
        ];
    }

    public function map($zone): array
    {
        // الحصول على الإحداثيات كـ GeoJSON
        $coordinates = DB::selectOne(
            'SELECT ST_AsGeoJSON(coordinates) as geojson FROM zones WHERE id = ?',
            [$zone->id]
        );

        // الحصول على IDs بدلاً من الأسماء
        $governorateIds = $zone->governorates ? $zone->governorates->pluck('id')->implode(',') : '';
        $stateIds = $zone->selectedStates->pluck('id')->implode(',');
        $placeIds = $zone->assignedPlaces->pluck('id')->implode(',');

        return [
            $zone->name,
            $coordinates->geojson ?? 'N/A',
            $governorateIds,
            $stateIds,
            $placeIds,
            $zone->owner_type ? class_basename($zone->owner_type) : 'N/A',
            $zone->owner_id ?? 'N/A',
            $zone->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

            // Set auto size for columns
            'A' => ['width' => 25],
            'B' => ['width' => 50],
            'C' => ['width' => 20],
            'D' => ['width' => 20],
            'E' => ['width' => 20],
            'F' => ['width' => 20],
            'G' => ['width' => 15],
            'H' => ['width' => 20],
        ];
    }
}
