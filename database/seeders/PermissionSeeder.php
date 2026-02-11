<?php

namespace Database\Seeders;

use App\Models\Hub;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Empty permissions table first (requested)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Cruds
        $roleModules = [
            "Dashboard",
            "Analytics",
            "Role",
            "Permission",
            "User",
            "Shipper",
            "State",
            "City",
            "Zone",
            "Rule",
            "Consignee",
            "Station",
            "Branch",
            "Shipment",
            "Shipment Amount",
            "Shipment Information",
            "Shipment Item",
            "Container",
            "Shipment Future",
            "Merchant",
            "Expenses",
            "Shelves",
            "Shelf",
            "Shelf Category",
            "Delivery Exception",
            "Unit",
            "Shipment Status",
            "Delivery Commission",
            "Governorate",
            "Place",
            "Merchant Waybill",
            "Driver Waybill",
            "Transfers Task",
            "Transfers",
            "contracts",
            "history tracking",
            "Daily delivery tasks",
            "Scanning",
            "Driver Status",
            "Drivers",
            "follow-ups",
            "COD Collection",
            "Manifest Management",
            "Sallary Bill Management",
            "salaries",
            "Vehicles",
            "Assignments",
            "Service Logs",
            "licenses",
            "CRM Task",
            "CRM Scenario",
            "CRM Complaint",
            "Pickup Task",
            "Assign Pickup Task",
            "Employee Department",
            "Employee Position",
            "Employee",
            "Work Time",
            "Employee Branch",
            "Employee Payroll",
            "Hierarchy Level",
            "Employee Hierarchy",
            "Leave Reason",
            "Leave Request",
            "Leave Request Approval",
            "Hub",
            "Truck",
            "Truck Driver",
            "Operation",
            "Merchant Commission",
            "Problems",
            "Fine",
            "Company Accounts",
            "Country Channel",
            "Governorate Channel",
            "State Channel",
            "Shipper Commission",
            "Driver Bonus",
            "Abnormality",
            "Invoice",
            "Sort",
            "StockOutTask",
            "Whatsapp Template",
            "Driver App Setting",
            "Login History",
            "WMS",
            "RTO",
            "CRM",
            "Analytics",
            "Financial",
            "Fleet",
            "Return",
            "Manual RTO",
            "Auto RTO",
            "Quality Check",
            "Session",
            "Activity Log",
            "User Action",
            "Compliance Checklist",
            "Legal Document",
            "Ticket",
            "Live Chat",
            "Merchant Ticket Chat",
            "Chat",
            "Merchant Address Book",
            "Merchant Branch",
            "Inventory Items",
            "Account",
            "Merchant Accounts",
            "Contact History",
            "Outsourced Shipment",
            "Pickup Unassigned Shipment",
            "Archived Shipment",
            "Quick Stats",
            "Alerts",
            "Daily Summary",
            "Setting",
            "Driver Accounts",
            "Driver Runsheet",
            "Financial Request",
            "Salary Bill Management",
            "Workspace Accounts",
            "Partner Dashboard",
            "Guest Driver",
            "Guest Driver Shipment",
            "Guest Customer Shipment",
            "Merchant Dashboard",
            "Merchant Summary",
            "Merchant Notification",
            "Merchant Support",
            "Merchant Wallet",
            "Returned Shipment",
            "Pickup collections",
            "COD collections",
            "Pickup Unnumbered Shipment",
            // New distinct modules required by frontend nav
            "Customer Created Shipments",
            "Reprint",
            "Address Updates",
            "OFD List",
            "Pickup Collection",
            // Finance Module
            "Finance Dashboard",
            "Financial Report",
        ];

        $baseAbilities = ["access", "create", "update", "delete"];

        $moduleExtras = [
            "Shipment" => ["print", "import", "import_template", "export", "walkin_create"],
            "Outsourced Shipment" => ["print", "import", "import template", "export"],
            'Customer Created Shipments' => ["access", "print", "update"],
            "Container" => ["print", "import", "import_template", "export"],
            "Zone" => ["import", "export"],
            "User" => ["import", "export"],
            "Role" => ["import", "export"],
            "Drivers" => ["export","restore","archived"],
            "Merchant" => ["archived","restore"],
            "Driver Status" => ["export"],
            "Driver Accounts" => ["export"],
            "Quality Check" => ["send warning"],
            "Unit" => ["export"],
            "Governorate" => ["export"],
            "State" => ["import", "export"],
            "Place" => ["import"],
            "Hub" => ["export"],
            "Station" => ["export"],
            "Permission" => ["export"],
            "Truck" => ["print", "export"],
            "Truck Driver" => ["export"],
        ];
        // Explicit abilities for modules that should not use default CRUD
        $moduleAbilitiesMap = [
            'Reprint' => ["access"],
            'Shipment Future' => ["access", "delete", "print"],
            'Assign Pickup Task' => ["access", "create"],
            'Workspace Accounts' => ["access"],
            'Transfers' => ["access"],
            'Merchant Accounts' => ["access", "export"],
            'Finance Dashboard' => ["access"],
            'Financial Report' => ["access", "export"],
        ];

        $individualPermissions = [
            "Assign Shipment",
            "Assign Shipment To Shelf",
            "Outbound",
            "Realtime Query",
            "Realtime Tracking",   // from PermissionSeeder2.php
            "Notification",
            "Shipment Print",
            "Shipment Import",
            "Shipment Import Template",
            "Shipment Export",
            "Driver Status",
            "Guest Driver",
            "Guest Driver Shipment",
            "Guest Customer Shipment",
            "Driver Runsheet",
            "Quality Check",
            "Safety Incident",
            "Activity Logs",
            "User Actions",
            "Sessions Management",
            "Login History",
            "Driver Invoices",
            "Merchant Invoices",
            "Returned Shipments",
        ];

        // Create individual permissions for admin
        foreach ($individualPermissions as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web', 'type' => 'admin']);
            $name = $perm . " access";
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web', 'type' => 'admin']);
            info($permission);
        }

        // Create individual permissions for userTypes (branch, station, hub)
        // foreach ($userTypes as $roleName => $type) {
        //     foreach ($individualPermissions as $perm) {

        //         $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web', 'type' => $type]);
        //         $name = $perm . " access";
        //         $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web', 'type' => $type, 'parent_id' => $permission->id]);
        //         info($permission);
        //     }
        // }

        // // Create permissions for userTypes (branch, station, hub) for modules
        // foreach ($userTypes as $roleName => $type) {
        //     foreach ($roleModules as $module) {
        //         $parentPermission = Permission::firstOrCreate(
        //             ['name' => $module, 'guard_name' => 'web', 'type' => $type, 'parent_id' => null]
        //         );
        //         foreach ($abilities as $ability) {
        //             Permission::firstOrCreate(
        //                 [
        //                     'name' => "$module $ability",
        //                     'guard_name' => 'web',
        //                     'parent_id' => $parentPermission->id,
        //                     'type' => $type,
        //                 ]
        //             );
        //         }
        //     }
        // }

        // pinfo([
        //     "branch permissoins count" => Permission::where('type', 'branch')->count(),
        //     "station permissoins count" => Permission::where('type', 'station')->count(),
        //     "hub permissoins count" => Permission::where('type', 'hub')->count(),
        // ]);

        // foreach ($userTypes as $roleName => $type) {
        //     $role = Role::where('name', $roleName)->first();
        //     pinfo([
        //         "permissions" => Permission::where('type', $type)->get(),
        //         "role" => $role,
        //     ], "permissions for " . $roleName . " and it's type is " . $type);
        // }

        // // Assign child permissions to each user-type role (branch, station, hub)
        // foreach ($userTypes as $roleName => $type) {
        //     $role = Role::where('name', $roleName)->first();

        //     if ($role) {
        //         // Fetch permissions for this specific type BEFORE using the variable
        //         $childPermissions = Permission::where('type', $type)->get();

        //         // Optional: log how many permissions were attached (helps future debugging)
        //         info("Assigned {$childPermissions->count()} {$type} permissions to role {$roleName}");

        //         // Sync the permissions
        //         $role->syncPermissions($childPermissions);
        //     } else {
        //         // Role not found – log it so the developer can investigate
        //         info("Role {$roleName} was not found. Skipping permission sync.");
        //     }
        // }
        foreach ($roleModules as $module) {
            $parent = Permission::firstOrCreate([
                'name' => $module,
                'guard_name' => 'web',
                'type' => 'admin',
                'parent_id' => null,
            ]);

            // Use explicit abilities when provided, otherwise default CRUD + extras
            $abilities = $moduleAbilitiesMap[$module] ?? array_merge($baseAbilities, $moduleExtras[$module] ?? []);

            foreach ($abilities as $ability) {
                Permission::firstOrCreate([
                    'name' => "$module $ability",   // أمثلة: "Shipment access", "Shipment print", ...
                    'guard_name' => 'web',
                    'parent_id' => $parent->id,
                    'type' => 'admin',
                ]);
            }
        }

        $rolePermissions = self::getRolePermissions();

        foreach (array_keys($rolePermissions) as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Assign permissions
        foreach ($rolePermissions as $roleName => $permissionGroups) {
            if ($role = Role::where('name', $roleName)->first()) {
                $permissionIds = [];
                foreach ($permissionGroups as $permissions) {
                    foreach ($permissions as $permissionName) {
                        $permission = Permission::where('name', $permissionName)
                            ->where('type', 'admin')
                            ->first();

                        if ($permission) {
                            $permissionIds[] = $permission->id;
                        }
                    }
                }

                $role->syncPermissions(array_unique($permissionIds));
            }
        }
    }


    public static function getRolePermissions()
    {
        return [
            'Super Admin' => [
                // All permissions have full CRUD (✔)
                // OMS
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Shipment print'],
                ['Shipment import'],
                ['Shipment import_template'],
                ['Shipment export'],
                ['Shipment walkin_create'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Archived Shipment access', 'Archived Shipment create', 'Archived Shipment update', 'Archived Shipment delete'],
                ['Quick Stats access', 'Quick Stats create', 'Quick Stats update', 'Quick Stats delete'],
                ['Alerts access', 'Alerts create', 'Alerts update', 'Alerts delete'],
                ['Daily Summary access', 'Daily Summary create', 'Daily Summary update', 'Daily Summary delete'],
                ['Setting access', 'Setting create', 'Setting update', 'Setting delete'],
                ['Driver Accounts access', 'Driver Accounts create', 'Driver Accounts update', 'Driver Accounts delete', 'Driver Accounts export'],
                ['Driver Runsheet access', 'Driver Runsheet create', 'Driver Runsheet update', 'Driver Runsheet delete'],
                ['Financial Request access', 'Financial Request create', 'Financial Request update', 'Financial Request delete'],
                ['Salary Bill Management access', 'Salary Bill Management create', 'Salary Bill Management update', 'Salary Bill Management delete'],
                ['Guest Driver access', 'Guest Driver create', 'Guest Driver update', 'Guest Driver delete'],
                ['Guest Driver Shipment access', 'Guest Driver Shipment create', 'Guest Driver Shipment update', 'Guest Driver Shipment delete'],
                ['Guest Customer Shipment access', 'Guest Customer Shipment create', 'Guest Customer Shipment update', 'Guest Customer Shipment delete'],
                ['Shipment Amount access', 'Shipment Amount create', 'Shipment Amount update', 'Shipment Amount delete'],           // from PermissionSeeder2.php
                ['Shipment Information access', 'Shipment Information create', 'Shipment Information update', 'Shipment Information delete'], // from PermissionSeeder2.php
                ['Shipment Item access', 'Shipment Item create', 'Shipment Item update', 'Shipment Item delete'],                 // from PermissionSeeder2.php
                ['Shipment Future access'],
                ['Notification access'],
                ['Realtime Query access'],
                ['Realtime Tracking access'],                                                                         // from PermissionSeeder2.php
                ['Driver Status access'],
                ['Assign Shipment access'],
                ['Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                // WMS
                ['Dashboard access', 'Dashboard create', 'Dashboard update', 'Dashboard delete'],
                ['Role access', 'Role create', 'Role update', 'Role delete', 'Role import', 'Role export'],
                ['Permission access', 'Permission create', 'Permission update', 'Permission delete', 'Permission export'],
                ['User access', 'User create', 'User update', 'User delete', 'User import', 'User export'],
                ['Shipper access', 'Shipper create', 'Shipper update', 'Shipper delete'],
                ['State access', 'State create', 'State update', 'State delete', 'State import', 'State export'],
                ['City access', 'City create', 'City update', 'City delete'],
                ['Zone access', 'Zone create', 'Zone update', 'Zone delete', 'Zone import', 'Zone export'],
                ['Rule access', 'Rule create', 'Rule update', 'Rule delete'],
                ['Governorate access', 'Governorate create', 'Governorate update', 'Governorate delete', 'Governorate export'],
                ['Place access', 'Place create', 'Place update', 'Place delete', 'Place import'],
                ['Station access', 'Station create', 'Station update', 'Station delete', 'Station export'],
                ['Hub access', 'Hub create', 'Hub update', 'Hub delete', 'Hub export'],
                ['Branch access', 'Branch create', 'Branch update', 'Branch delete'],
                ['Consignee access', 'Consignee create', 'Consignee update', 'Consignee delete'],
                ['Merchant access', 'Merchant create', 'Merchant update', 'Merchant delete','Merchant restore','Merchant archived'],
                ['Merchant Waybill access', 'Merchant Waybill create', 'Merchant Waybill update', 'Merchant Waybill delete'],
                ['Driver Waybill access', 'Driver Waybill create', 'Driver Waybill update', 'Driver Waybill delete'],
                ['Container access', 'Container create', 'Container update', 'Container delete', 'Container print', 'Container import', 'Container import_template', 'Container export'],
                ['Merchant Accounts access'],
                ['Shelves access', 'Shelves create', 'Shelves update', 'Shelves delete'],
                ['Shelf access', 'Shelf create', 'Shelf update', 'Shelf delete'],
                ['Shelf Category access', 'Shelf Category create', 'Shelf Category update', 'Shelf Category delete'],
                ['Unit access', 'Unit create', 'Unit update', 'Unit delete'],
                // HRM
                ['Employee access', 'Employee create', 'Employee update', 'Employee delete'],
                ['Employee Department access', 'Employee Department create', 'Employee Department update', 'Employee Department delete'],
                ['Employee Position access', 'Employee Position create', 'Employee Position update', 'Employee Position delete'],
                ['Work Time access', 'Work Time create', 'Work Time update', 'Work Time delete'],
                ['Employee Branch access', 'Employee Branch create', 'Employee Branch update', 'Employee Branch delete'],
                ['Employee Payroll access', 'Employee Payroll create', 'Employee Payroll update', 'Employee Payroll delete'],
                ['Hierarchy Level access', 'Hierarchy Level create', 'Hierarchy Level update', 'Hierarchy Level delete'],
                ['Employee Hierarchy access', 'Employee Hierarchy create', 'Employee Hierarchy update', 'Employee Hierarchy delete'],
                ['Leave Reason access', 'Leave Reason create', 'Leave Reason update', 'Leave Reason delete'],
                ['Leave Request access', 'Leave Request create', 'Leave Request update', 'Leave Request delete'],
                ['Leave Request Approval access', 'Leave Request Approval create', 'Leave Request Approval update', 'Leave Request Approval delete'],
                // CRM
                ['CRM Task access', 'CRM Task create', 'CRM Task update', 'CRM Task delete'],
                ['CRM Scenario access', 'CRM Scenario create', 'CRM Scenario update', 'CRM Scenario delete'],
                ['CRM Complaint access', 'CRM Complaint create', 'CRM Complaint update', 'CRM Complaint delete'],
                ['follow-ups access', 'follow-ups create', 'follow-ups update', 'follow-ups delete'],
                // RTO
                ['Manual RTO access', 'Manual RTO create', 'Manual RTO update', 'Manual RTO delete'],
                ['Auto RTO access', 'Auto RTO create', 'Auto RTO update', 'Auto RTO delete'],
                ['Outbound access'],
                ['history tracking access', 'history tracking create', 'history tracking update', 'history tracking delete'],
                ['Problems access', 'Problems create', 'Problems update', 'Problems delete'],
                // Driver App
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['Driver Status access', 'Driver Status create', 'Driver Status update', 'Driver Status delete', 'Driver Status export'],
                ['Login History access', 'Login History create', 'Login History update', 'Login History delete'],
                // Financial
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['salaries access', 'salaries create', 'salaries update', 'salaries delete'],
                ['Merchant Commission access', 'Merchant Commission create', 'Merchant Commission update', 'Merchant Commission delete'],
                ['Invoice access', 'Invoice create', 'Invoice update', 'Invoice delete'],
                ['Driver Bonus access', 'Driver Bonus create', 'Driver Bonus update', 'Driver Bonus delete'],
                // Partner Dashboard
                ['Partner Dashboard access', 'Partner Dashboard create', 'Partner Dashboard update', 'Partner Dashboard delete'],
                // Fleet & Driver Management
                ['Drivers access', 'Drivers create', 'Drivers update', 'Drivers delete', 'Drivers export', 'Drivers restore', 'Drivers archived'],
                ['Truck access', 'Truck create', 'Truck update', 'Truck delete', 'Truck print', 'Truck export'],
                ['Truck Driver access', 'Truck Driver create', 'Truck Driver update', 'Truck Driver delete', 'Truck Driver export'],
                ['Vehicles access', 'Vehicles create', 'Vehicles update', 'Vehicles delete'],
                ['Assignments access', 'Assignments create', 'Assignments update', 'Assignments delete'],
                ['Service Logs access', 'Service Logs create', 'Service Logs update', 'Service Logs delete'],
                ['licenses access', 'licenses create', 'licenses update', 'licenses delete'],
                // Other
                ['Sort access', 'Sort create', 'Sort update', 'Sort delete'],
                ['StockOutTask access', 'StockOutTask create', 'StockOutTask update', 'StockOutTask delete'],
                ['Transfers Task access', 'Transfers Task create', 'Transfers Task update', 'Transfers Task delete'],
                ['contracts access', 'contracts create', 'contracts update', 'contracts delete'],
                ['Whatsapp Template access', 'Whatsapp Template create', 'Whatsapp Template update', 'Whatsapp Template delete'],
                ['Operation access', 'Operation create', 'Operation update', 'Operation delete'],
                ['Company Accounts access', 'Company Accounts create', 'Company Accounts update', 'Company Accounts delete'],
                ['Country Channel access', 'Country Channel create', 'Country Channel update', 'Country Channel delete'],
                ['State Channel access', 'State Channel create', 'State Channel update', 'State Channel delete'],
                ['Governorate Channel access', 'Governorate Channel create', 'Governorate Channel update', 'Governorate Channel delete'],
                ['Fine access', 'Fine create', 'Fine update', 'Fine delete'],
                ['Abnormality access', 'Abnormality create', 'Abnormality update', 'Abnormality delete'],
                ['Inventory Items access', 'Inventory Items create', 'Inventory Items update', 'Inventory Items delete'],
                // Additional missing permissions
                ['Expenses access', 'Expenses create', 'Expenses update', 'Expenses delete'],
                ['Delivery Commission access', 'Delivery Commission create', 'Delivery Commission update', 'Delivery Commission delete'],
                ['Manifest Management access', 'Manifest Management create', 'Manifest Management update', 'Manifest Management delete'],
                ['Workspace Accounts access'],
                ['Transfers access'],
                ['Shipper Commission access', 'Shipper Commission create', 'Shipper Commission update', 'Shipper Commission delete'],
                ['Quality Check access', 'Quality Check create', 'Quality Check update', 'Quality Check delete', 'Quality Check send warning'],
                ['Session access', 'Session create', 'Session update', 'Session delete'],
                ['Activity Log access', 'Activity Log create', 'Activity Log update', 'Activity Log delete'],
                ['User Action access', 'User Action create', 'User Action update', 'User Action delete'],
                ['Compliance Checklist access', 'Compliance Checklist create', 'Compliance Checklist update', 'Compliance Checklist delete'],
                ['Legal Document access', 'Legal Document create', 'Legal Document update', 'Legal Document delete'],
                ['Ticket access', 'Ticket create', 'Ticket update', 'Ticket delete'],
                ['Live Chat access', 'Live Chat create', 'Live Chat update', 'Live Chat delete'],
                ['Merchant Ticket Chat access', 'Merchant Ticket Chat create', 'Merchant Ticket Chat update', 'Merchant Ticket Chat delete'],
                ['Chat access', 'Chat create', 'Chat update', 'Chat delete'],
                ['Merchant Address Book access', 'Merchant Address Book create', 'Merchant Address Book update', 'Merchant Address Book delete'],
                ['Merchant Branch access', 'Merchant Branch create', 'Merchant Branch update', 'Merchant Branch delete'],
                ['Account access', 'Account create', 'Account update', 'Account delete'],
                ['Contact History access', 'Contact History create', 'Contact History update', 'Contact History delete'],
                ['Pickup Task access', 'Pickup Task create', 'Pickup Task update', 'Pickup Task delete'],
                ['Assign Pickup Task access', 'Assign Pickup Task create'],
                // New modules from frontend nav
                ['Customer Created Shipments access', 'Customer Created Shipments print', 'Customer Created Shipments update'],
                ['Reprint access'],
                ['Driver Invoices'],
                ['Merchant Invoices'],
                ['Returned Shipments'],
                ['Address Updates access', 'Address Updates create', 'Address Updates update', 'Address Updates delete'],
                ['OFD List access', 'OFD List create', 'OFD List update', 'OFD List delete'],
                ['Activity Logs','User Actions','Sessions Management','Login History']
            ],
            'Country Manager' => [
                // All permissions have full CRUD (✔)
                // OMS
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Archived Shipment access', 'Archived Shipment create', 'Archived Shipment update', 'Archived Shipment delete'],
                ['Quick Stats access', 'Quick Stats create', 'Quick Stats update', 'Quick Stats delete'],
                ['Alerts access', 'Alerts create', 'Alerts update', 'Alerts delete'],
                ['Daily Summary access', 'Daily Summary create', 'Daily Summary update', 'Daily Summary delete'],
                ['Setting access', 'Setting create', 'Setting update', 'Setting delete'],
                ['Driver Accounts access', 'Driver Accounts create', 'Driver Accounts update', 'Driver Accounts delete', 'Driver Accounts export'],
                ['Driver Runsheet access', 'Driver Runsheet create', 'Driver Runsheet update', 'Driver Runsheet delete'],
                ['Financial Request access', 'Financial Request create', 'Financial Request update', 'Financial Request delete'],
                ['Salary Bill Management access', 'Salary Bill Management create', 'Salary Bill Management update', 'Salary Bill Management delete'],
                ['Guest Driver access', 'Guest Driver create', 'Guest Driver update', 'Guest Driver delete'],
                ['Guest Driver Shipment access', 'Guest Driver Shipment create', 'Guest Driver Shipment update', 'Guest Driver Shipment delete'],
                ['Guest Customer Shipment access', 'Guest Customer Shipment create', 'Guest Customer Shipment update', 'Guest Customer Shipment delete'],
                ['Shipment Amount access', 'Shipment Amount create', 'Shipment Amount update', 'Shipment Amount delete'],           // from PermissionSeeder2.php
                ['Shipment Information access', 'Shipment Information create', 'Shipment Information update', 'Shipment Information delete'], // from PermissionSeeder2.php
                ['Shipment Item access', 'Shipment Item create', 'Shipment Item update', 'Shipment Item delete'],                 // from PermissionSeeder2.php
                ['Shipment Future access'],
                ['Notification access'],
                ['Realtime Query access'],
                ['Realtime Tracking access'],                                                                         // from PermissionSeeder2.php
                ['Driver Status access'],
                ['Assign Shipment access'],
                ['Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                // WMS
                ['Dashboard access', 'Dashboard create', 'Dashboard update', 'Dashboard delete'],
                ['Role access', 'Role create', 'Role update', 'Role delete', 'Role import', 'Role export'],
                ['Permission access', 'Permission create', 'Permission update', 'Permission delete', 'Permission export'],
                ['User access', 'User create', 'User update', 'User delete', 'User import', 'User export'],
                ['Shipper access', 'Shipper create', 'Shipper update', 'Shipper delete'],
                ['State access', 'State create', 'State update', 'State delete', 'State import', 'State export'],
                ['City access', 'City create', 'City update', 'City delete'],
                ['Zone access', 'Zone create', 'Zone update', 'Zone delete', 'Zone import', 'Zone export'],
                ['Rule access', 'Rule create', 'Rule update', 'Rule delete'],
                ['Governorate access', 'Governorate create', 'Governorate update', 'Governorate delete', 'Governorate export'],
                ['Place access', 'Place create', 'Place update', 'Place delete', 'Place import'],
                ['Station access', 'Station create', 'Station update', 'Station delete', 'Station export'],
                ['Branch access', 'Branch create', 'Branch update', 'Branch delete'],
                ['Consignee access', 'Consignee create', 'Consignee update', 'Consignee delete'],
                ['Merchant access', 'Merchant create', 'Merchant update', 'Merchant delete','Merchant restore','Merchant archived'],
                ['Merchant Waybill access', 'Merchant Waybill create', 'Merchant Waybill update', 'Merchant Waybill delete'],
                ['Container access', 'Container create', 'Container update', 'Container delete', 'Container print', 'Container import', 'Container import_template', 'Container export'],
                ['Shelves access', 'Shelves create', 'Shelves update', 'Shelves delete'],
                ['Shelf access', 'Shelf create', 'Shelf update', 'Shelf delete'],
                ['Shelf Category access', 'Shelf Category create', 'Shelf Category update', 'Shelf Category delete'],
                ['Unit access', 'Unit create', 'Unit update', 'Unit delete'],
                // HRM
                ['Employee access', 'Employee create', 'Employee update', 'Employee delete'],
                ['Employee Department access', 'Employee Department create', 'Employee Department update', 'Employee Department delete'],
                ['Employee Position access', 'Employee Position create', 'Employee Position update', 'Employee Position delete'],
                ['Work Time access', 'Work Time create', 'Work Time update', 'Work Time delete'],
                ['Employee Branch access', 'Employee Branch create', 'Employee Branch update', 'Employee Branch delete'],
                ['Employee Payroll access', 'Employee Payroll create', 'Employee Payroll update', 'Employee Payroll delete'],
                ['Hierarchy Level access', 'Hierarchy Level create', 'Hierarchy Level update', 'Hierarchy Level delete'],
                ['Employee Hierarchy access', 'Employee Hierarchy create', 'Employee Hierarchy update', 'Employee Hierarchy delete'],
                ['Leave Reason access', 'Leave Reason create', 'Leave Reason update', 'Leave Reason delete'],
                ['Leave Request access', 'Leave Request create', 'Leave Request update', 'Leave Request delete'],
                ['Leave Request Approval access', 'Leave Request Approval create', 'Leave Request Approval update', 'Leave Request Approval delete'],
                // CRM
                ['CRM Task access', 'CRM Task create', 'CRM Task update', 'CRM Task delete'],
                ['CRM Scenario access', 'CRM Scenario create', 'CRM Scenario update', 'CRM Scenario delete'],
                ['CRM Complaint access', 'CRM Complaint create', 'CRM Complaint update', 'CRM Complaint delete'],
                ['follow-ups access', 'follow-ups create', 'follow-ups update', 'follow-ups delete'],
                // RTO
                ['Manual RTO access', 'Manual RTO create', 'Manual RTO update', 'Manual RTO delete'],
                ['Auto RTO access', 'Auto RTO create', 'Auto RTO update', 'Auto RTO delete'],
                ['Outbound access'],
                ['history tracking access', 'history tracking create', 'history tracking update', 'history tracking delete'],
                ['Problems access', 'Problems create', 'Problems update', 'Problems delete'],
                // Driver App
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['Driver Status access', 'Driver Status create', 'Driver Status update', 'Driver Status delete', 'Driver Status export'],
                ['Login History access', 'Login History create', 'Login History update', 'Login History delete'],
                // Financial
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['salaries access', 'salaries create', 'salaries update', 'salaries delete'],
                ['Merchant Commission access', 'Merchant Commission create', 'Merchant Commission update', 'Merchant Commission delete'],
                ['Invoice access', 'Invoice create', 'Invoice update', 'Invoice delete'],
                ['Driver Bonus access', 'Driver Bonus create', 'Driver Bonus update', 'Driver Bonus delete'],
                // Fleet & Driver Management
                ['Drivers access', 'Drivers create', 'Drivers update', 'Drivers delete', 'Drivers export', 'Drivers restore', 'Drivers archived'],
                ['Truck access', 'Truck create', 'Truck update', 'Truck delete', 'Truck print', 'Truck export'],
                ['Truck Driver access', 'Truck Driver create', 'Truck Driver update', 'Truck Driver delete', 'Truck Driver export'],
                ['Vehicles access', 'Vehicles create', 'Vehicles update', 'Vehicles delete'],
                ['Assignments access', 'Assignments create', 'Assignments update', 'Assignments delete'],
                ['Service Logs access', 'Service Logs create', 'Service Logs update', 'Service Logs delete'],
                ['licenses access', 'licenses create', 'licenses update', 'licenses delete'],
                // Other
                ['Sort access', 'Sort create', 'Sort update', 'Sort delete'],
                ['StockOutTask access', 'StockOutTask create', 'StockOutTask update', 'StockOutTask delete'],
                ['Transfers Task access', 'Transfers Task create', 'Transfers Task update', 'Transfers Task delete'],
                ['contracts access', 'contracts create', 'contracts update', 'contracts delete'],
                ['Whatsapp Template access', 'Whatsapp Template create', 'Whatsapp Template update', 'Whatsapp Template delete'],
                ['Operation access', 'Operation create', 'Operation update', 'Operation delete'],
                ['Company Accounts access', 'Company Accounts create', 'Company Accounts update', 'Company Accounts delete'],
                ['Country Channel access', 'Country Channel create', 'Country Channel update', 'Country Channel delete'],
                ['State Channel access', 'State Channel create', 'State Channel update', 'State Channel delete'],
                ['Governorate Channel access', 'Governorate Channel create', 'Governorate Channel update', 'Governorate Channel delete'],
                ['Fine access', 'Fine create', 'Fine update', 'Fine delete'],
                ['Abnormality access', 'Abnormality create', 'Abnormality update', 'Abnormality delete'],
                ['Inventory Items access', 'Inventory Items create', 'Inventory Items update', 'Inventory Items delete'],
            ],
            'Warehouse Supervisor' => [
                // Shipments related (ALL)
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Archived Shipment access', 'Archived Shipment create', 'Archived Shipment update', 'Archived Shipment delete'],
                ['Quick Stats access', 'Quick Stats create', 'Quick Stats update', 'Quick Stats delete'],
                ['Alerts access', 'Alerts create', 'Alerts update', 'Alerts delete'],
                ['Daily Summary access', 'Daily Summary create', 'Daily Summary update', 'Daily Summary delete'],
                ['Setting access', 'Setting create', 'Setting update', 'Setting delete'],
                ['Driver Accounts access', 'Driver Accounts create', 'Driver Accounts update', 'Driver Accounts delete', 'Driver Accounts export'],
                ['Driver Runsheet access', 'Driver Runsheet create', 'Driver Runsheet update', 'Driver Runsheet delete'],
                ['Financial Request access', 'Financial Request create', 'Financial Request update', 'Financial Request delete'],
                ['Salary Bill Management access', 'Salary Bill Management create', 'Salary Bill Management update', 'Salary Bill Management delete'],
                ['Guest Driver access', 'Guest Driver create', 'Guest Driver update', 'Guest Driver delete'],
                ['Guest Driver Shipment access', 'Guest Driver Shipment create', 'Guest Driver Shipment update', 'Guest Driver Shipment delete'],
                ['Guest Customer Shipment access', 'Guest Customer Shipment create', 'Guest Customer Shipment update', 'Guest Customer Shipment delete'],
                ['Shipment Amount access', 'Shipment Amount create', 'Shipment Amount update', 'Shipment Amount delete'],
                ['Shipment Information access', 'Shipment Information create', 'Shipment Information update', 'Shipment Information delete'],
                ['Shipment Item access', 'Shipment Item create', 'Shipment Item update', 'Shipment Item delete'],
                ['Assign Shipment access', 'Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Container access', 'Container create', 'Container update', 'Container delete', 'Container print', 'Container import', 'Container import_template', 'Container export'],
                ['Realtime Query access'],
                ['Shipment Future access'],
                ['Notification access'],

                // Pickup tasks (drivers / truck drivers)
                ['Pickup Task access', 'Pickup Task create', 'Pickup Task update', 'Pickup Task delete'],
                ['Assign Pickup Task access', 'Assign Pickup Task create'],

                // Logistics
                ['Transfers Task access', 'Transfers Task create', 'Transfers Task update', 'Transfers Task delete'],
                ['Outbound access'],
                ['history tracking access', 'history tracking create', 'history tracking update', 'history tracking delete'],

                // Finance (without confirmation)
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['salaries access', 'salaries create', 'salaries update', 'salaries delete'],
                ['Merchant Commission access', 'Merchant Commission create', 'Merchant Commission update', 'Merchant Commission delete'],
                ['Invoice access', 'Invoice create', 'Invoice update', 'Invoice delete'],

                // User Management
                ['User access', 'User create', 'User update', 'User delete', 'User import', 'User export'],
                ['Role access', 'Role create', 'Role update', 'Role delete', 'Role import', 'Role export'],
                ['Permission access', 'Permission create', 'Permission update', 'Permission delete', 'Permission export'],

                // Penalties
                ['Fine access', 'Fine create', 'Fine update', 'Fine delete'],

                ['Driver Invoices'],
                ['Merchant Invoices'],
            ],
            'Warehouse Cashier' => [
                // ====== OMS (Shipment Management System) ======
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Shipment Amount access', 'Shipment Amount create', 'Shipment Amount update', 'Shipment Amount delete'],
                ['Shipment Information access', 'Shipment Information create', 'Shipment Information update', 'Shipment Information delete'],
                ['Shipment Item access', 'Shipment Item create', 'Shipment Item update', 'Shipment Item delete'],
                ['Assign Shipment access', 'Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Realtime Query access'],
                ['Daily delivery tasks access', 'Daily delivery tasks update'],
                ['Scanning access', 'Scanning update'],
                ['Shipment Future access'],
                ['Notification access'],

                // ====== Finance ======
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['salaries access', 'salaries create', 'salaries update', 'salaries delete'],
                ['Merchant Commission access', 'Merchant Commission create', 'Merchant Commission update', 'Merchant Commission delete'],
                ['Invoice access', 'Invoice create', 'Invoice update', 'Invoice delete'],
                ['Account access', 'Account create', 'Account update', 'Account delete'],

                // ====== Penalties / Fines ======
                ['Fine access', 'Fine create', 'Fine update', 'Fine delete'],
                ['Driver Invoices'],
                ['Merchant Invoices'],
            ],
            'Customer Service' => [
                ['history tracking access'],

                ['CRM Task access', 'CRM Task create', 'CRM Task update', 'CRM Task delete'],
                ['CRM Scenario access', 'CRM Scenario create', 'CRM Scenario update', 'CRM Scenario delete'],
                ['CRM Complaint access', 'CRM Complaint create', 'CRM Complaint update', 'CRM Complaint delete'],
                ['follow-ups access', 'follow-ups create', 'follow-ups update', 'follow-ups delete'],

                ['Ticket access', 'Ticket create', 'Ticket update', 'Ticket delete'],
                ['Live Chat access', 'Live Chat create', 'Live Chat update', 'Live Chat delete'],
                ['Chat access', 'Chat create', 'Chat update', 'Chat delete'],
                ['Merchant Ticket Chat access', 'Merchant Ticket Chat create', 'Merchant Ticket Chat update', 'Merchant Ticket Chat delete'],

                ['Contact History access', 'Contact History create', 'Contact History update', 'Contact History delete'],

                ['CRM access'],

            ],
            'Driver Admin' => [
                // OMS
                ['Dashboard access', 'Dashboard create', 'Dashboard update', 'Dashboard delete'],
                ['Analytics access', 'Analytics create', 'Analytics update', 'Analytics delete'],
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Archived Shipment access', 'Archived Shipment create', 'Archived Shipment update', 'Archived Shipment delete'],
                ['Quick Stats access', 'Quick Stats create', 'Quick Stats update', 'Quick Stats delete'],
                ['Alerts access', 'Alerts create', 'Alerts update', 'Alerts delete'],
                ['Daily Summary access', 'Daily Summary create', 'Daily Summary update', 'Daily Summary delete'],
                ['Setting access', 'Setting create', 'Setting update', 'Setting delete'],
                ['Driver Accounts access', 'Driver Accounts create', 'Driver Accounts update', 'Driver Accounts delete', 'Driver Accounts export'],
                ['Driver Runsheet access', 'Driver Runsheet create', 'Driver Runsheet update', 'Driver Runsheet delete'],
                ['Financial Request access', 'Financial Request create', 'Financial Request update', 'Financial Request delete'],
                ['Salary Bill Management access', 'Salary Bill Management create', 'Salary Bill Management update', 'Salary Bill Management delete'],
                ['Guest Driver access', 'Guest Driver create', 'Guest Driver update', 'Guest Driver delete'],
                ['Guest Driver Shipment access', 'Guest Driver Shipment create', 'Guest Driver Shipment update', 'Guest Driver Shipment delete'],
                ['Guest Customer Shipment access', 'Guest Customer Shipment create', 'Guest Customer Shipment update', 'Guest Customer Shipment delete'],
                ['Shipment Future access'],
                ['Notification access'],
                ['Realtime Query access'],
                ['Assign Shipment access'],
                ['Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception update'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Daily delivery tasks access', 'Daily delivery tasks update'],
                ['Scanning access', 'Scanning update'],
                // WMS
                ['Hub access', 'Hub create', 'Hub update', 'Hub delete', 'Hub export'],
                ['Station access', 'Station create', 'Station update', 'Station delete', 'Station export'],
                ['Branch access', 'Branch create', 'Branch update', 'Branch delete'],
                // Fleet & Driver Management
                ['Drivers access', 'Drivers create', 'Drivers update', 'Drivers delete', 'Drivers export', 'Drivers restore', 'Drivers archived'],
                ['Truck access', 'Truck create', 'Truck update', 'Truck delete', 'Truck print', 'Truck export'],
                ['Truck Driver access', 'Truck Driver create', 'Truck Driver update', 'Truck Driver delete', 'Truck Driver export'],
                ['Vehicles access', 'Vehicles create', 'Vehicles update', 'Vehicles delete'],
                ['Assignments access', 'Assignments create', 'Assignments update', 'Assignments delete'],
                ['Service Logs access', 'Service Logs create', 'Service Logs update', 'Service Logs delete'],
                ['licenses access', 'licenses create', 'licenses update', 'licenses delete'],
                // Other
                ['contracts access', 'contracts create', 'contracts update', 'contracts delete'],
            ],
            'Sorter' => [
                // OMS
                ['Shipment access', 'Shipment update'],
                ['Realtime Query access'],
                ['Assign Shipment access'],
                ['Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Daily delivery tasks access', 'Daily delivery tasks update'],
                ['Scanning access', 'Scanning update'],
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['Outbound access'],
                ['Sort access', 'Sort create', 'Sort update', 'Sort delete'],
                ['StockOutTask access', 'StockOutTask create', 'StockOutTask update', 'StockOutTask delete'],

            ],
            'Driver' => [
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Driver Status access', 'Driver Accounts access', 'Driver Status create', 'Driver Status update', 'Driver Status delete'],
            ],
            'Vendor Driver' => [
                ['Shipment access', 'Shipment update'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Realtime Query access'],
                ['Assign Shipment access'],
                ['Assign Shipment To Shelf access'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Daily delivery tasks access', 'Daily delivery tasks create', 'Daily delivery tasks update', 'Daily delivery tasks delete'],
                ['Scanning access', 'Scanning create', 'Scanning update', 'Scanning delete'],
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['Driver Status access', 'Driver Status create', 'Driver Status update', 'Driver Status delete'],
                // ['Drivers access', 'Drivers create', 'Drivers update', 'Drivers delete'],
            ],

            'Financial Accountant' => [
                ['COD Collection access', 'COD Collection create', 'COD Collection update', 'COD Collection delete'],
                ['Pickup Collection access', 'Pickup Collection create', 'Pickup Collection update', 'Pickup Collection delete'],
                ['salaries access', 'salaries create', 'salaries update', 'salaries delete'],
                ['Delivery Exception access', 'Delivery Exception create', 'Delivery Exception update', 'Delivery Exception delete'],
                ['Station access', 'Station update'],
                ['Hub access', 'Hub update'],
                ['Branch access', 'Branch update'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Shipment Status access', 'Shipment Status create', 'Shipment Status update', 'Shipment Status delete'],
                ['Employee Payroll access', 'Employee Payroll create', 'Employee Payroll update', 'Employee Payroll delete'],
                ['Merchant access', 'Merchant create', 'Merchant update', 'Merchant delete','Merchant restore','Merchant archived'],
                ['Merchant Waybill access', 'Merchant Waybill create', 'Merchant Waybill update', 'Merchant Waybill delete'],
                ['Account access', 'Account create', 'Account update', 'Account delete'],
                ['Merchant Commission access', 'Merchant Commission create', 'Merchant Commission update', 'Merchant Commission delete'],
                ['Invoice access', 'Invoice create', 'Invoice update', 'Invoice delete'],
                ['Driver Bonus access', 'Driver Bonus create', 'Driver Bonus update', 'Driver Bonus delete'],
                ['Manual RTO access', 'Manual RTO create', 'Manual RTO update', 'Manual RTO delete'],
                ['Auto RTO access', 'Auto RTO create', 'Auto RTO update', 'Auto RTO delete'],
                ['Outbound access'],
                ['history tracking access', 'history tracking create', 'history tracking update', 'history tracking delete'],
                ['licenses access', 'licenses create', 'licenses update', 'licenses delete'],
                ['contracts access', 'contracts create', 'contracts update', 'contracts delete'],
                ['Dashboard access', 'Dashboard create', 'Dashboard update', 'Dashboard delete'],
                ['Problems access', 'Problems create', 'Problems update', 'Problems delete'],
                ['Driver Invoices'],
                ['Merchant Invoices'],
            ],

            'Merchant' => [
                ['Dashboard access'],
                ['Shipment access', 'Shipment create', 'Shipment update', 'Shipment delete'],
                ['Outsourced Shipment access', 'Outsourced Shipment create', 'Outsourced Shipment update', 'Outsourced Shipment delete'],
                ['Pickup Unassigned Shipment access', 'Pickup Unassigned Shipment create', 'Pickup Unassigned Shipment update', 'Pickup Unassigned Shipment delete'],
                ['Merchant Waybill access', 'Merchant Waybill create', 'Merchant Waybill update', 'Merchant Waybill delete'],
                ['Merchant Dashboard access', 'Merchant Dashboard create', 'Merchant Dashboard update', 'Merchant Dashboard delete'],
                ['Merchant Summary access', 'Merchant Summary create', 'Merchant Summary update', 'Merchant Summary delete'],
                ['Merchant Notification access', 'Merchant Notification create', 'Merchant Notification update', 'Merchant Notification delete'],
                ['Merchant Support access', 'Merchant Support create', 'Merchant Support update', 'Merchant Support delete'],
                ['Merchant Wallet access', 'Merchant Wallet create', 'Merchant Wallet update', 'Merchant Wallet delete'],
                ['Merchant Address Book access', 'Merchant Address Book create', 'Merchant Address Book update', 'Merchant Address Book delete'],

            ],

            'Partner Dashboard' => [
                ['Partner Dashboard access', 'Partner Dashboard create', 'Partner Dashboard update', 'Partner Dashboard delete'],
            ],
        ];
    }
}
