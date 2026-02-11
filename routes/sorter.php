<?php

use App\Http\Controllers\Api\v1\RTOController;
use App\Http\Controllers\Api\v1\SorterController;
use App\Http\Resources\AuthResource;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Jenssegers\Agent\Agent;



Route::prefix('v1')
    ->group(function () {
        Route::get('/sorter/test', function () {
            return response()->json(['what' => 'is up']);
        });
        
        Route::post('/sorter/login', function (Request $request) {
            if (!$request->filled('login') && $request->filled('email')) {
                $request->merge(['login' => $request->email]);
            }
        
            $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
                'workspace' => 'nullable',
            ]);
        
            $login = trim($request->input('login'));
        
            $historyData = [
                'email' => $login,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
        
            $agent = new Agent();
            $isMobile = $agent->isMobile();
        
            $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
            $isPhone = preg_match('/^\+?\d{7,15}$/', $login);
        
            $user = null;
            if ($isEmail) {
                $user = User::where('email', $login)->first();
            } elseif ($isPhone) {
                $digits = ltrim($login, '+');
                $phoneSplit = splitPhoneNumber($login);
                $user = User::where('country_code', $phoneSplit['country_code'] ?? null)
                    ->where('phone', $phoneSplit['national_number'] ?? null)
                    ->first();
                if (!$user) {
                    $user = User::whereRaw("CONCAT(country_code, phone) = ?", [$digits])
                        ->orWhereRaw("CONCAT('+', country_code, phone) = ?", ['+' . $digits])
                        ->orWhere('phone', $digits)
                        ->first();
                }
            } else {
                $user = User::where('username', $login)->first();
        
            }
        
            if (!$user) {
                LoginHistory::create($historyData + ['status' => false]);
                return sendResponse('No account found for this identifier.', [], false, 402);
            }
        
            if (!Hash::check($request->password, $user->password)) {
                LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                return sendResponse('Password is incorrect.', [], false, 402);
            }
        
            Auth::login($user);
        
            if (!$user->hasRole(['Sorter'])) {
                LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                Auth::logout();
                return sendResponse('You are not authorized to access the system.', [], false, 500);
            }
        
            $user['token'] = $user->createToken('main')->plainTextToken;
        
            $hubs = $user->hubs->map(fn($hub) => [
                'id' => $hub->id,
                'name' => $hub->name,
                'type' => accountables('hub'),
            ]);
        
            $stations = $user->stations->map(fn($station) => [
                'id' => $station->id,
                'name' => $station->name,
                'type' => accountables('station'),
            ]);
        
            $branches = $user->branches->map(fn($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'type' => accountables('branch'),
            ]);
        
            if ($hubs->isEmpty() && $stations->isEmpty() && $branches->isEmpty()) {
                LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
                return sendResponse('User login successful.', new AuthResource($user), true);
            }
        
            $totalFacilities = $hubs->count() + $stations->count() + $branches->count();
            if ($totalFacilities === 1) {
                if ($hubs->count() === 1) {
                    $selectedWorkspace = $hubs->first();
                    $selectedWorkspace['type'] = "App\\Models\\Hub";
                } elseif ($stations->count() === 1) {
                    $selectedWorkspace = $stations->first();
                    $selectedWorkspace['type'] = "App\\Models\\Station";
                } else {
                    $selectedWorkspace = $branches->first();
                    $selectedWorkspace['type'] = "App\\Models\\Branch";
                }
        
                $selectedWorkspace['id'] = Crypt::encryptString($selectedWorkspace['id']);
                $user['workspace'] = $selectedWorkspace;
        
                $request->headers->set('X-Workspace-Key', $selectedWorkspace['id']);
                $request->headers->set('X-Workspace-Type', $selectedWorkspace['type']);
        
                LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
                return sendResponse('Login successful.', new AuthResource($user), true);
            }
        
            $selectedWorkspace = null;
            $workspace = json_decode($request->workspace);
        
            if (!$workspace) {
                LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
                return sendResponse('Please select a workspace to continue.', [
                    'workspace' => collect($hubs)->merge($stations)->merge($branches)->filter()->values()
                ], false, 403);
            }
        
            if ($workspace->type == "App\\Models\\Hub") {
                $selectedWorkspace = $hubs->firstWhere('id', $workspace->id);
                if ($selectedWorkspace)
                    $selectedWorkspace['type'] = "App\\Models\\Hub";
            } elseif ($workspace->type == "App\\Models\\Station") {
                $selectedWorkspace = $stations->firstWhere('id', $workspace->id);
                if ($selectedWorkspace)
                    $selectedWorkspace['type'] = "App\\Models\\Station";
            } elseif ($workspace->type == "App\\Models\\Branch") {
                $selectedWorkspace = $branches->firstWhere('id', $workspace->id);
                if ($selectedWorkspace)
                    $selectedWorkspace['type'] = "App\\Models\\Branch";
            }
        
            if (!$selectedWorkspace) {
                return sendResponse('Invalid workspace selection.', [], false, 403);
            }
        
            $selectedWorkspace['id'] = Crypt::encryptString($selectedWorkspace['id']);
            $user['workspace'] = $selectedWorkspace;
        
            $request->headers->set('X-Workspace-Key', $selectedWorkspace['id']);
            $request->headers->set('X-Workspace-Type', $selectedWorkspace['type']);
        
            LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
        
            return sendResponse('Login successful.', new AuthResource($user), true);
        });
        
        
        // Route::post('sorter/login', function (Request $request) {
        //     $request->validate([
        //         'email' => 'required',
        //         'password' => 'required',
        //     ]);
        
        //     $historyData = [
        //         'email'      => $request->email,
        //         'ip_address' => $request->ip(),
        //         'user_agent' => $request->userAgent(),
        //     ];
        
        //     if (User::where('email', '=', $request->email)->count() > 0) {
        //         if (Auth::attempt($request->only('email', 'password'))) {
        //             $user = Auth::user();
        
        //             if (!$user->hasRole("Sorter")) {
        //                 LoginHistory::create(array_merge($historyData, [
        //                     'status'  => false,
        //                     'user_id'  => $user->id,
        //                 ]));
        //                 return sendResponse('You are not authorized to access the system.', [], false, 500);
        //             }
        
        //             $user['token'] = $user->createToken('main')->plainTextToken;
        
        //             return sendResponse('Login successful', new AuthResource($user), []);
        //         } else {
        //             LoginHistory::create(array_merge($historyData, [
        //                 'status'  => false,
        //             ]));
        //             return sendResponse('Password is incorrect.', [], [], 402);
        //         }
        //     } else {
        //         LoginHistory::create(array_merge($historyData, [
        //             'status'  => false,
        //         ]));
        //         return sendResponse('No account found with this email.', [], [], 402);
        //     }
        // });
        
        
        
        Route::middleware(['auth:sanctum', 'role:Sorter'])->group(function () {
            Route::prefix('/sorter')->controller(SorterController::class)->group(function () {
                Route::post('warehouse_sort', 'warehouse_sort')->name("warehouse_sort");
                Route::post('inbound_sort', 'inbound_sort')->name("inbound_sort");
                Route::post('sort_of_d', 'sort_ofd')->name("sort_ofd");
                Route::post('load', 'load')->name("load");
                Route::post('unload', 'unload')->name("unload");
                Route::post('pickup_sort', 'pickup_sort')->name("pickup_sort");
                Route::post('stockout', 'stockout')->name("stockout");
            });
        
            Route::prefix('/sorter')->controller(RTOController::class)->group(function () {
                Route::post('pick_rto', 'pick_rto')->name("pick_rto");
                Route::post('load_rto', 'load_rto')->name("load_rto");
            });
        });

    });
