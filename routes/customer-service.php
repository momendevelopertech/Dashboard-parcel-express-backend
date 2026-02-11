<?php

// CRM tasks
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\CrmTaskController;

Route::prefix('v1')
    ->group(function () {
        Route::prefix('crm_tasks')->middleware(['auth:sanctum'])->controller(CrmTaskController::class)->group(function () {
            Route::get('', 'index')->middleware("authorize:CRM Task access");
            Route::post('change_status', 'change_status')->middleware("authorize:CRM Task update");
            Route::post('change_shipment_status', 'change_shipment_status')->middleware("authorize:CRM Task update");
            Route::get('get-by-status', 'getTasksByStatus');
        });

    });

