<?php

namespace App\Imports;

use App\Models\StateChannel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\Importable;

class StateChannelsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    private $shipperId;
    private $importedCount = 0;
    private $errors = [];

    public function __construct($shipperId)
    {
        $this->shipperId = $shipperId;
    }

    public function model(array $row)
    {
        $internalId = $row['internal_state_id'] ?? null;
        $internalName = $row['internal_state_name'] ?? null;
        $externalName = trim($row['external_state_name'] ?? null);

        if (empty($internalId) || empty($internalName)) {
            return null;
        }

        if (!empty($externalName)) {
            $exists = StateChannel::where('external_state_name', $externalName)
                ->where('shipper_id', $this->shipperId)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $this->importedCount++;

        return new StateChannel([
            'shipper_id' => $this->shipperId,
            'internal_state_id' => $internalId,
            'internal_state_name' => $internalName,
            'external_state_id' => $row['external_state_id'] ?? null,
            'external_state_name' => $externalName,
        ]);
    }

    public function rules(): array
    {
        return [
            'internal_state_id' => 'required|integer',
            'internal_state_name' => 'required|string|max:255',
            'external_state_id' => 'nullable|integer',
            'external_state_name' => 'nullable|string|max:255',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'internal_state_id.required' => 'Internal State ID is required',
            'internal_state_id.integer' => 'Internal State ID must be an integer',
            'internal_state_name.required' => 'Internal State Name is required',
            'external_state_id.integer' => 'External State ID must be an integer',
        ];
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}