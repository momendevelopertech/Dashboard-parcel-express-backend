<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\VehicleDriverController;
use App\Http\Resources\AuthResource;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::prefix('v1')
    ->group(function () {

        Route::prefix('vehicle_driver')->controller(VehicleDriverController::class)->middleware('auth:sanctum')->group(function () {
            Route::post('update_vehicle_status', 'update_vehicle_status')->middleware("role:Vehicle Driver");
        });
        
        Route::post('vehicle_driver/login', function (Request $request) {
            $request->validate([
                'email' => 'required',
                'password' => 'required',
            ]);
        
            $historyData = [
                'email'      => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
        
            if (User::where('email', '=', $request->email)->count() > 0) {
                if (Auth::attempt($request->only('email', 'password'))) {
                    $user = Auth::user();
        
                    if (!$user->hasRole(["Truck Driver"])) {
                        LoginHistory::create(array_merge($historyData, [
                            'status'  => false,
                            'user_id'  => $user->id,
                        ]));
                        return sendResponse('You are not authorized to access the system.', [], false, 500);
                    }
        
                    $user['token'] = $user->createToken('main')->plainTextToken;
                    LoginHistory::create(array_merge($historyData, [
                        'status'  => true,
                        'user_id'  => $user->id,
                    ]));
                    return sendResponse('Login successful', new AuthResource($user), []);
                } else {
                    LoginHistory::create(array_merge($historyData, [
                        'status'  => false,
                    ]));
                    return sendResponse('Password is incorrect.', [], [], 402);
                }
            } else {
                LoginHistory::create(array_merge($historyData, [
                    'status'  => false,
                ]));
                return sendResponse('No account found with this email.', [], [], 402);
            }
        });
    });

