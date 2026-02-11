<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\FacilityAccount;
use App\Models\Hub;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BranchSeeder extends Seeder
{
    public function run()
    {

        $station1 = Station::first();

        $branch_1 = Branch::create([
            'station_id' => $station1->id,
            'hub_id' => 1,
            'name' => 'Branch 1',
            'location' => 'Branch Location 1',
            'address' => 'Branch Address 1',
        ]);

        Account::create([
            "accountable_id" => $branch_1->id,
            "accountable_type" => Branch::class,
        ]);

        $branch_2 = Branch::create([
            'station_id' => 2,
            'hub_id' => 1,
            'name' => 'Branch 2',
            'location' => 'Branch Location 2',
            'address' => 'Branch Address 2',
        ]);

        Account::create([
            "accountable_id" => $branch_2->id,
            "accountable_type" => Branch::class,
        ]);

        // BranchUserSeeding
        $branch_role = Role::create(["name" => "BranchAdmin", "guard_name" => "web"]);

        $branch = User::create([
            'owner_type' => Hub::class,
            'owner_id' => 1,
            'name' => 'branch',
            'email' => 'branch@gmail.com',
            'phone' => "+9123456789",
            'password' => Hash::make('Aa@123456')
        ]);
        BranchUser::create([
            "user_id" => $branch->id,
            "branch_id" => $branch_1->id,
        ]);

        $branch2 = User::create([
            'owner_type' => Hub::class,
            'owner_id' => 1,
            'name' => 'branch',
            'email' => 'branch1@gmail.com',
            'phone' => "+9123456789",
            'password' => Hash::make('Aa@123456')
        ]);
        BranchUser::create([
            "user_id" => $branch2->id,
            "branch_id" => $branch_2->id,
        ]);
        $branch->assignRole($branch_role);
        $branch2->assignRole($branch_role);
    }
}
