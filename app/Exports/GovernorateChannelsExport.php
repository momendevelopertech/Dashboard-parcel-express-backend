<?php

namespace App\Exports;

use App\Models\GovernorateChannel;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class GovernorateChannelsExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    use Exportable;

    public function __construct(
        protected ?int $shipperId = null,
        protected ?string $search = null,
        protected ?string $sortBy = 'id',
        protected string $sortDir = 'desc'
    ) {
    }

    public function query()
    {
        /** @var Builder $q */
        $q = GovernorateChannel::query();

        if ($this->shipperId) {
            $q->where('shipper_id', $this->shipperId);
        }

        if ($this->search) {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('internal_governorate_name', 'like', $term)
                    ->orWhere('external_governorate_name', 'like', $term)
                    ->orWhere('internal_governorate_id', 'like', $term)
                    ->orWhere('external_governorate_id', 'like', $term);
            });
        }

        $allowed = ['id', 'internal_governorate_id', 'external_governorate_id', 'created_at'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'id';
        $sortDir = strtolower($this->sortDir) === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($sortBy, $sortDir);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Shipper ID',
            'Internal Governorate ID',
            'Internal Governorate Name',
            'External Governorate ID',
            'External Governorate Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->shipper_id,
            $row->internal_governorate_id,
            $row->internal_governorate_name ?? null,
            $row->external_governorate_id,
            $row->external_governorate_name ?? null,
            optional($row->created_at)?->toDateTimeString(),
            optional($row->updated_at)?->toDateTimeString(),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
