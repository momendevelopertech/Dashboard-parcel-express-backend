<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use DB;

class RolesImport implements ToCollection, WithHeadingRow
{
    private $importedCount = 0;
    private $errors = [];

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $data = $row->toArray();
                $rules = $this->rules();
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }
                // Check for existing role by name to decide whether to update or create
                $role = Role::updateOrCreate(
                    [
                        'name' => $row['name'],
                        'roleable_id' => facility() ? facility()->id : null,
                        'roleable_type' => facility() ? facility()->type : null,
                        'guard_name' => 'web'
                    ],
                    [] // The second parameter is for attributes to set on creation/update.
                );

                // Assign permissions
                if (!empty($row['permissions'])) {
                    $permissionNames = array_map('trim', explode(',', $row['permissions']));
                    $permissions = Permission::whereIn('name', $permissionNames)->get();

                    if ($permissions->isNotEmpty()) {
                        $role->syncPermissions($permissions);
                    }
                }

                $this->importedCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    /**
     * Define validation rules for each row.
     * The `unique` rule is removed as we are using upsert logic.
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|string',
        ];
    }

    /**
     * Get the count of successfully imported roles.
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get the errors that occurred during the import.
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
