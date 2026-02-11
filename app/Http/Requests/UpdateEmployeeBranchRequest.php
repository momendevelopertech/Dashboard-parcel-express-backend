<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'morphable_id' => 'required|integer|exists:' . $this->getMorphableTable() . ',id',
            'morphable_type' => 'required|string|in:App\\Models\\Hub,App\\Models\\Station,App\\Models\\Branch',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'morphable_type' => $this->mapMorphableType($this->morphable_type),
        ]);
    }

    protected function mapMorphableType(string $type): string
    {
        $mapping = [
            'hub' => \App\Models\Hub::class,
            'station' => \App\Models\Station::class,
            'branch' => \App\Models\Branch::class,
        ];

        return $mapping[$type] ?? $type;
    }

    protected function getMorphableTable(): string
    {
        return $this->morphable_type === \App\Models\Hub::class ?
            'hubs' : ($this->morphable_type === \App\Models\Station::class ? 'stations' : 'branches');
    }
}
