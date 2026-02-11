<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Role;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Hub;
use App\Models\BranchUser;
use App\Models\StationUser;
use App\Models\HubUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UsersImport implements ToCollection, WithHeadingRow
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
                $rowArray = $row->toArray();

                // Validate row data, excluding the unique email rule
                $validator = Validator::make($rowArray, $this->rules());
                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }
                $phoneSplit = splitPhoneNumber($row['phone'] ?? '');

                $user = User::updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name' => $row['name'],
                        'country_code' => $phoneSplit['country_code'],
                        'phone' => $phoneSplit['national_number'],
                        'password' => Hash::make($row['password'] ?? 'Aa@123456'),
                    ]
                );

                // Assign role
                $roleName = $row['role'] ?? 'User';
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $user->syncRoles($role);
                }

                // Handle facility assignments
                $this->assignFacilities($user, $row);

                $this->importedCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    private function assignFacilities($user, $row)
    {
        // Assign branches
        if (!empty($row['branches'])) {
            $branchNames = array_map('trim', explode(',', $row['branches']));
            foreach ($branchNames as $branchName) {
                $branch = Branch::where('name', $branchName)->first();
                if ($branch) {
                    BranchUser::firstOrCreate([
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                    ]);
                }
            }
        }

        // Assign stations
        if (!empty($row['stations'])) {
            $stationNames = array_map('trim', explode(',', $row['stations']));
            foreach ($stationNames as $stationName) {
                $station = Station::where('name', $stationName)->first();
                if ($station) {
                    StationUser::firstOrCreate([
                        'user_id' => $user->id,
                        'station_id' => $station->id,
                    ]);
                }
            }
        }

        // Assign hubs
        if (!empty($row['hubs'])) {
            $hubNames = array_map('trim', explode(',', $row['hubs']));
            foreach ($hubNames as $hubName) {
                $hub = Hub::where('name', $hubName)->first();
                if ($hub) {
                    HubUser::firstOrCreate([
                        'user_id' => $user->id,
                        'hub_id' => $hub->id,
                    ]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|phone:OM',
            'role' => 'required|string|exists:roles,name',
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
