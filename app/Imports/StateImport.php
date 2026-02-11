<?php

namespace App\Imports;

use App\Models\State;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class StateImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use SkipsFailures;

    protected string $mode; // create|upsert

    public function __construct(string $mode = 'create')
    {
        $this->mode = $mode;
    }

    public function model(array $row)
    {
        // أعمدة مطلوبة
        $payload = [
            'en_name' => $row['en_name'] ?? null,
            'ar_name' => $row['ar_name'] ?? null,
            'country_id' => $row['country_id'] ?? null,
            'governorate_id' => $row['governorate_id'] ?? null,
            'station_id' => $row['station_id'] ?? null,
            'lat' => $row['lat'] ?? null,
            'lng' => $row['lng'] ?? null,
        ];

        if ($this->mode === 'upsert' && !empty($row['id'])) {
            $state = State::find($row['id']);
            if ($state) {
                $state->fill($payload);
                $state->save();
                return null; // already updated
            }
        }

        return new State($payload);
    }

    public function rules(): array
    {
        return [
            '*.en_name' => ['required', 'string', 'max:255'],
            '*.ar_name' => ['required', 'string', 'max:255'],
            '*.country_id' => ['required', 'integer', 'exists:countries,id'],
            '*.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            '*.lat' => ['nullable', 'numeric'],
            '*.lng' => ['nullable', 'numeric'],
            '*.id' => ['nullable', 'integer', 'exists:states,id'],
        ];
    }

    public function batchSize(): int
    {
        return 500;
    }
    public function chunkSize(): int
    {
        return 500;
    }
}
