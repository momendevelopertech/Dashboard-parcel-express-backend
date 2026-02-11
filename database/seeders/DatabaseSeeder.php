<?php

namespace Database\Seeders;

use App\Models\Hub;
use App\Models\Station;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use App\Models\CompanyCommission;
use Database\Seeders\SeedTestPartnerKeySeeder;
use Database\Seeders\PartnersFakeAccountsSeeder;
use Database\Seeders\AllActorsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(CountriesTableSeeder::class);
        $this->call(GovernorateSeeder::class);
        $this->call(StateSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(PlaceSeeder::class);
        $this->call(ShipperSeeder::class);
        $this->call(ConsigneeSeeder::class);
        $this->call(HubSeeder::class);
        $this->call(StationSeeder::class);
        // $this->call(BranchSeeder::class);
        $this->call(MerchantSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(DriverBonusSeeder::class);
        $this->call(ZoneSeeder::class);
        $this->call(ZoneStateSeeder::class);
        $this->call(ShelfSeeder::class);
        $this->call(ShelfCategorySeeder::class);
        $this->call(WhatsappTemplateSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        $this->call(UnitSeeder::class);
        $this->call(MerchantCommissionSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(CountryChannelSeeder::class);
        $this->call(GovernorateChannelSeeder::class);
        $this->call(StateChannelSeeder::class);
        $this->call(CompanyCommissionSeeder::class);
        $this->call(ShipperCommissionSeeder::class);
        $this->call(DriverAppSettingSeeder::class);
        // $this->call(NotificationSeeder::class);
        // $this->call(MerchantInvoiceSeeder::class);
        $this->call(ShipmentTypeSeeder::class);
        // $this->call(TruckSeeder::class);
        $this->call(HubTimezoneSeeder::class);
        $this->call(PermissionSeeder::class);
        // Additional permissions seeder
        $this->call(AppVersionPinSeeder::class);
        // $this->call(ShipmentsSeeder::class);

        // FacilityRoleSeeder::createRoles(Hub::class, 1);
        FacilityRoleSeeder::createRoles(Station::class, 1);
        FacilityRoleSeeder::createRoles(Station::class, 2);
        FacilityRoleSeeder::createRoles(Station::class, 3);

        // Partners Fake Accounts Seeder
        $this->call(PartnersFakeAccountsSeeder::class);
        $this->call(SeedTestPartnerKeySeeder::class);
    }
}
