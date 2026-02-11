<?php

namespace App\Exports;

use App\Models\StateChannel;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class StateChannelsExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
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
        $q = StateChannel::query();

        if ($this->shipperId) {
            $q->where('shipper_id', $this->shipperId);
        }

        // Optional simple search (matches names/IDs)
        if ($this->search) {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('internal_state_name', 'like', $term)
                    ->orWhere('external_state_name', 'like', $term)
                    ->orWhere('internal_state_id', 'like', $term)
                    ->orWhere('external_state_id', 'like', $term);
            });
        }

        // Sort
        $allowed = ['id', 'internal_state_id', 'external_state_id', 'created_at'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'id';
        $sortDir = strtolower($this->sortDir) === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($sortBy, $sortDir);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Shipper ID',
            'Internal State ID',
            'Internal State Name',
            'External State ID',
            'External State Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->shipper_id,
            $row->internal_state_id,
            $row->internal_state_name,
            $row->external_state_id,
            $row->external_state_name,
            optional($row->created_at)?->toDateTimeString(),
            optional($row->updated_at)?->toDateTimeString(),
        ];
    }

    public function columnFormats(): array
    {
        // Treat IDs as text to avoid Excel auto-format (especially if there are leading zeros)
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
