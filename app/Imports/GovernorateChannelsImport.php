<?php

namespace App\Imports;

use App\Models\GovernorateChannel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\Importable;

class GovernorateChannelsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    private int $shipperId;
    private int $importedCount = 0;

    public function __construct(int $shipperId)
    {
        $this->shipperId = $shipperId;
    }

    public function model(array $row)
    {
        $internalId = $row['internal_governorate_id'] ?? null;
        $internalName = $row['internal_governorate_name'] ?? null;
        $externalName = trim($row['external_governorate_name'] ?? null);

        if (empty($internalId) || empty($internalName)) {
            return null;
        }

        if (!empty($externalName)) {
            $exists = GovernorateChannel::where('external_governorate_name', $externalName)
                ->where('shipper_id', $this->shipperId)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $this->importedCount++;

        return new GovernorateChannel([
            'shipper_id' => $this->shipperId,
            'internal_governorate_id' => $internalId,
            'internal_governorate_name' => $internalName,
            'external_governorate_id' => $row['external_governorate_id'] ?? null,
            'external_governorate_name' => $externalName,
        ]);
    }

    public function rules(): array
    {
        return [
            'internal_governorate_id' => 'required|integer',
            'internal_governorate_name' => 'required|string|max:255',
            'external_governorate_id' => 'nullable|integer',
            'external_governorate_name' => 'nullable|string|max:255',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
