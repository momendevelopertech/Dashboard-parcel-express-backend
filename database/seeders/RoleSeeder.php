<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Merchant;
use App\Models\MerchantCommission;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Hub;
use App\Models\HubUser;
use App\Models\Permission;
use App\Models\State;
use App\Models\Station;
use App\Models\StationUser;
use App\Models\Truck;
use App\Models\TruckDriver;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
// use Spatie\Permission\Models\Role;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    private function makeUniquePhone(string $countryCode = '+9', int $length = 9): array
    {
        static $seen = []; // تمنع تكرار الأرقام داخل نفس الـ seeder run

        do {
            $national = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
            $key = $countryCode . $national;
        } while (isset($seen[$key]) || \App\Models\User::where('country_code', $countryCode)->where('phone', $national)->exists());

        $seen[$key] = true;

        return ['country_code' => $countryCode, 'national_number' => $national];
    }
    public function run(): void
    {

        $merchantRole = Role::create(["roleable_id" => 1, "roleable_type" => Hub::class, "name" => "Merchant", "guard_name" => "web"]);
        Role::create(["roleable_id" => 1, "roleable_type" => Hub::class, "name" => "Country Manager", "guard_name" => "web"]);
        $driver_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Driver",
            "guard_name" => "web"
        ]);
        // Split phone number into country code and national number

        // $manager_role = Role::create([
        //     "roleable_id" => 1,
        //     "roleable_type" => Hub::class,
        //     "name" => "Manager",
        //     "guard_name" => "web"
        // ]);
        // $supervisor_role = Role::create([
        //     "roleable_id" => 1,
        //     "roleable_type" => Hub::class,
        //     "name" => "SuperVisor",
        //     "guard_name" => "web"
        // ]);

        // Split phone number into country code and national number
        // $phoneSplit = splitPhoneNumber("+9123456789");
        // $manager = User::create([
        //     'owner_id' => 1,
        //     'owner_type' => Hub::class,
        //     'name' => 'manager',
        //     'email' => 'manager@gmail.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);

        // Split phone number into country code and national number
        // $phoneSplit = splitPhoneNumber("+9123456789");
        // $supervisor = User::create([
        //     'owner_id' => 1,
        //     'owner_type' => Hub::class,
        //     'name' => 'supervisor',
        //     'email' => 'supervisor@gmail.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);

        // $supervisor->assignRole($supervisor_role);
        // $manager->assignRole($manager_role);

        $role = Role::where('name', 'Merchant')->first();
        $role->syncPermissions(Permission::where('type', 'admin')->get());
        // User::whereHas("merchant")->first()->assignRole("Merchant");


        // admin stuff.
        $superadmin_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Super Admin",
            "guard_name" => "web"
        ]);

        $branches = Branch::select('id')->get();
        $stations = Station::select('id')->get();
        $hubs = Hub::select('id')->get();

        // Split phone number into country code and national number
        $phoneSplit = $this->makeUniquePhone();
        $admin = User::create([
            'owner_id' => 1,
            'owner_type' => Hub::class,
            'name' => 'admin',
            'email' => 'superadmin@parcelexpress.com',
            'country_code' => $phoneSplit['country_code'],
            'phone' => $phoneSplit['national_number'],
            'password' => Hash::make('Aa@123456')
        ]);


        $admin->assignRole($superadmin_role);

        foreach ($branches as $branch) {
            BranchUser::create([
                'user_id' => $admin->id,
                'branch_id' => $branch->id,
            ]);
        }

        foreach ($stations as $station) {
            StationUser::create([
                'user_id' => $admin->id,
                'station_id' => $station->id,
            ]);
        }

        foreach ($hubs as $hub) {
            HubUser::create([
                'user_id' => $admin->id,
                'hub_id' => $hub->id,
            ]);
        }
        // New Roles
        //  Station Admin Role
        $station_admin_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Station Admin",
            "guard_name" => "web"
        ]);
        //  Hub Admin Role
        $hub_admin_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Hub Admin",
            "guard_name" => "web"
        ]);
        // Muscat Hub Admin
        // $phoneSplit = $this->makeUniquePhone();
        // $muscat_station_admin = User::create([
        //     'owner_id' => 1,
        //     'owner_type' => Hub::class,
        //     'name' => 'Muscot Hub Admin',
        //     'email' => 'hubadmin.muscat@parcelexpress.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);

        // HubUser::create([
        //     "user_id" => $muscat_station_admin->id,
        //     "hub_id" => 1
        // ]);
        // $muscat_station_admin->assignRole($hub_admin_role);
        // // Sohar Station Admin
        // $phoneSplit = $this->makeUniquePhone();
        // $sohar_station_admin = User::create([
        //     'owner_id' => 3,
        //     'owner_type' => Station::class,
        //     'name' => 'Sohar Hub Admin',
        //     'email' => 'stationadmin.sohar@parcelexpress.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // $sohar_station_admin->assignRole($station_admin_role);
        // // Salalah Station Admin
        // $phoneSplit = $this->makeUniquePhone();
        // $salalah_station_admin = User::create([
        //     'owner_id' => 2,
        //     'owner_type' => Station::class,
        //     'name' => 'Salalah Station Admin',
        //     'email' => 'stationadmin.salalah@parcelexpress.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // $salalah_station_admin->assignRole($station_admin_role);
        // // Ruwi Station Admin
        // $phoneSplit = $this->makeUniquePhone();
        // $ruwi_station_admin = User::create([
        //     'owner_id' => 4,
        //     'owner_type' => Station::class,
        //     'name' => 'Ruwi Station Admin',
        //     'email' => 'stationadmin.ruwi@parcelexpress.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // $ruwi_station_admin->assignRole($station_admin_role);


        //  Hub Admin Role
//        $merchant_admin_role = Role::create([
//            "roleable_id" => 1,
//            "roleable_type" => Hub::class,
//            "name" => "Merchant Admin",
//            "guard_name" => "web"
//        ]);
        //        ------------ Muscat Merchant ------------
        $states = State::select('id')->get();


        //        ------------ Salalah Merchant ------------


        // Create wallet for the merchant


        //        ------------ Sohar Merchant ------------

        $warehouse_admin_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Warehouse Supervisor",
            "guard_name" => "web"
        ]);
        $supervisor_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "SuperVisor",
            "guard_name" => "web"
        ]);
        // Split phone number into country code and national number

        // 2- Driver Admin
        $driver_admin_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Driver Admin",
            "guard_name" => "web"
        ]);
        // Split phone number into country code and national number

        // 4- Vendor Driver
        // $vendor_driver_role = Role::create([
        //     "roleable_id" => 1,
        //     "roleable_type" => Hub::class,
        //     "name" => "Vendor Driver",
        //     "guard_name" => "web"
        // ]);
        // Split phone number into country code and national number
        // $phoneSplit = splitPhoneNumber("+9123456789");
        // $vendor_driver = User::create([
        //     'owner_id' => 1,
        //     'owner_type' => Hub::class,
        //     'name' => 'Vendor Driver',
        //     'email' => 'vendorDriver@gmail.com',
        //     'country_code' => $phoneSplit['country_code'],
        //     'phone' => $phoneSplit['national_number'],
        //     'password' => Hash::make('Aa@123456')
        // ]);
        // $vendor_driver->assignRole($vendor_driver_role);
        // 5- Financial Account
        $financial_account_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Financial Accountant",
            "guard_name" => "web"
        ]);
        // Split phone number into country code and national number

        // 5- Customer Service
        $customer_service_role = Role::create([
            "roleable_id" => 1,
            "roleable_type" => Hub::class,
            "name" => "Customer Service",
            "guard_name" => "web"
        ]);
        // Split phone number into country code and national number

    }
}
