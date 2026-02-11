<?php

use App\Http\Controllers\Api\v1\PartnerManagementController;
use App\Http\Controllers\Api\v1\ContainerController;
use App\Models\Country;
use App\Http\Resources\AuthResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\v1\HubController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ChatController;
use App\Http\Controllers\Api\v1\CityController;
use App\Http\Controllers\Api\v1\RoleController;
use App\Http\Controllers\Api\v1\RuleController;
use App\Http\Controllers\Api\v1\TestController;
use App\Http\Controllers\Api\v1\UnitController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\ZoneController;
use App\Http\Controllers\Api\v1\PlaceController;
use App\Http\Controllers\Api\v1\RouteController;
use App\Http\Controllers\Api\v1\ShelfController;
use App\Http\Controllers\Api\v1\StateController;
use App\Http\Controllers\Api\v1\TruckController;
use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\DriverController;
use App\Http\Controllers\Api\v1\DriverReturnPickupController;
use App\Http\Controllers\Api\v1\ReturnController;
use App\Http\Controllers\Api\v1\ReturnRequestController;
use App\Http\Controllers\Api\v1\TicketController;
use App\Http\Controllers\Api\v1\WalletController;
use App\Http\Controllers\Api\v1\AddressController;
use App\Http\Controllers\Api\v1\CompanyController;
use App\Http\Controllers\Api\v1\CrmTaskController;
use App\Http\Controllers\Api\v1\ExpenseController;
use App\Http\Controllers\Api\v1\InvoiceController;
use App\Http\Controllers\Api\v1\OFDListController;
use App\Http\Controllers\Api\v1\PartnerController;
use App\Http\Controllers\Api\v1\ProfileController;
use App\Http\Controllers\Api\v1\SessionController;
use App\Http\Controllers\Api\v1\SettingController;
use App\Http\Controllers\Api\v1\ShipperController;
use App\Http\Controllers\Api\v1\StationController;
use App\Http\Controllers\Api\v1\AccountsController;
use App\Http\Controllers\Api\v1\EmployeeController;
use App\Http\Controllers\Api\v1\ManifestController;
use App\Http\Controllers\Api\v1\MerchantController;
use App\Http\Controllers\Api\v1\MerchantInvoicesController;
use App\Http\Controllers\Api\v1\DriverInvoicesController;
use App\Http\Controllers\Api\v1\ScenarioController;
use App\Http\Controllers\Api\v1\ShipmentController;
use App\Http\Controllers\Api\v1\TrackingController;
use App\Http\Controllers\Api\v1\ConsigneeController;
use App\Http\Controllers\Api\v1\DashboardController;
use App\Http\Controllers\Api\v1\WorkspaceController;
use App\Http\Controllers\Api\v1\AppVersionController;
use App\Http\Controllers\Api\v1\DriverStopController;
use App\Http\Controllers\Api\v1\PermissionController;
use App\Http\Controllers\Api\v1\UserActionController;
use App\Http\Controllers\Api\v1\AbnormalityController;
use App\Http\Controllers\Api\v1\ActivityLogController;
use App\Http\Controllers\Api\v1\CustomAlertController;
use App\Http\Controllers\Api\v1\DriverBonusController;
use App\Http\Controllers\Api\v1\GovernorateController;
use App\Http\Controllers\Api\v1\GuestDriverController;
use App\Http\Controllers\Api\v1\LeaveReasonController;
use App\Http\Controllers\Api\v1\TransferFeeController;
use App\Http\Controllers\Api\v1\TruckDriverController;
use App\Http\Controllers\Api\v1\TruckStatusController;
use App\Http\Controllers\LApi\v1\eaveReasonController;
use App\Http\Controllers\Api\v1\DeliverySlotController;
use App\Http\Controllers\Api\v1\DriverStatusController;
use App\Http\Controllers\Api\v1\LeaveRequestController;
use App\Http\Controllers\Api\v1\LoginHistoryController;
use App\Http\Controllers\Api\v1\QualityCheckController;
use App\Http\Controllers\Api\v1\ShipmentFeesController;
use App\Http\Controllers\Api\v1\ShipmentFineController;
use App\Http\Controllers\Api\v1\ShipmentItemController;
use App\Http\Controllers\Api\v1\ShipmentRuleController;
use App\Http\Controllers\Api\v1\StateChannelController;
use App\Http\Controllers\Api\v1\StockOutTaskController;
use App\Http\Controllers\Api\v1\TransferTaskController;
use App\Http\Controllers\Api\v1\ScheduledMessageController;
use App\Http\Controllers\Api\v1\AutomatedTaskController;
use App\Http\Controllers\Api\v1\CODCollectionController;
use App\Http\Controllers\Api\v1\PickupDepositController;
use App\Http\Controllers\Api\v1\DriverAccountController;
use App\Http\Controllers\Api\v1\DriverInvoiceController;
use App\Http\Controllers\Api\v1\EmailTemplateController;
use App\Http\Controllers\Api\v1\InventoryItemController;
use App\Http\Controllers\Api\v1\LegalDocumentController;
use App\Http\Controllers\Api\v1\LowStockAlertController;
use App\Http\Controllers\Api\v1\PickupRequestController;
use App\Http\Controllers\Api\v1\ShelfCategoryController;
use App\Http\Controllers\Api\v1\CompanyAccountController;
use App\Http\Controllers\Api\v1\ContactHistoryController;
use App\Http\Controllers\Api\v1\CountryChannelController;
use App\Http\Controllers\Api\v1\DriverLocationController;
use App\Http\Controllers\Api\v1\DriverRunsheetController;
use App\Http\Controllers\Api\v1\OutsourcedDriverSettlementController;
use App\Http\Controllers\Api\v1\DriverStopListController;
use App\Http\Controllers\Api\v1\EmployeeBranchController;
use App\Http\Controllers\Api\v1\HierarchyLevelController;
use App\Http\Controllers\Api\v1\MerchantTicketController;
use App\Http\Controllers\Api\v1\OnTimeDeliveryController;
use App\Http\Controllers\Api\v1\SafetyIncidentController;
use App\Http\Controllers\Api\v1\ShipmentAmountController;
use App\Http\Controllers\Api\v1\ShipmentStatusController;
use App\Http\Controllers\Api\v1\WaybillRequestController;
use App\Http\Controllers\Api\v1\EmployeePayrollController;
use App\Http\Controllers\Api\v1\FinancialReportController;
use App\Http\Controllers\Api\v1\InstantDeliveryController;
use App\Http\Controllers\Api\v1\MerchantAccountController;
use App\Http\Controllers\Api\v1\MerchantInvoiceController;
use App\Http\Controllers\Api\v1\MerchantPaymentController;
use App\Http\Controllers\Api\v1\MerchantSettingController;
use App\Http\Controllers\Api\v1\MerchantWaybillController;
use App\Http\Controllers\Api\v1\PartnerShipmentController;
use App\Http\Controllers\Api\v1\ScheduledActionController;
use App\Http\Controllers\Api\v1\ShipmentArchiveController;
use App\Http\Controllers\Api\v1\CustomerShipmentController;
use App\Http\Controllers\Api\v1\DeliveryReminderController;
use App\Http\Controllers\Api\v1\DriverAppSettingController;
use App\Http\Controllers\Api\v1\DriverCommissionController;
use App\Http\Controllers\Api\v1\EmployeePositionController;
use App\Http\Controllers\MobilePickupController;
use App\Http\Controllers\Api\v1\EmployeeWorkTimeController;
use App\Http\Controllers\Api\v1\FinancialRequestController;
use App\Http\Controllers\Api\v1\StockTransactionController;
use App\Http\Controllers\Api\v1\WarehouseAccountController;
use App\Http\Controllers\Api\v1\WhatsappTemplateController;
use App\Http\Controllers\Api\v1\DeliveryExceptionController;
use App\Http\Controllers\Api\v1\DriverPerformanceController;
use App\Http\Controllers\Api\v1\EmployeeHierarchyController;
use App\Http\Controllers\Api\v1\ScheduledDeliveryController;
use App\Http\Controllers\Api\v1\ShipperCommissionController;
use App\Http\Controllers\Api\v1\CustomsDeclarationController;
use App\Http\Controllers\Api\v1\EmployeeDepartmentController;
use App\Http\Controllers\Api\v1\GovernorateChannelController;
use App\Http\Controllers\Api\v1\MerchantCommissionController;
use App\Http\Controllers\Api\v1\MerchantPickupTaskController;
use App\Http\Controllers\Api\v1\ComplianceChecklistController;
use App\Http\Controllers\Api\v1\GuestDriverShipmentController;
use App\Http\Controllers\Api\v1\InterBranchTransferController;
use App\Http\Controllers\Api\v1\MaintenanceScheduleController;
use App\Http\Controllers\Api\v1\ShipmentFulfillmentController;
use App\Http\Controllers\Api\v1\ShipmentInformationController;
use App\Http\Controllers\Api\v1\FuelEfficiencyReportController;
use App\Http\Controllers\Api\v1\LeaveRequestApprovalController;
use App\Http\Controllers\Api\v1\AssignShipmentToShelfController;
use App\Http\Controllers\Api\v1\Driver\DriverShipmentController;
use App\Http\Controllers\Api\v1\DriverLocationHistoryController;
use App\Http\Controllers\Api\v1\DriverWaybillController;
use App\Http\Controllers\Api\v1\GovernorateStatePlaceController;
use App\Http\Controllers\Api\v1\ShipmentFeeAllocationController;
use App\Http\Controllers\Api\v1\MerchantPickupShipmentController;
use App\Http\Controllers\Api\v1\FinanceDashboardController;
use App\Http\Controllers\Api\v1\FinanceTopPerformersController;
use App\Http\Controllers\Api\v1\FinanceReportsController;
use App\Http\Controllers\Api\v1\ReversePickupRequestController;
use App\Http\Controllers\Api\v1\ReversePickupTaskController;
use App\Http\Controllers\Api\v1\DriverReversePickupController;
use App\Http\Controllers\Api\v1\ReverseShipmentController;
use App\Http\Controllers\Api\v1\ReturnQueueController;
use App\Http\Controllers\Api\v1\MapsUnshortenController;
use App\Enums\ShipmentStatusEnum;
use App\Enums\DeliveryExceptionEnum;


Route::prefix('v1')
    ->group(function () {

        Route::get('/app-version', [AppVersionController::class, 'show']);


        Route::get('/user', function () {
            return new AuthResource(Auth::user());
        })->middleware('auth:sanctum');

        Route::post('customer-shipments/store', [CustomerShipmentController::class, 'store'])->middleware('throttle:10,1');

        Route::get('mobile_user', function () {
            try {
                $user = Auth::user();
                return sendResponse("User retrived Successfully.", new AuthResource($user), true, [], 200);
            } catch (Exception $e) {
                return sendResponse("", [], false, $e->getMessage(), 500);
            }
        })->middleware('auth:sanctum');

        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

        // Broadcasting authentication routes for Laravel Reverb
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        Route::post('test', [TestController::class, 'test'])->middleware('auth:sanctum');

        Route::get('test_s', function () {
            $adminLevel = 4;  // governorates in Oman
            $countryIso = 'OM';
            $query = "
                [out:json];
                relation
                    [\"boundary\"=\"administrative\"]
                    [\"admin_level\"=\"$adminLevel\"]
                    [\"ISO3166-2\"~\"$countryIso.*\"];
                out body;
                >;
                out skel qt;
                ";
            $response = Http::post('https://overpass-api.de/api/interpreter', ['data' => $query]);
            info($response);
            $elements = $response->json()['elements'];
            info($elements);
        });

        // Route::get('test_g', function () {
        //     $governorates = Governorate::with('country')->get();
        //     $googleKey = env('GOOGLE_API_KEY');
    
        //     foreach ($governorates as $g) {
    
        //         $address = "{$g->en_name}, {$g->country->name}";
    
        //         $response = Http::withOptions(['verify' => 'D:\Softwares\cacert.pem'])->get('https://maps.googleapis.com/maps/api/geocode/json', [
        //             'address' => $address,
        //             'key'     => $googleKey,
        //         ]);
    
        //         $data = $response->json();
        //         if ($data['status'] === 'OK' && !empty($data['results'])) {
        //             $location = $data['results'][0]['geometry']['location'];
        //             Log::info($data['results'][0]['geometry']);
        //             $g->lat  = $location['lat'];
        //             $g->lng = $location['lng'];
        //             $g->save();
        //         } else {
        //             Log::error("Error");
        //         }
    
        //         Log::info("Done");
        //     }
        // });
    
        Route::get('countries', function () {
            return sendResponse("Countries", Country::select("id", "name")->get());
        });

        Route::get('facilities', function () {
            return sendResponse("FacilityTypes", facilities());
        });

        Route::get('facilities', function () {
            return sendResponse("FacilityTypes", facilities());
        });



        Route::middleware(['auth:sanctum', 'workspace.valid'])->group(function () {

            Route::get('overview', [DashboardController::class, 'index']);
            Route::get('quick-stats', [DashboardController::class, 'quickStats']);
            Route::get('daily-summary', [DashboardController::class, 'dailySummary']);

            Route::prefix('settlements/outsourced')->controller(OutsourcedDriverSettlementController::class)->group(function () {
                Route::post('otp/send', 'sendOtp');
                Route::post('otp/verify', 'verifyOtp');
                Route::post('/', 'store');
                Route::post('/driver-payroll/import-preview', 'import_preview');
                Route::post('/driver-payroll/import', 'import');
            });

            Route::post('/pickups', [PickupRequestController::class, 'create_pickup_request_by_merchant'])->middleware('role:Merchant,Super Admin');

            Route::prefix('/notifications')->controller(\App\Http\Controllers\Api\v1\NotificationCounterController::class)->group(function () {
                Route::get('counters', 'index');
                // Route::post('mark-seen', 'markSeen');
            });
            Route::post('/waybill-request', [WaybillRequestController::class, 'store']);
            Route::prefix('/permissions')->controller(PermissionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Permission access");
                Route::post('store', 'store')->middleware("authorize:Permission create");
                Route::post('update', 'update')->middleware("authorize:Permission update");
                Route::post('delete', 'delete')->middleware("authorize:Permission delete");
                Route::post('export', 'export')->middleware("authorize:Permission access");
                Route::get('all', 'all')->middleware("authorize:Permission access");
            });

            Route::prefix('/roles')->controller(RoleController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Role access");
                Route::post('store', 'store')->middleware("authorize:Role create");
                Route::get('show/{id}', 'show')->middleware("authorize:Role update");
                Route::get('edit/{id}', 'show')->middleware("authorize:Role update");
                Route::post('update', 'update')->middleware("authorize:Role update");
                Route::post('delete', 'delete')->middleware("authorize:Role delete");
                Route::post('export', 'export')->middleware("authorize:Role access");
                Route::post('import', 'import')->middleware("authorize:Role import");
                Route::get('export', 'export')->middleware("authorize:Role export");
                Route::get('all', 'all');
            });

            Route::prefix('/shippers')->controller(ShipperController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipper access");
                Route::post('store', 'store')->middleware("authorize:Shipper create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Shipper update");
                Route::post('update', 'update')->middleware("authorize:Shipper update");
                Route::post('delete', 'delete')->middleware("authorize:Shipper delete");
                Route::get('all', 'all');
                Route::get('getSingle', 'getSingle');
                Route::post('update-default-commission', 'updateDefaultShipperCommission');
                Route::get('default-commission', 'getDefaultShipperCommission');
            });


            Route::prefix('/governorates')->controller(GovernorateController::class)->group(function () {
                Route::get('index', 'index')->middleware("authorize:Governorate access");
                Route::post('store', 'store')->middleware("authorize:Governorate create");
                Route::get('show/{id}', 'show')->middleware("authorize:Governorate update");
                Route::get('edit/{id}', 'edit')->middleware("authorize:Governorate update");
                Route::post('update', 'update')->middleware("authorize:Governorate update");
                Route::post('delete', 'delete')->middleware("authorize:Governorate delete");
                Route::post('export', 'export')->middleware("authorize:Governorate access");
                Route::get('all', 'all');
            });

            Route::prefix('/states')->controller(StateController::class)->group(function () {
                Route::get('index', 'index')->middleware("authorize:State access");
                Route::post('store', 'store')->middleware("authorize:State create");
                Route::get('show/{id}', 'show')->middleware("authorize:State update");
                Route::get('edit/{id}', 'edit')->middleware("authorize:State update");
                Route::post('update', 'update')->middleware("authorize:State update");
                Route::post('delete', 'delete')->middleware("authorize:State delete");
                Route::post('export', 'export')->middleware("authorize:State access");
                Route::post('import', 'import')->middleware("authorize:State create");
                Route::get('template', 'template')->middleware("authorize:State access");
                Route::get('all', 'all');
            });

            Route::prefix('/places')->controller(PlaceController::class)->group(function () {
                Route::get('index', 'index')->middleware("authorize:Place access");
                Route::post('store', 'store')->middleware("authorize:Place create");
                Route::post('update', 'update')->middleware("authorize:Place update");
                Route::post('delete', 'delete')->middleware("authorize:Place delete");
                Route::get('all', 'all');
            });

            Route::post('import-governorate-state-place', [GovernorateStatePlaceController::class, 'import'])->name('import.governorate.state.place');


            Route::prefix('/cities')->controller(CityController::class)->group(function () {
                Route::get('index', 'index')->middleware("authorize:City access");
                Route::post('store', 'store')->middleware("authorize:City create");
                Route::post('update', 'update')->middleware("authorize:City update");
                Route::post('delete', 'delete')->middleware("authorize:City delete");
                Route::get('all', 'all');
            });

            Route::prefix('/zones')->controller(ZoneController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Zone access");

                Route::post('store', 'store')->middleware("authorize:Zone create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Zone update");
                Route::post('update', 'update')->middleware("authorize:Zone update");
                Route::post('delete', 'delete')->middleware("authorize:Zone delete");
                Route::post('import', 'import')->middleware("authorize:Zone import");
                Route::get('export', 'export')->middleware("authorize:Zone export");
                Route::post('shipments/{id}', 'shipments')->middleware("authorize:Zone access");
                Route::get('all', 'all');
                Route::get('owners', 'owners');
            });

            Route::prefix('/rules')->controller(RuleController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Rule access");
                Route::post('store', 'store')->middleware("authorize:Rule create");
                Route::get('show/{rule}', 'show')->middleware("authorize:Rule access");
                Route::post('update/{rule}', 'update')->middleware("authorize:Rule update");
                Route::post('delete/{rule}', 'destroy')->middleware("authorize:Rule delete");
                Route::post('toggle-status/{rule}', 'toggleStatus')->middleware("authorize:Rule update");
            });

            Route::prefix('/consignees')->controller(ConsigneeController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Consignee access");
                Route::post('store', 'store')->middleware("authorize:Consignee create");
                Route::post('update', 'update')->middleware("authorize:Consignee update");
                Route::post('delete', 'delete')->middleware("authorize:Consignee delete");
                Route::get('all', 'all');
                Route::get('/{consignee}/addresses', 'addresses');
            });
            Route::post('/addresses/{address}/approve', [AddressController::class, 'approveBySupervisor'])->middleware('auth:sanctum');

            Route::prefix('/units')->controller(UnitController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Unit access");
                Route::get('getSingle', 'getSingle')->middleware("authorize:Unit access");
                Route::post('store', 'store')->middleware("authorize:Unit create");
                Route::post('update', 'update')->middleware("authorize:Unit update");
                Route::post('delete', 'delete')->middleware("authorize:Unit delete");
                Route::post('export', 'export')->middleware("authorize:Unit access");
                Route::get('all', 'all');
            });


            Route::prefix('/hubs')->controller(HubController::class)->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store')->middleware("authorize:Hub create");
                Route::get('show/{id}', 'show')->middleware("authorize:Hub update");
                Route::post('update', 'update')->middleware("authorize:Hub update");
                Route::post('delete', 'delete')->middleware("authorize:Hub delete");
                Route::post('export', 'export')->middleware("authorize:Hub access");
                Route::get('all', 'all');
                Route::get('getStationsByHub', 'getStationsByHub');
            });

            Route::prefix('/stations')->controller(StationController::class)->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store')->middleware("authorize:Station create");
                Route::get('show/{id}', 'show')->middleware("authorize:Station update");
                Route::get('edit/{id}', 'show')->middleware("authorize:Station update");
                Route::post('update', 'update')->middleware("authorize:Station update");
                Route::post('delete', 'delete')->middleware("authorize:Station delete");
                Route::post('export', 'export')->middleware("authorize:Station access");
                Route::get('all', 'all');
            });


            Route::prefix('/branches')->controller(BranchController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Branch access");
                Route::get('all', 'all');
                Route::get('{id}', 'show')->middleware("authorize:Branch create");
                Route::post('store', 'store')->middleware("authorize:Branch create");
                Route::post('update', 'update')->middleware("authorize:Branch update");
                Route::get('show/{id}', 'show')->middleware("authorize:Branch update");
                Route::post('delete', 'delete')->middleware("authorize:Branch delete");
                Route::get('getBranchesByStation', 'getBranchesByStation');
            });

            Route::prefix('/shipment_amounts')->controller(ShipmentAmountController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipment Amount access");
                Route::post('store', 'store')->middleware("authorize:Shipment Amount create");
                Route::post('update', 'update')->middleware("authorize:Shipment Amount update");
                Route::post('delete', 'delete')->middleware("authorize:Shipment Amount delete");
                Route::get('all', 'all');
            });

            Route::prefix('/shipment_information')->controller(ShipmentInformationController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipment Information access");
                Route::post('store', 'store')->middleware("authorize:Shipment Information create");
                // Route::post('edit/{id}', 'edit')->middleware("authorize:Shipment Information update");
                Route::post('update', 'update')->middleware("authorize:Shipment Information update");
                Route::post('delete', 'delete')->middleware("authorize:Shipment Information delete");
                Route::get('all', 'all');
            });

            Route::prefix('/shipment_items')->controller(ShipmentItemController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipment Item access");
                Route::post('store', 'store')->middleware("authorize:Shipment Item create");
                Route::post('update', 'update')->middleware("authorize:Shipment Item update");
                Route::post('delete', 'delete')->middleware("authorize:Shipment Item delete");
                Route::get('all', 'all');
            });

            Route::prefix('/returns')->controller(ReturnController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Return access");
                Route::get('show/{id}', 'show')->middleware("authorize:Return access");
                Route::post('update', 'update')->middleware("authorize:Return update");
                Route::post('export', 'export')->middleware("authorize:Return access");
                Route::get('export_template', 'exportTemplate')->middleware("authorize:Return access");
            });

            Route::prefix('/return-queue')->controller(ReturnQueueController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Return access");
            });
        
        
    Route::prefix('/containers')->controller(ContainerController::class)->middleware('role:Hub Admin,Station Admin,Warehouse Supervisor,Super Admin')->group(function () {
        Route::get('', 'index')->middleware("authorize:Container access");
        Route::post('store', 'store')->middleware("authorize:Container create");
        Route::get('show/{container}', 'show')->middleware("authorize:Container access");
        Route::post('update/{container}', 'update')->middleware("authorize:Container update");
        Route::post('delete/{container}', 'destroy')->middleware("authorize:Container delete");
        
        // Shipment Management
        Route::post('{container}/add-shipment', 'addShipment');
        Route::post('{container}/remove-shipment', 'removeShipment');
        Route::post('{container}/add-multiple-shipments', 'addMultipleShipments');
        Route::post('{container}/remove-multiple-shipments', 'removeMultipleShipments');
        Route::post('{container}/move-shipments', 'moveShipments');
        Route::get('{container}/shipments', 'getShipments');
        Route::post('{container}/empty', 'empty');
        
        // Lifecycle
        Route::post('{container}/seal', 'seal');
        Route::post('{container}/unload', 'unload');
        Route::post('{container}/close', 'close');
        Route::post('{container}/update-status', 'updateStatus');
        
        // Print
        Route::get('printContainer', 'printContainer');
    });

            
    Route::prefix('/shipments')->controller(ShipmentController::class)->middleware('exclude.role:Driver,Guest Driver,Vendor Driver,merchant')->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipment access");
                Route::get('unregistered', 'unregisteredShipments')->middleware("authorize:Shipment access");
                Route::get('future-shipments', 'futureShipments')->middleware("authorize:Shipment Future access");
                Route::get('pickup-unassigned', 'pickupUnassignedIndex')->middleware("authorize:Shipment access");
                Route::get('track/{trackingNo}', 'track')->middleware("authorize:Shipment access");
                Route::get('show/{id}', 'showById')->middleware("authorize:Shipment access");
                Route::get('history', 'history')->middleware("authorize:Shipment access");
                Route::post('history/export', 'exportHistoryShipment')->middleware("authorize:Shipment access");
                Route::post('store', 'store')->middleware("authorize:Shipment create");
                Route::post('edit/{id}', 'edit')->middleware(middleware: "authorize:Shipment update");
                Route::post('show/{tracking_no}', 'show')->middleware("authorize:Shipment access");
                Route::post('update', 'update')->middleware("authorize:Shipment update");
                Route::post('delete', 'delete')->middleware(middleware: "authorize:Shipment delete");
                Route::post('getSingle', 'getSingle')->middleware("authorize:Shipment access");
                Route::get('statuses', 'statuses')->middleware("authorize:Shipment access");
                Route::get('by-status', 'byStatus')->middleware("authorize:Shipment access");
                Route::get('printShipment', 'printShipment');
                Route::post('printMultipleShipments', 'printMultipleShipments');
                Route::post('printCompleted', 'printCompleted');
                Route::get('realtime-query', 'indexAllForRealtimeQuery')->middleware("authorize:Realtime Tracking access");
                Route::post('sort-shipment', 'sortShipment')->middleware("authorize:Shipment access");
                Route::post('assign-shipment', 'assignShipment')->middleware("role:Sorter");
                Route::post('/fcm/assign-notify', 'sendAssignNotification');
                Route::post('unassign-shipment', 'unassignShipment');
                Route::get('driver_shipments', 'driver_shipments');
                Route::post('confirm-assign-shipment', 'confirmAssignShipment')->middleware("role:Driver,Guest Driver,Vendor Driver");
                Route::post('confirm-all-assigned', 'confirmAllAssigned')->middleware("role:Driver,Guest Driver,Vendor Driver");
                Route::post('update_bulk_status', 'update_bulk_status');
                Route::get('not_deliver', 'not_deliver');
                Route::post('/ndr/reschedule', 'reschedule');
                Route::post('mark_resolved', 'mark_resolved');
                Route::get('all', 'all');
                Route::get('import_template', 'import_template');
                Route::post('import_preview', 'import_preview');
                Route::post('import', 'import');
                Route::get('reports', 'reports');
                Route::post('process_batch', 'processBatch');
                Route::get('import_progress', 'getImportProgress');
                Route::get('reports', 'reports');
                Route::post('reports/export', 'exportReports')->middleware("authorize:Shipment access");
                Route::post('getStatName', 'getStatName');
                Route::post('update-status', 'updateStatus')->middleware("role:Super Admin");
                Route::post('getMultiple', 'getMultiple')->middleware("authorize:Shipment access");
                Route::post('export', 'export');
                Route::post('{shipment}/generate-tracking-no', 'generateTrackingNo');

                Route::post('generate-tracking-no', 'bulkGenerateTrackingNo');
                Route::get('/pickup-unassigned-proofs/{type}', 'pickupUnassignedProofs')->middleware('authorize:Shipment access');

                Route::get('/statuses', function () {
                    return response()->json([
                        'data' => collect(ShipmentStatusEnum::getAll())->map(fn($status) => [
                            'key' => $status,
                            'label' => str_replace('_', ' ', ucfirst(strtolower($status))),
                        ])->values()
                    ]);
                });
                Route::get('/statuses/delivery/exceptions', function () {
                    return response()->json([
                        'data' => collect(DeliveryExceptionEnum::all())->map(fn($status) => [
                            'key' => $status,
                            'label' => str_replace('_', ' ', ucfirst(strtolower($status))),
                        ])->values()
                    ]);
                });
            });

            Route::prefix('/statuses')->controller(ShipmentStatusController::class)->group(function () {
                Route::get('all', 'index')->middleware("authorize:Shipment Status access");
                Route::post('store', 'store')->middleware("authorize:Shipment Status create");
                Route::post('update', 'update')->middleware("authorize:Shipment Status update");
                Route::post('delete', 'delete')->middleware("authorize:Shipment Status delete");
            });

            Route::prefix('/merchants')->controller(MerchantController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Merchant access");
                Route::get('deleted', 'deleted_merchants_index')->middleware("authorize:Merchant access");
                Route::post('store', 'store')->middleware("authorize:Merchant create");
                Route::post('update', 'update')->middleware("authorize:Merchant update");
                Route::get('show/{id}', 'edit');
                Route::post('edit/{id}', 'edit');
                Route::post('delete', 'delete')->middleware("authorize:Merchant delete");
                Route::post('restore', 'restoreMerchant')->middleware("authorize:Merchant delete");
                Route::post('update-default-commission', 'updateDefaultMerchantCommission');
                Route::get('default-commission', 'getDefaultMerchantCommission');
                Route::get('all', 'all')->middleware("authorize:Assign Pickup Task access | Merchant access");
                Route::post('{id}/toggle-active-status', 'toggleMerchantStatus')->middleware('authorize:Merchant Accounts access');                 // KPIs + جدول
                Route::get('{id}/profile', 'profile');
                Route::get('getSingle', 'getSingle');
                Route::get('/by-phone', 'findByPhone');
                Route::post('kpi', 'merchantKpi');
                Route::post('{merchantId}/add-images', 'addImage')->middleware('authorize:Merchant update');
                Route::post('{imageId}/delete-image', 'deleteImage')->middleware('authorize:Merchant update');
                Route::get('{merchantId}/images', 'getImages')->middleware('authorize:Merchant access');
            });

            Route::prefix('/merchant_accounts')->controller(MerchantAccountController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Merchant Accounts access');
                Route::get('exportAll', 'exportAll')->middleware('authorize:Merchant Accounts access');
                Route::get('{id}', 'show')->middleware('authorize:Merchant Accounts access');                 // KPIs + جدول
                Route::post('{id}/export', 'export')->middleware('authorize:Merchant Accounts access');        // CSV/XLSX
                Route::post('{id}/settlements', 'storeSettlement')->middleware('authorize:Merchant Settlement create');
            });

            Route::prefix('/merchant_invoices')
                ->controller(MerchantInvoicesController::class)
                ->group(function () {
                    Route::get('all', 'index')
                        ->middleware('authorize:Merchant Accounts access');
                    Route::get('{merchant_id}', 'show')
                        ->middleware('authorize:Merchant Accounts access');
                });
            Route::prefix('/driver_invoices')
                ->controller(DriverInvoicesController::class)
                ->group(function () {
                    Route::get('all', 'index')
                        ->middleware('authorize:Driver Accounts access');
                    Route::get('{driver_id}', 'show')
                        ->middleware('authorize:Driver Accounts access');
                });
            Route::delete('delete/merchant/account', [MerchantAccountController::class, 'deactivateSelf']);
            Route::prefix('/transfer-fees')->controller(TransferFeeController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/options', 'options'); // hubs/stations lists
                Route::post('/store', 'store');
                Route::post('/update', 'update');
                Route::delete('/delete', 'destroy');
            });


            Route::prefix('/merchant/account')->middleware('role:Merchant,Super Admin')->group(function () {
                Route::get('', [MerchantAccountController::class, 'merchantAccount']);
                Route::post('settlement-requests', [MerchantAccountController::class, 'requestSettlement']);
            });

            Route::prefix('/driver-accounts')->controller(DriverAccountController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Driver Accounts access");
                Route::get('exportAll', 'exportAll')->middleware("authorize:Driver Accounts access");
                Route::get('{driver}/total-bonus', 'getTotalBonus')->middleware("authorize:Driver Accounts access");
                Route::get('{driver}', 'show')->middleware("authorize:Driver Accounts access");
                Route::post('{driver}/deposits', 'storeDeposit')->middleware("authorize:Driver Accounts create");
                Route::post('{driver}/payouts', 'storePayout')->middleware("authorize:Driver Accounts create");
                Route::post('multiple/payout', 'multiPayout')->middleware("authorize:Driver Accounts create");
                Route::get('{driver}/advances/next-receipt', 'nextAdvanceReceipt')->middleware("authorize:Driver Accounts access");
                Route::post('{driver}/advances', 'storeAdvance')->middleware("authorize:Driver Accounts create");
                Route::post('advances/{advance}/update-image', 'updateAdvanceImage')->middleware("authorize:Driver Accounts update");

                Route::get('{driver}/export', 'export')->middleware("authorize:Driver Accounts access");
            });

            Route::prefix('/merchant_settings')->controller(MerchantSettingController::class)->group(function () {
                Route::get('{merchant_id}', 'show');
                Route::post('{merchant_id}/update', 'update');
                Route::post('{merchant_id}/toggle-created-shipment-notification', 'toggleCreatedShipmentNotification');
            });

            Route::prefix('/merchant_commissions')->controller(MerchantCommissionController::class)->group(function () {
                Route::get('defaults', 'getDefaults');
                Route::post('defaults', 'saveDefaults');
                Route::get('{merchant_id}', 'merchant_commissions');

                // Route::get('{merchant_id}', 'index');
    
                Route::post('store_commissions', 'store_commissions')->middleware("authorize:Merchant Commission create");
                Route::post('store', 'store')->middleware("authorize:Merchant Commission create");
                Route::post('update', 'update')->middleware("authorize:Merchant Commission update");
                Route::post('delete', 'delete')->middleware("authorize:Merchant Commission delete");
                Route::get('all', 'all');
                Route::get('by_state/{merchant_id}/{state_id}', 'by_state');
            });
            Route::prefix('/shipper_commissions')->controller(ShipperCommissionController::class)->group(function () {
                Route::get('by_state/{shipper_id}/{state_id}', 'byState');
            });
            Route::prefix('/merchant_waybills')->controller(MerchantWaybillController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Merchant Waybill access");
                Route::get('show', 'show')->middleware("authorize:Merchant Waybill access");
                Route::post('store', 'store')->middleware("authorize:Merchant Waybill create");
                Route::post('update', 'update')->middleware("authorize:Merchant Waybill update");
                Route::post('delete', 'delete')->middleware("authorize:Merchant Waybill delete");
                Route::get('print', 'printWaybill')->middleware("authorize:Shipment access");
                Route::post('printMultipleWaybills', 'printMultipleWaybills')->middleware("authorize:Shipment access");
                Route::post('printCompleted', 'printCompleted')->middleware("authorize:Shipment access");

                Route::get('batches', 'batches');
                Route::get('batches/{id}', 'showBatches');
            });
            Route::prefix('/driver_waybills')->controller(DriverWaybillController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Driver Waybill access");
                Route::get('show', 'show')->middleware("authorize:Driver Waybill access");

                Route::post('store', 'store')->middleware("authorize:Driver Waybill create");
                Route::post('update', 'update')->middleware("authorize:Driver Waybill update");
                Route::post('delete', 'delete')->middleware("authorize:Driver Waybill delete");

                Route::get('print', 'printWaybill')->middleware("authorize:Shipment access");
                Route::post('printMultipleWaybills', 'printMultipleWaybills')->middleware("authorize:Shipment access");
                Route::post('printCompleted', 'printCompleted')->middleware("authorize:Shipment access");

                Route::get('batches', 'batches');
                Route::get('batches/{id}', 'showBatches');
            });

            Route::prefix('/pickup_tasks')->controller(MerchantPickupTaskController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Pickup Task access");
                Route::get('show', 'show')->middleware("authorize:Pickup Task access");
                Route::post('store', 'store')->middleware("authorize:Pickup Task create");
                Route::post('update', 'update')->middleware("authorize:Pickup Task update");
                Route::post('delete', 'delete')->middleware("authorize:Pickup Task delete");
                Route::post('change_status', 'change_status')->middleware("authorize:Pickup Task update");
                Route::post('cached-shipment-stepper', 'updateCachedShipmentStepper');
                // Route::get('all', 'all');
            });
            Route::post('/pickup-tasks/{task}/picked-shipments', [MerchantPickupTaskController::class, 'updatePickedShipments']);

            Route::prefix('/merchant_pickup_shipments')->controller(MerchantPickupShipmentController::class)->group(function () {
                Route::post('store', 'store')->middleware("authorize:Assign Pickup Task create");
                Route::post('assign_to_request', 'Assign_PickupTask_To_PickupRequest')->middleware("authorize:Assign Pickup Task create");
                Route::get('merchant_created_pickup_requests', 'merchant_created_pickup_requests')->middleware("authorize:Assign Pickup Task access");
                Route::get('merchant_created_shipments/{merchant_id}', 'merchant_created_shipments')->middleware("authorize:Assign Pickup Task access");
                Route::get('merchant_created_shipments_count/{merchant_id}', 'merchant_created_shipments_count')->middleware("authorize:Assign Pickup Task access");
                Route::get('assigned_pickups', 'active_pickups')->middleware("authorize:Assign Pickup Task access");
            });

            Route::get('active-pickup-requests', [MerchantPickupShipmentController::class, 'active_pickups'])
                ->middleware("authorize:Assign Pickup Task access");

            // ============================================================
            // NEW: Return Requests Routes (Unified Architecture)
            // ============================================================
    
            // Merchant return request routes
            Route::prefix('/return-requests')->controller(ReturnRequestController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::post('/{id}/cancel', 'cancel');
                Route::get('/shipments', 'getShipmentsForAuthMerchant');
                Route::get('/cancelled', 'getCancelled');
                Route::post('/{id}/assign', 'assignToDriver');
                Route::post('/{id}/assign-by-zone', 'assignByZone');
            });

            // Driver return pickup routes
            Route::prefix('/driver/return-pickup')->controller(DriverReturnPickupController::class)->group(function () {
                Route::get('/my-tasks', 'myTasks');
                Route::post('/pickup', 'pickupShipment');
                Route::post('/scan-hub', 'scanAtHub');
                Route::post('/deliver-to-merchant', 'deliverToMerchant');
                Route::get('/shipment/{tracking_no}', 'getShipmentDetails');
            });

            // Backward compatibility routes removed - using unified return architecture only
    
            // Driver return intake (unified)
            Route::prefix('/driver/returns')->group(function () {
                Route::post('/scan', [DriverReversePickupController::class, 'returnToWarehouse']);
            });

            // Old reverse-shipments routes removed - use /return-requests instead
    


            Route::prefix('/shelf-categories')->controller(ShelfCategoryController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shelf Category access");
                Route::post('store', 'store')->middleware("authorize:Shelf Category create");
                Route::post('update', 'update')->middleware("authorize:Shelf Category update");
                Route::post('delete', 'delete')->middleware("authorize:Shelf Category delete");
                Route::get('all', 'all');
                Route::get('getSingle', 'getSingle');
                Route::get('printCategory', 'printCategory');
            });

            Route::prefix('/shelves')->controller(ShelfController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shelf access");
                Route::post('store', 'store')->middleware("authorize:Shelf create");
                Route::post('update', 'update')->middleware("authorize:Shelf update");
                Route::delete('delete', 'delete')->middleware("authorize:Shelf delete");
                Route::get('all', 'all');
                Route::get('getSingle', 'getSingle');
                Route::get('printShelf', 'printShelf');
                Route::get('printShelfByCategory', 'printShelfByCategory');

                Route::get('shipments', 'shipments')->middleware("authorize:Shelf access");
                Route::post('/printMultiple', 'printMultiple');
            });


            // @Group User Management
            Route::prefix('/users')->controller(UserController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:User access");
                Route::post('store', 'store')->middleware("authorize:User create");
                Route::post('import', 'import')->middleware("authorize:User import");
                Route::get('export', 'export')->middleware("authorize:User export");
                Route::post('store_branch_admin', 'store_branch_admin')->middleware("authorize:User create");
                Route::post('store_station_admin', 'store_station_admin')->middleware("authorize:User create");
                Route::post('store_hub_admin', 'store_hub_admin')->middleware("authorize:User create");
                Route::post('store_driver', 'store_driver')->middleware("authorize:User create");

                Route::post('edit/{id}', 'edit')->middleware("authorize:User update");
                Route::post('update', 'update')->middleware("authorize:User update");
                Route::post('delete', 'delete')->middleware("authorize:User delete");
                Route::post('change_password', 'change_password');

                Route::get('all', 'all');
                Route::get('get-all-drivers', 'getAllDrivers')->middleware("authorize:Assign Pickup Task access | Drivers access");
                Route::get('get-all-drivers-with-bonuses', 'getAllDriversWithBonuses');
                Route::get('get-all-merchants-with-balances', 'getAllMerchantsWithBalances');
                Route::get('crm-agents', 'crm_agents')->middleware("authorize:User access");
                Route::get('warehouse_managers', 'warehouse_managers')->middleware("authorize:User access");
            });

            // @group user management
            Route::prefix('/drivers')->controller(DriverController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Drivers access | Problems access");
                Route::get('deleted', 'deleted_drivers_index')->middleware("authorize:Drivers access | Problems access");
                Route::post('store', 'store')->middleware("authorize:Drivers create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Drivers update");
                Route::post('update/{id}', 'update')->middleware("authorize:Drivers update");
                Route::post('delete', 'delete')->middleware("authorize:Drivers delete");
                Route::post('restore', 'restoreDriver')->middleware("authorize:Drivers delete");
                Route::post('export', 'export')->middleware("authorize:Drivers export");
                Route::get('all', 'all');
                Route::get('getSingle', 'getSingle');
                Route::post('change_status/{driver_id}', 'change_status');
                Route::post('change_edit_proof/{driver_id}', 'change_edit_proof');
                Route::get('performance', [DriverPerformanceController::class, 'index']);
                Route::get('{driverId}/history', [DriverPerformanceController::class, 'history']);
                Route::get('performance/export/csv', [DriverPerformanceController::class, 'exportCsv']);
                Route::get('performance/export/pdf', [DriverPerformanceController::class, 'exportPdf']);
                Route::post('update-default-bonuse', 'updateDefaultDriverBonuse');
                Route::get('default-bonuse', 'getDefaultDriverBonuse');
                Route::get('/daily-summary', 'dailySummary');

                Route::get('/{driver_id}/stop-lists', [DriverStopListController::class, 'index'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::post('/{driver_id}/stop-lists', [DriverStopListController::class, 'store'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::put('/{driver_id}/stop-lists/{list_id}', [DriverStopListController::class, 'update'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::delete('/{driver_id}/stop-lists/{list_id}', [DriverStopListController::class, 'destroy'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');

                Route::get('/{driver_id}/stop-lists/{list_id}/stops', [DriverStopController::class, 'index'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::post('/{driver_id}/stop-lists/{list_id}/stops', [DriverStopController::class, 'store'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::post('/{driver_id}/stop-lists/{list_id}/stops/bulk', [DriverStopController::class, 'bulkStore'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::put('/{driver_id}/stops/{stop_id}', [DriverStopController::class, 'update'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::delete('/{driver_id}/stops/{stop_id}', [DriverStopController::class, 'destroy'])->middleware('role:Driver,Guest Driver,Vendor Driver,Driver Admin,Super Admin');
                Route::post('/stats', 'stats');
                Route::get('/notifications', [\App\Http\Controllers\Api\v1\DriverNotificationController::class, 'index']);
            });

            Route::prefix('/expenses')->controller(ExpenseController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Expense access");
                Route::post('store', 'store')->middleware("authorize:Expense create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Expense update");
                Route::post('update', 'update')->middleware("authorize:Expense update");
                Route::post('delete', 'delete')->middleware("authorize:Expense delete");
                Route::get('all', 'all');
            });

            Route::prefix('/delivery_exceptions')->controller(DeliveryExceptionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Delivery Exception access | Problems access");
                Route::post('store', 'store')->middleware("authorize:Delivery Exception create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Delivery Exception update");
                Route::post('update', 'update')->middleware("authorize:Delivery Exception update");
                Route::post('delete', 'delete')->middleware("authorize:Delivery Exception delete");
                Route::post('export', 'export')->middleware("authorize:Delivery Exception access");
                Route::get('all', 'all');
                Route::get('all_delivery_exceptions', 'all_delivery_exceptions');
            });
            // change permissions
            // Route::prefix('/delivery_commissions')->controller(DeliveryCommissionController::class)->group(function () {
            //     Route::get('', 'index')->middleware("authorize:Delivery Commission access");
            //     Route::post('store', 'store')->middleware("authorize:Delivery Commission create");
            //     Route::post('edit/{id}', 'edit')->middleware("authorize:Delivery Commission update");
            //     Route::post('update', 'update')->middleware("authorize:Delivery Commission update");
            //     Route::post('delete', 'delete')->middleware("authorize:Delivery Commission delete");
            //     Route::get('all', 'all');
            // });
    
            Route::prefix('/driver_commissions')->controller(DriverCommissionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Delivery Commission access");
                Route::post('store', 'store')->middleware("authorize:Delivery Commission create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Delivery Commission update");
                Route::post('update', 'update')->middleware("authorize:Delivery Commission update");
                Route::post('delete', 'delete')->middleware("authorize:Delivery Commission delete");
                Route::get('all', 'all');
            });

            Route::apiResource('driver-bonus-templates', \App\Http\Controllers\Api\v1\DriverBonusTemplateController::class);

            // CHANGE ME
            Route::prefix('/shipper_commissions')->controller(ShipperCommissionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shipper access");
                Route::post('store', 'store')->middleware("authorize:Shipper create");
            });

            Route::prefix('/settings')->controller(SettingController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Setting access");
                Route::get('get/{key}', 'get')->middleware("authorize:Setting access");
                Route::post('store', 'store')->middleware("authorize:Setting create");
                Route::post('update', 'update')->middleware("authorize:Setting update");
                Route::post('delete', 'delete')->middleware("authorize:Setting delete");
                Route::post('change_status', 'change_status')->middleware("authorize:Setting update");
            });

            Route::prefix('/profile')->controller(ProfileController::class)->group(function () {
                Route::post('update', 'update');
                Route::post('update_password', 'update_password');
            });

            Route::prefix('/assign_shipment_to_shelf')->controller(AssignShipmentToShelfController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Shelf access");
                Route::post('store', 'store');
                Route::post('shipments/{barcode}', 'shelf_shipments');
            });


            Route::prefix('/transfer_tasks')->controller(TransferTaskController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Transfer Task access");
                Route::post('store', 'store')->middleware("authorize:Transfer Task create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Transfer Task access");
                Route::post('update', 'update')->middleware("authorize:Transfer Task update");
                Route::get('pending-transfer-shipments', 'getPendingTransferShipments')->middleware("authorize:Transfer Task access");
                Route::post('change_status', 'change_status')->middleware("authorize:Transfer Task update");
                Route::post('delete', 'delete')->middleware("authorize:Transfer Task delete");
                Route::get('areas', 'areas');
                Route::get('by_status/{status}', 'transfer_tasks_by_status');
                Route::get('destinations/{transfer_task_id}/{status}', 'transfer_tasks_destinations');
                Route::post('add_destination', 'add_destination')->middleware("authorize:Transfer Task create");
                Route::get('incoming_trucks', 'incoming_trucks');
                Route::get('/transfer-areas', 'transfer_areas');
            });

            Route::prefix('/trucks')->controller(TruckController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Truck access");
                Route::post('store', 'store')->middleware("authorize:Truck create");
                Route::get('view/{barcode}', 'view')->middleware("authorize:Truck update");
                Route::post('update', 'update')->middleware("authorize:Truck update");
                Route::post('delete', 'delete')->middleware("authorize:Truck delete");
                Route::post('export', 'export')->middleware("authorize:Truck access");
                Route::get('all', 'all');
                Route::get('printTruckBarcode', 'printTruckBarcode');
            });

            Route::prefix('/truck_drivers')->controller(TruckDriverController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Truck Driver access");
                Route::post('store', 'store')->middleware("authorize:Truck Driver create");
                Route::post('update', 'update')->middleware("authorize:Truck Driver update");
                Route::post('export', 'export')->middleware("authorize:Truck Driver access");
                Route::post('delete', 'delete')->middleware("authorize:Truck Driver delete");
                Route::get('all', 'all');
            });

            ///
            /// HR Mangement
            ///
    
            // Employee Department
            Route::prefix('/employee_departments')->controller(EmployeeDepartmentController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee Department access");
                Route::post('store', 'store')->middleware("authorize:Employee Department create");
                Route::post('update', 'update')->middleware("authorize:Employee Department update");
                Route::post('delete', 'delete')->middleware("authorize:Employee Department delete");
                Route::get('all', 'all');
            });

            // Employee Positions
            Route::prefix('/employee_positions')->controller(EmployeePositionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee Position access");
                Route::post('store', 'store')->middleware("authorize:Employee Position create");
                Route::post('update', 'update')->middleware("authorize:Employee Position update");
                Route::post('delete', 'delete')->middleware("authorize:Employee Position delete");
                Route::get('all', 'all');
                Route::get('/positions/{department_id}', [EmployeePositionController::class, 'getByDepartment']);
            });

            // Employee
            Route::prefix('/employees')->controller(EmployeeController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee access");
                Route::post('store', 'store')->middleware("authorize:Employee create");
                Route::post('update', 'update')->middleware("authorize:Employee update");
                Route::get('show/{id}', 'show')->middleware("authorize:Employee access");
                Route::post('delete', 'delete')->middleware("authorize:Employee delete");
                Route::get('all', 'all');
            });

            // Scheduled Messages
            Route::prefix('/messages/scheduled')->controller(ScheduledMessageController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::put('', 'update');
                Route::post('delete', 'destroy');
                Route::get('logs/{id}', 'logs');
            });

            // Employee Branch
            Route::prefix('/employee_branches')->controller(EmployeeBranchController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee Branch access");
                Route::post('store', 'store')->middleware("authorize:Employee Branch create");
                Route::post('update', 'update')->middleware("authorize:Employee Branch update");
                Route::post('delete', 'delete')->middleware("authorize:Employee Branch delete");
                Route::get('all', 'all');
            });

            // Work Times
            Route::prefix('/work_times')->controller(EmployeeWorkTimeController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Work Time access");
                Route::get('show/{id}', 'show')->middleware("authorize:Work Time access");
                Route::post('start', 'store')->middleware("authorize:Work Time create");
                Route::post('end', 'update')->middleware("authorize:Work Time update");
                Route::post('delete', 'delete')->middleware("authorize:Work Time delete");
                Route::get('all', 'all');
            });

            // Employee Payrolls
            Route::prefix('/employee_payrolls')->controller(EmployeePayrollController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee Payroll access");
                Route::post('store', 'store')->middleware("authorize:Employee Payroll create");
                Route::post('update', 'update')->middleware("authorize:Employee Payroll update");
                Route::post('delete', 'delete')->middleware("authorize:Employee Payroll delete");
                Route::get('all', 'all');
            });

            // Hierarchy Level
            Route::prefix('/hierarchy_levels')->controller(HierarchyLevelController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Hierarchy Level access");
                Route::post('store', 'store')->middleware("authorize:Hierarchy Level create");
                Route::post('update', 'update')->middleware("authorize:Hierarchy Level update");
                Route::post('delete', 'delete')->middleware("authorize:Hierarchy Level delete");
                Route::get('all', 'all');
            });

            // Employee Hierarchy
            Route::prefix('/employee_hierarchies')->controller(EmployeeHierarchyController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Employee Hierarchy access");
                Route::post('store', 'store')->middleware("authorize:Employee Hierarchy create");
                Route::post('update', 'update')->middleware("authorize:Employee Hierarchy update");
                Route::post('delete', 'delete')->middleware("authorize:Employee Hierarchy delete");
                Route::get('all', 'all');
            });

            // Leave Reason
            Route::prefix('/leave_reasons')->controller(LeaveReasonController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Leave Reason access");
                Route::post('store', 'store')->middleware("authorize:Leave Reason create");
                Route::post('update', 'update')->middleware("authorize:Leave Reason update");
                Route::post('delete', 'delete')->middleware("authorize:Leave Reason delete");
                Route::get('all', 'all');
            });

            // Leave Request
            Route::prefix('/leave_requests')->controller(LeaveRequestController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Leave Request access");
                Route::post('store', 'store')->middleware("authorize:Leave Request create");
                Route::post('update', 'update')->middleware("authorize:Leave Request update");
                Route::post('delete', 'delete')->middleware("authorize:Leave Request delete");
                Route::post('approve', 'approve');
                Route::post('reject', 'reject');
                Route::get('all', 'all');
            });


            // Leave Request Approval
            Route::prefix('/leave_request_approvals')->controller(LeaveRequestApprovalController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Leave Request Approval access");
                Route::post('store', 'store')->middleware("authorize:Leave Request Approval create");
                Route::post('update', 'update')->middleware("authorize:Leave Request Approval update");
                Route::post('delete', 'delete')->middleware("authorize:Leave Request Approval delete");
                Route::get('all', 'all');
            });

            // OFD List
            Route::prefix('/ofd')->controller(OFDListController::class)->group(function () {
                Route::post('', 'index');
            });

            // Runsheet
            Route::prefix('/driver_runsheet')->controller(DriverRunsheetController::class)->group(function () {
                Route::post('', 'index')->middleware("authorize:Driver Runsheet access");
                Route::post('hold/{id}', 'hold')->middleware("authorize:Driver Runsheet update");
            });

            // Quality Check
            Route::prefix('/quality_check')->controller(QualityCheckController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Quality Check access");
                Route::post('send_warning', 'send_warning')->middleware("authorize:Quality Check send warning");
            });

            // Address Updates
            Route::prefix('/address-updates')->controller(AddressController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Quality Check access");
                Route::get('stats', 'stats')->middleware("authorize:Quality Check access");
                Route::get('{id}', 'show')->middleware("authorize:Quality Check access");
                Route::post('{id}/approve', 'approve')->middleware("authorize:Quality Check access");
                Route::post('{id}/reject', 'reject')->middleware("authorize:Quality Check access");
            });

            Route::prefix('/fines')->controller(ShipmentFineController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Fine access");
                Route::post('store', 'store')->middleware("authorize:Fine create");
                Route::post('delete', 'delete')->middleware("authorize:Fine delete");
            });

            Route::prefix('/driver-warnings')->controller(\App\Http\Controllers\Api\v1\DriverWarningController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Fine access");
            });

            // Finance
            Route::prefix('/cod_collection')->controller(CODCollectionController::class)->group(function () {
                Route::get('drivers/{status}', 'index_drivers');
                Route::get('{status}/{driver_id?}', 'index');
                Route::get('{runsheetId}/shipments/{type}', [DriverRunsheetController::class, 'getShipmentsByType']);
                Route::post('hold', 'hold');
                Route::post('store', 'store');
                Route::post('store_holding', 'store_holding');
                Route::get('reports', 'reports');
                Route::post('export', 'export');
                Route::post('export_runsheets', 'exportRunsheets');
            });

            Route::prefix('/pickupdeposits')->controller(PickupDepositController::class)->group(function () {
                Route::get('{status}/{driver_id?}', 'index');
                Route::put('update/status', 'updateStatus');
            });

            Route::prefix('/accounts-warehouse')->controller(AccountsController::class)->group(function () {
                // Route::get('', 'index');
                Route::get('', 'show');
                Route::get('showAll', 'showAll');
                Route::get('export/{type}/{id}', 'export');
            });

            Route::prefix('/partners')->controller(PartnerManagementController::class)->group(function () {
                Route::get('all', 'all');
                Route::post('store', 'store');
                Route::get('{id}/keys', 'keys');
                Route::get('{id}/webhooks', 'webhooks');
                Route::post('{id}/scopes', 'updateScopes')->middleware("authorize:Partner update");
            });

            Route::prefix('/invoices')->controller(InvoiceController::class)->group(function () {
                Route::get('driver_paid_invoices/{id}', 'driver_paid_invoices');
                Route::get('print_pending_driver_invoice/{id}', 'print_pending_driver_invoice');
                Route::get('pending_user_invoice/{id}', 'pending_user_invoice');
                Route::get('completed_user_invoice/{id}', 'completed_user_invoice');
                Route::get('pending_company_driver_invoices/{company_id}', 'pending_company_driver_invoices');
                Route::get('confirm_company_driver_invoices/{company_id}', 'confirm_company_driver_invoices');
                Route::post('settle_driver_invoice', 'settle_driver_invoice');
                Route::get('drivers/{status}', 'index_driver_invoices');
                Route::get('merchants/{status}', 'index_merchant_invoices');
                Route::get('{status}/{driver_id?}', 'driver_index_invoices');
                Route::get('{status}/{merchant_id?}', 'merchant_index_invoices');
                Route::post('bulk_pay', 'bulk_pay');
                Route::post('change_status', 'change_status');
                Route::post('export/{status?}', 'export');
                Route::post('bulk_settle_driver_invoices', 'bulk_settle_driver_invoices');
                Route::post('bulk_confirm_company_driver_invoices/{company_id}', 'bulk_confirm_company_driver_invoices');
            });

            Route::prefix('/merchant_invoices')->controller(MerchantInvoiceController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Merchant Invoice access");
                Route::get('show/{id}', 'show')->middleware("authorize:Merchant Invoice access");
                Route::get('{id}/download', 'download')->middleware("authorize:Merchant Invoice access");
            });

            // Company
            Route::prefix('/companies')->controller(CompanyController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Company access | Problems access");
                Route::post('store', 'store')->middleware("authorize:Company create");
                Route::post('update', 'update')->middleware("authorize:Company update");
                Route::post('delete', 'delete')->middleware("authorize:Company delete");
                Route::get('view', 'view');
                Route::post('store_commissions', 'store_commissions');
                Route::get('commissions', 'commissions');
                Route::get('export_commissions/{company_id}', 'export_commissions');
                Route::get('export_drivers_list/{company_id}', 'export_drivers_list');
                Route::post('import_commissions/{company_id}', 'import_commissions');
                Route::post('update_payment_proof_required/{company_id}', 'update_payment_proof_required');
                Route::get('all', 'all');
                Route::post('drivers_status_update', 'drivers_status_update');
                Route::post('delivery_confirmation_method', 'delivery_confirmation_method');
            });

            // Company Accounts
            Route::prefix('/company-accounts')->controller(CompanyAccountController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Company access');
                Route::get('{companyId}', 'show')->middleware('authorize:Company access');
                Route::post('store', 'store')->middleware('authorize:Company create');
                Route::post('{companyId}/recalculate', 'recalculate')->middleware('authorize:Company access');
            });

            // Country Channels
            Route::prefix('/country_channels')->controller(CountryChannelController::class)->group(function () {
                Route::get('{shipper_id}', 'index')->middleware("authorize:Country Channel access");
                // Route::get('channels', 'channels')->middleware("authorize:Country Channel access");
                Route::post('store', 'store')->middleware("authorize:Country Channel create");
                Route::post('delete', 'delete')->middleware("authorize:Country Channel delete");
            });

            // Governorate Channels
            Route::prefix('/governorate_channels')->controller(GovernorateChannelController::class)->group(function () {
                Route::get('export', 'export')->middleware("authorize:Governorate Channel access");
                Route::post('import', 'import')->middleware("authorize:Governorate Channel create");

                Route::get('{shipper_id}', 'index')
                    ->whereNumber('shipper_id')
                    ->middleware("authorize:Governorate Channel access");
                Route::post('channels', 'channels')->middleware("authorize:Governorate Channel access");
                Route::post('store', 'store')->middleware("authorize:Governorate Channel create");
                Route::post('delete', 'delete')->middleware("authorize:Governorate Channel delete");
            });

            // State Channels
    
            Route::prefix('/state_channels')->controller(StateChannelController::class)->group(function () {

                // Route::get('{shipper_id}', 'index')->middleware("authorize:State Channel access");
                Route::get('{shipper_id}', 'index')
                    ->whereNumber('shipper_id')
                    ->middleware("authorize:State Channel access");
                Route::post('channels', 'channels')->middleware("authorize:State Channel access");
                Route::post('store', 'store')->middleware("authorize:State Channel create");
                Route::post('delete', 'delete')->middleware("authorize:State Channel delete");
                Route::get('export', 'export')->middleware("authorize:State Channel access");
                Route::post('import', 'import')->middleware("authorize:State Channel create");
            });

            Route::prefix('/driver_bonuses')->controller(DriverBonusController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Driver Bonus access");
                Route::post('store', 'store')->middleware("authorize:Driver Bonus create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Driver Bonus update");
                Route::post('update', 'update')->middleware("authorize:Driver Bonus update");
                Route::post('delete', 'delete')->middleware("authorize:Driver Bonus delete");
                Route::get('all', 'all');
            });

            Route::prefix('/abnormalities')->controller(AbnormalityController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Company access");
                Route::post('handle_lost', 'handle_lost')->middleware("authorize:Company create");
                Route::post('handle_damaged', 'handle_damaged')->middleware("authorize:Company create");
                Route::post('handle_missort', 'handle_missort')->middleware("authorize:Company create");
            });

            Route::prefix('/stockout_tasks')->controller(StockOutTaskController::class)->group(function () {
                Route::get('', 'index'); // ->middleware("authorize:StockOutTask access|role:Sorter");
                Route::post('store', 'store'); // ->middleware("authorize:StockOutTask access|role:Sorter");
                Route::get('tasks', 'tasks'); // ->middleware("authorize:StockOutTask access|role:Sorter");
            });

            Route::prefix('/whatsapp_templates')->controller(WhatsappTemplateController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Whatsapp Template access");
                Route::post('store', 'store')->middleware("authorize:Whatsapp Template create");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Whatsapp Template update");
                Route::post('update', 'update')->middleware("authorize:Whatsapp Template update");
                Route::post('delete', 'delete')->middleware("authorize:Whatsapp Template delete");
                Route::get('all', 'all');
            });
            Route::prefix('/email_templates')->controller(EmailTemplateController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Email Template access');
                Route::post('store', 'store')->middleware('authorize:Email Template create');
                Route::post('edit/{id}', 'edit')->middleware('authorize:Email Template update');
                Route::post('update', 'update')->middleware('authorize:Email Template update');
                Route::post('delete', 'delete')->middleware('authorize:Email Template delete');
                Route::get('all', 'all')->middleware('authorize:Email Template access');
            });

            Route::prefix('/driver_invoices')->controller(DriverInvoiceController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Invoice access");
                Route::post('confirm_invoice', 'confirm_invoice')->middleware("authorize:Invoice access");
                Route::get('invoice_details/{invoice_id}', 'invoice_details')->middleware("authorize:Invoice access");
            });

            Route::prefix('/driver_app_settings')->controller(DriverAppSettingController::class)->group(function () {
                Route::get('', 'index');
                Route::get('get/{key}', 'get');
                Route::post('update', 'update')->middleware("authorize:Driver App Setting update");
            });

            Route::prefix('/login_histories')->controller(LoginHistoryController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Login History access");
                Route::post('export', 'export')->middleware("authorize:Login History access");
            });

            Route::prefix('/sessions')->controller(SessionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Session access");
                Route::post('destroy/{id}', 'destroy')->middleware("authorize:Session access");
                Route::post('export', 'export')->middleware("authorize:Session access");
            });

            Route::prefix('/activity_logs')->controller(ActivityLogController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Activity Log access");
                Route::post('edit/{id}', 'edit')->middleware("authorize:Activity Log update");
                Route::post('export', 'export')->middleware("authorize:Activity Log access");
                Route::get('all', 'all');
            });

            Route::prefix('/user_actions')->controller(UserActionController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:User Action access");
                Route::get('show/{id}', 'show')->middleware("authorize:User Action access");
                Route::post('export', 'export')->middleware("authorize:User Action access");
                Route::get('all', 'all')->middleware("authorize:User Action access");
            });

            Route::prefix('/truck_statuses')->controller(TruckStatusController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Truck Status access");
                Route::post('store', 'store')->middleware("authorize:Truck Status store");
                Route::get('all', 'all')->middleware("authorize:Truck Status access");
            });

            Route::prefix('/driver_statuses')->controller(DriverStatusController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Driver Status access");
                Route::post('store', 'store')->middleware("authorize:Driver Status create");
                Route::post('update', 'update')->middleware("authorize:Driver Status update");
                Route::post('delete', 'delete')->middleware("authorize:Driver Status delete");
                Route::get('history/{driver_id}', 'history')->middleware("authorize:Driver Status access");
                Route::get('all', 'all')->middleware("authorize:Driver Status access");
            });

            Route::prefix('/maintenance-schedules')->controller(MaintenanceScheduleController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Maintenance Schedule access");
                Route::post('store', 'store')->middleware("authorize:Maintenance Schedule create");
                Route::get('edit/{id}', 'edit')->middleware("authorize:Maintenance Schedule update");
                Route::post('update', 'update')->middleware("authorize:Maintenance Schedule update");
                Route::post('delete', 'delete')->middleware("authorize:Maintenance Schedule delete");
                Route::post('mark-complete', 'markComplete')->middleware("authorize:Maintenance Schedule update");
                Route::get('trucks', 'getTrucks')->middleware("authorize:Maintenance Schedule access");
                Route::post('export', 'export')->middleware("authorize:Maintenance Schedule access");
                Route::get('all', 'all')->middleware("authorize:Maintenance Schedule access");
            });

            // Automated Tasks
            Route::prefix('/automated_tasks')->controller(AutomatedTaskController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Automated Tasks access");
                Route::post('store', 'store')->middleware("authorize:Automated Tasks create");
                Route::get('{automatedTask}', 'show')->middleware("authorize:Automated Tasks access");
                Route::put('update', 'update')->middleware("authorize:Automated Tasks update");
                Route::post('delete', 'destroy')->middleware("authorize:Automated Tasks delete");
            });
            // Schedule Actions
            Route::prefix('/scheduled-actions')->controller(ScheduledActionController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::get('{id}', 'show');
                Route::put('', 'update');
                Route::post('delete', 'destroy');
                Route::post('{id}/run', 'run');
            });
            // Route Planning
            Route::prefix('/routes')->controller(RouteController::class)->group(function () {
                Route::get('', 'index');
                Route::post('plan', 'plan');
                Route::get('{id}', 'show');
                Route::put('{id}', 'update');
                Route::delete('{id}', 'destroy');
                Route::post('{route}/reshipment', 'reshipmentStops');
            });
            // GPS Tracking Routes
            Route::prefix('/driver-locations')->controller(DriverLocationController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:GPS Tracking access');
                Route::post('', 'storeOrUpdate')->middleware('authorize:GPS Tracking update');
                Route::get('{driver_id}', 'show')->middleware('authorize:GPS Tracking access');
            });

            Route::prefix('/driver-location-histories')->controller(DriverLocationHistoryController::class)->group(function () {
                Route::get('{driver_id}', 'index')->middleware('authorize:GPS Tracking access');
            });
            // Shipment Fulfillment Routes
            Route::prefix('/shipment-fulfillment')->controller(ShipmentFulfillmentController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/generate', 'generate');
                Route::get('/{date}', 'show');
                Route::post('/export', 'export');
            });

            Route::prefix('/on-time-delivery')->controller(OnTimeDeliveryController::class)->group(function () {
                Route::get('/', 'index')->middleware("authorize:On-Time Delivery access");
                Route::post('/generate', 'generate')->middleware("authorize:On-Time Delivery access");
                Route::get('/{date}', 'show')->middleware("authorize:On-Time Delivery access");
                Route::post('/export', 'export')->middleware("authorize:On-Time Delivery access");
            });

            // Fuel Efficiency Routes
            Route::prefix('/fuel-efficiency')->group(function () {
                Route::get('', [FuelEfficiencyReportController::class, 'index']);
                Route::get('export/pdf', [FuelEfficiencyReportController::class, 'exportPdf']);
                Route::get('export/csv', [FuelEfficiencyReportController::class, 'exportCsv']);
            });
            // Financial Reports
            Route::prefix('/financial-reports')->controller(FinancialReportController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Financial Reports view");
                Route::get('export/pdf', 'exportPdf')->middleware("authorize:Financial Reports export");
                Route::get('export/csv', 'exportCsv')->middleware("authorize:Financial Reports export");
                Route::get('details', 'getDetails')->middleware("authorize:Financial Reports view");
            });

            // Finance Module Routes (Dashboard, Reports, Top Performers)
            Route::prefix('/finance')->group(function () {
                // Finance Dashboard
                Route::get('dashboard', [FinanceDashboardController::class, 'index'])
                    ->middleware('authorize:Finance Dashboard access');

                // Accounts Summary (quick cards)
                Route::get('accounts/summary', [FinanceDashboardController::class, 'accountsSummary'])
                    ->middleware('authorize:Finance Dashboard access');

                // Top Performers (by delivered shipments)
                Route::get('top-performers', [FinanceTopPerformersController::class, 'index'])
                    ->middleware('authorize:Finance Dashboard access');

                // Financial Reports
                Route::get('reports', [FinanceReportsController::class, 'index'])
                    ->middleware('authorize:Financial Report access');
                Route::post('reports/export', [FinanceReportsController::class, 'export'])
                    ->middleware('authorize:Financial Report export');

                // Cash Flow
                Route::get('cashflow', [FinanceReportsController::class, 'cashflow'])
                    ->middleware('authorize:Financial Report access');
            });

            // Inventory Items Routes
            Route::prefix('/inventory-items')->controller(InventoryItemController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Inventory Items access");
                Route::post('', 'store')->middleware("authorize:Inventory Items create");
                Route::get('{id}', 'show')->middleware("authorize:Inventory Items access");
                Route::put('', 'update')->middleware("authorize:Inventory Items update");
                Route::post('/delete', 'destroy')->middleware("authorize:Inventory Items delete");
                Route::get('export/csv', 'exportCsv')->middleware("authorize:Inventory Items export");
            });

            // Stock Transactions
            Route::prefix('/stock-transactions')->controller(StockTransactionController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::get('{item}/transactions', 'forItem');
            });

            // Low Stock Alerts
            Route::prefix('/low-stock-alerts')->controller(LowStockAlertController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::put('', 'update');
            });

            // Delivery Slots
            Route::prefix('/delivery-slots')->controller(DeliverySlotController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::put('', 'update');
                Route::post('delete', 'destroy');
            });

            // Scheduled Deliveries
            Route::prefix('/scheduled-deliveries')->controller(ScheduledDeliveryController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::put('', 'update');
                Route::post('delete', 'destroy');
            });

            // Delivery Reminders
            Route::prefix('/delivery-reminders')->controller(DeliveryReminderController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::put('', 'update');
                Route::post('toggle', 'toggle');
            });

            // Compliance Checklists
            Route::prefix('/compliance-checklists')->controller(ComplianceChecklistController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Compliance Checklist access');
                Route::post('store', 'store')->middleware('authorize:Compliance Checklist create');
                Route::get('edit/{id}', 'edit')->middleware('authorize:Compliance Checklist update');
                Route::get('show/{id}', 'show')->middleware('authorize:Compliance Checklist access');
                Route::post('update', 'update')->middleware('authorize:Compliance Checklist update');
                Route::post('delete', 'delete')->middleware('authorize:Compliance Checklist delete');
                Route::get('all', 'all')->middleware('authorize:Compliance Checklist access');
                Route::post('export', 'export')->middleware('authorize:Compliance Checklist access');
                Route::post('{checklistId}/items/{itemId}/mark', 'markItem')->middleware('authorize:Compliance Checklist update');
            });

            // Legal Documents
            Route::prefix('/legal-documents')->controller(LegalDocumentController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Legal Document access');
                Route::post('store', 'store')->middleware('authorize:Legal Document create');
                Route::get('edit/{id}', 'edit')->middleware('authorize:Legal Document update');
                Route::post('update', 'update')->middleware('authorize:Legal Document update');
                Route::post('delete', 'delete')->middleware('authorize:Legal Document delete');
                Route::get('all', 'all')->middleware('authorize:Legal Document access');
                Route::get('{document}/download', 'download')->middleware('authorize:Legal Document access');
            });

            // Support System - Tickets
    

            // Support System - Contact History
            Route::prefix('/contact-history')->controller(ContactHistoryController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Contact History access');
                Route::post('store', 'store')->middleware('authorize:Contact History create');
                Route::get('show/{id}', 'show')->middleware('authorize:Contact History access');
                Route::post('update', 'update')->middleware('authorize:Contact History update');
                Route::post('delete', 'delete')->middleware('authorize:Contact History delete');
                Route::post('export', 'export')->middleware('authorize:Contact History access');
                Route::get('customer/{email}', 'getCustomerHistory')->middleware('authorize:Contact History access');
                Route::get('follow-ups', 'getFollowUps')->middleware('authorize:Contact History access');
            });

            // Custom Alerts
            Route::prefix('/custom-alerts')->controller(CustomAlertController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Custom Alerts access");
                Route::post('store', 'store')->middleware("authorize:Custom Alerts create");
                Route::get('show', 'show')->middleware("authorize:Custom Alerts access");
                Route::post('update', 'update')->middleware("authorize:Custom Alerts update");
                Route::post('delete', 'destroy')->middleware("authorize:Custom Alerts delete");
            });

            // Scenario Routes
            Route::prefix('/scenarios')->controller(ScenarioController::class)->group(function () {
                Route::get('', 'index');
                Route::get('{id}', 'show');
                Route::post('', 'store');
                Route::put('{id}', 'update');
                Route::post('delete', 'destroy');
                Route::post('{id}/reviewed', 'markAsReviewed');
            });
            // Customs Declarations Routes
            Route::prefix('/customs')->controller(CustomsDeclarationController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::post('update/{id}', 'update');
                Route::post('delete', 'destroy');
                Route::get('{id}/pdf', 'generatePdf');
            });
            // Shipment Rules
            Route::prefix('/shipment-rules')->controller(ShipmentRuleController::class)->group(function () {
                Route::get('', 'index');
                Route::post('', 'store');
                Route::post('update', 'update');
                Route::post('delete', 'destroy');
            });
            // Rules Engine
            Route::prefix('/rules')->controller(RuleController::class)->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store');
                Route::post('update', 'update');
                Route::post('delete', 'destroy');
            });

            // -------------------- Merchant Payments --------------------
            Route::prefix('/payments')
                ->middleware(['auth:sanctum'])
                ->group(function () {
                Route::get('summary', [MerchantPaymentController::class, 'summary']);
                Route::get('transactions', [MerchantPaymentController::class, 'transactions']);
            });
        });

        Route::prefix('/partners')->middleware([
            'partner.auth',
            'verify.hmac',
            'rate.limit',
            // 'ip.allow',
            // 'audit',
        ])->group(function () {
            Route::get('/partners/test', [PartnerController::class, 'test']); // Test connection to partner API
            // Route::get('/partners/me', [PartnerController::class, 'me'])->middleware('scopes:partner:read'); // Get partner information
            // Route::get('/partner/quota', [PartnerController::class, 'quota'])->middleware('scopes:quota:read'); // Get partner quota
    
            Route::get('/shipments', [PartnerShipmentController::class, 'index'])->middleware('scopes:read:shipments'); // Get all shipments
            Route::post('/shipments', [PartnerShipmentController::class, 'storeShipment'])->middleware('scopes:write:shipments'); // Create a new shipment
            Route::get('/shipments/{shipment_id}', [PartnerShipmentController::class, 'showByInternalId'])->middleware('scopes:read:shipments'); // Get shipment by internal shipment ID
    
            Route::get('/trackings', [PartnerShipmentController::class, 'listAllTrackings'])->middleware('scopes:tracking:read'); // Get all trackings
            Route::get('/trackings/{tracking_number}', [PartnerShipmentController::class, 'showByTracking'])->middleware('scopes:tracking:read'); // Get tracking by tracking number
        });

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::prefix('/tickets')->controller(TicketController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Ticket access');
                Route::post('store', 'store')->middleware('authorize:Ticket create');
                Route::get('show/{id}', 'show')->middleware('authorize:Ticket access');
                Route::post('update', 'update')->middleware('authorize:Ticket update');
                Route::post('delete', 'delete')->middleware('authorize:Ticket delete');
                Route::get('stats', 'getStats')->middleware('authorize:Ticket access');
                Route::get('agents', 'getAgents')->middleware('authorize:Ticket access'); // Get customer service agents
                Route::get('{ticketId}/chat', 'openChatSession')->middleware('authorize:Ticket access'); // Open chat session for ticket
                Route::post('customer-tickets', 'customerTickets'); // Public endpoint for customers
                Route::post('{ticketId}/comments', 'addComment')->middleware('authorize:Ticket comment');
            });
            Route::get('/financial-requests', [FinancialRequestController::class, 'index']);
            Route::get('/get-net-ballance', [FinancialRequestController::class, 'getNetBalanceForCurrentWarehouse']);
            Route::post('/financial-requests', [FinancialRequestController::class, 'store']);
            Route::patch('/financial-requests/{financialRequest}/approve', [FinancialRequestController::class, 'approve']);
            Route::patch('/financial-requests/{financialRequest}/reject', [FinancialRequestController::class, 'reject']);


            // Support System - Chat Sessions
            Route::prefix('/chat-sessions')->controller(ChatController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Chat access');
                Route::get('{sessionId}/history', 'getChatHistory')->middleware('authorize:Chat access'); // Keep original for full history
                Route::post('{sessionId}/message', 'sendMessage')->middleware('authorize:Chat access'); // Add sending messages
                Route::post('{sessionId}/escalate', 'escalateToTicket')->middleware('authorize:Chat access');
                Route::post('{sessionId}/close', 'closeSession')->middleware('authorize:Chat access');
                Route::post('{sessionId}/archive', 'archiveSession')->middleware('authorize:Chat access');
            });


            Route::prefix('/safety-incidents')->controller(SafetyIncidentController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Safety Incident access");
                Route::post('store', 'store')->middleware("authorize:Safety Incident create");
                Route::get('show/{id}', 'show')->middleware("authorize:Safety Incident access");
                Route::get('edit/{id}', 'edit')->middleware("authorize:Safety Incident update");
                Route::post('update', 'update')->middleware("authorize:Safety Incident update");
                Route::post('delete', 'delete')->middleware("authorize:Safety Incident delete");
                Route::post('export', 'export')->middleware("authorize:Safety Incident access");
                Route::get('all', 'all');
            });

            Route::prefix('/merchant_tickets')->controller(MerchantTicketController::class)->group(function () {
                Route::get('', 'index')->middleware('authorize:Merchant Ticket Chat access');
                Route::get('{id}', 'show')->middleware('authorize:Merchant Ticket Chat access');
                Route::get('{id}/messages', 'messages')->middleware('authorize:Merchant Ticket Chat access');
                Route::post('{id}/message', 'sendMessage')->middleware('authorize:Merchant Ticket Chat create');
                Route::post('{id}/close', 'close')->middleware('authorize:Merchant Ticket Chat delete');

                // Debug route for testing WebSocket broadcasting - remove in production
                Route::post('test-broadcast', function () {
                    $testTicket = new \App\Models\MerchantTicket([
                        'id' => 9999,
                        'merchant_id' => 1,
                        'subject' => 'Test WebSocket Broadcast',
                        'status' => 'ACTIVE',
                        'initial_message' => 'This is a test message to verify WebSocket functionality',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    broadcast(new \App\Events\MerchantTicketChanged($testTicket, 'ACTIVE'));

                    return response()->json([
                        'message' => 'Test broadcast sent',
                        'event' => 'MerchantTicketChanged',
                        'ticket_id' => 9999,
                        'change_type' => 'ACTIVE'
                    ]);
                })->middleware('authorize:Merchant Ticket Chat access');
            });

            Route::prefix('/customer-shipments')->controller(CustomerShipmentController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Customer Created Shipments access");
                Route::get('pending', 'getPendingCustomerShipments')->middleware("authorize:Customer Created Shipments access");
                Route::post('accept', 'acceptCustomerShipment')->middleware("authorize:Customer Created Shipments update");
                Route::post('assign', 'assignCustomerShipment')->middleware("authorize:Customer Created Shipments update");
            });

            Route::prefix('/guest-drivers')->controller(GuestDriverController::class)->group(function () {
                Route::get('drivers/{driver_id?}', 'index')->middleware("authorize:Guest Driver access");
                Route::post('approve', 'approve')->middleware("authorize:Guest Driver update");
                Route::post('reject', 'reject')->middleware("authorize:Guest Driver access");
                Route::get('customers', 'customers')->middleware("authorize:Guest Customer Shipment");
                Route::post('stops', 'storeStop');
                Route::get('stops', 'listStops');
                Route::get('status', 'statusSelf');
                Route::delete('driver/{driver}', 'destroy');
                Route::post('drivers/{driver}/convert', 'convert')->whereNumber('driver');
            });

            Route::prefix('/guest-drivers-shipments')->controller(GuestDriverShipmentController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Guest Driver Shipment access");
            });

            Route::prefix('/shipment-archive')->controller(ShipmentArchiveController::class)->group(function () {
                Route::get('', 'index')->middleware('role:Super Admin');
                Route::post('restore', 'restore')->middleware('role:Super Admin');
                Route::post('delete', 'permanentDelete')->middleware('role:Super Admin');
                Route::post('show', 'show')->middleware('role:Super Admin');
            });
            // Container Routes
            Route::prefix('/containers')->controller(ContainerController::class)->group(function () {
                Route::get('', 'index')->middleware("authorize:Container access");
                Route::post('', 'store')->middleware("authorize:Container create");
                Route::get('{container}', 'show')->middleware("authorize:Container access");
                Route::put('{container}', 'update')->middleware("authorize:Container update");
                Route::delete('{container}', 'destroy')->middleware("authorize:Container delete");
                Route::post('{container}/add-shipment', 'addShipment')->middleware("authorize:Container update");
                Route::post('{container}/remove-shipment', 'removeShipment')->middleware("authorize:Container update");
                Route::post('{container}/add-multiple-shipments', 'addMultipleShipments')->middleware("authorize:Container update");
                Route::post('{container}/remove-multiple-shipments', 'removeMultipleShipments')->middleware("authorize:Container update");
                Route::post('{container}/move-shipments', 'moveShipments')->middleware("authorize:Container update");
                Route::get('{container}/shipments', 'getShipments')->middleware("authorize:Container access");
                Route::post('{container}/empty', 'empty')->middleware("authorize:Container update");
                Route::post('{container}/update-status', 'updateStatus')->middleware("authorize:Container update");
                Route::post('printMultipleContainers', 'printMultipleContainers');

            });
            // Wallet Routes
            Route::prefix('/accounts')->controller(WalletController::class)->group(function () {
                Route::get('{type}/{id}', 'show')->middleware("authorize:Merchant access");
                Route::post('{type}/{id}/deposit', 'deposit')->middleware("authorize:Merchant update");
                Route::post('{type}/{id}/withdraw', 'withdraw')->middleware("authorize:Merchant update");
            });
            // Manifest Routes
            Route::prefix('/manifests')->controller(ManifestController::class)->group(function () {
                Route::post('generate', 'generate')->middleware("authorize:Manifest create");
                Route::get('{id}', 'show')->middleware("authorize:Manifest access");
                Route::get('{id}/export', 'export')->middleware("authorize:Manifest access");
            });
            // Route::get('accounts-warehouse/{type}/{id}', [WarehouseAccountController::class, 'show']);
            Route::get('inter-branch-transfers/options', [InterBranchTransferController::class, 'options']);
            Route::get('/runsheets/{id}/transferables', [\App\Http\Controllers\Api\v1\RunsheetTransferablesController::class, 'show']);



            Route::get('/shipments/fee-allocations', [ShipmentFeeAllocationController::class, 'index']);

            Route::get('/shipments/{tracking_no}/fee-allocations', [ShipmentFeeAllocationController::class, 'show']);
            Route::prefix('/inter-branch-transfers')->controller(InterBranchTransferController::class)->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store');
                Route::get('show/{id}', 'show');
                Route::post('approve/{id}', 'approve');
                Route::post('reject/{id}', 'reject');
                Route::post('export', 'export');
                Route::post('from-shipment', 'storeFromShipment');
            });
            Route::get('workspaces', [WorkspaceController::class, 'index']);

            // Calculation endpoints for frontend
            Route::prefix('/calculations')->controller(\App\Http\Controllers\Api\v1\CalculationController::class)->group(function () {
                Route::post('driver-collectible', 'driverCollectible');
                Route::post('merchant-cod', 'merchantCOD');
                Route::post('total-cod', 'totalCOD');
                Route::post('amount', 'amount');
                Route::post('batch', 'batch');
            });

            // Maps URL unshortening endpoint
            Route::get('maps/unshorten', MapsUnshortenController::class);
        });
    });

require __DIR__ . '/ajax.php';
require __DIR__ . '/driver.php';
require __DIR__ . '/vehicle_driver.php';
require __DIR__ . '/merchant.php';
require __DIR__ . '/sorter.php';
require __DIR__ . '/customer-service.php';
