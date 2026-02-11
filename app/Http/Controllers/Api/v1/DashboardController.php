<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Models\Driver;
use App\Models\DriverRunsheet;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\MerchantPickupShipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = [];
        $shipments = Shipment::byOwner();
        if ($user->merchant) {
            $merchantId = $user->merchant->id;
            $regularShipmentsCount = (clone $shipments)->where('merchant_id', $merchantId)->count();
            $unassignedNotConvertedCount = MerchantPickupShipment::whereNull('shipment_id')->count();
            $allUnassignedCount = MerchantPickupShipment::count();
            $data['all_shipments'] = $regularShipmentsCount + $unassignedNotConvertedCount;
            $data['today_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->whereDate('created_at', Carbon::today())
                ->count();
            $data['ofd_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('status', "OFD")
                ->count();
            $data['returns_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('in_exception', true)->count();
            $registeredUnassignedCount = MerchantPickupShipment::whereNotNull('shipment_id')->count();
            $data['unassigned_shipments'] = $allUnassignedCount;
            $data['unassigned_shipments_registered'] = $registeredUnassignedCount;
            $data['delivered_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('status', "DELIVERED")
                ->count();
        } else {
            $regularShipmentsCount = (clone $shipments)->count();
            $unassignedNotConvertedCount = MerchantPickupShipment::whereNull('shipment_id')->count();
            $allUnassignedCount = MerchantPickupShipment::count();
            $data['all_shipments'] = $regularShipmentsCount + $unassignedNotConvertedCount;
            $data['today_shipments'] = (clone $shipments)->whereDate('created_at', Carbon::today())->count();
            $data['ofd_shipments'] = (clone $shipments)->where('status', "OFD")->count();
            $data['returns_shipments'] = (clone $shipments)->where('in_exception', true)->count();
            $registeredUnassignedCount = MerchantPickupShipment::whereNotNull('shipment_id')->count();
            $data['unassigned_shipments'] = $allUnassignedCount;
            $data['unassigned_shipments_registered'] = $registeredUnassignedCount;
            $data['delivered_shipments'] = (clone $shipments)->where('status', "DELIVERED")->count();
            $data['completed_cod'] = DriverRunsheet::where('status', "completed")->count();
            $data['pending_cod'] = DriverRunsheet::where('status', "pending")->count();
            $data['notifications'] = Notification::count();
        }
        $data['active_drivers'] = Driver::count();
        return sendResponse("Dashboard", $data);
    }

    public function quickStats()
    {
        $user = Auth::user();
        $data = [];
        $shipments = Shipment::byOwner();
        if ($user->merchant) {
            $merchantId = $user->merchant->id;
            $data['shipments_in_progress'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('status', "OFD")->count();
            $data['delivered_today'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->whereDate('created_at', Carbon::today())
                ->where('status', 'DELIVERED')
                ->count();
            $data['pending_issues'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('in_exception', true)->count();
        } else {
            $data['shipments_in_progress'] = (clone $shipments)->where('status', "OFD")->count();
            $data['delivered_today'] = (clone $shipments)->whereDate('created_at', Carbon::today())
                ->where('status', 'DELIVERED')
                ->count();
            $data['pending_issues'] = (clone $shipments)->where('in_exception', true)->count();
        }
        $data['active_drivers'] = Driver::count();
        return sendResponse("Quick Stats", $data);
    }

    public function dailySummary(Request $request)
    {
        $user = Auth::user();
        $data = [];
        $shipments = Shipment::byOwner();

        // Get date from request or default to today
        $date = $request->get('date');
        if ($date) {
            try {
                $selectedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Exception $e) {
                return sendResponse('Invalid date format. Please use Y-m-d format.', [], false, [$e->getMessage()], 400);
            }
        } else {
            $selectedDate = Carbon::today()->startOfDay();
        }

        if ($user->merchant) {
            $merchantId = $user->merchant->id;
            // Completed shipments: shipments that were delivered on the selected date
            $data['completed_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->where('status', 'DELIVERED')
                ->whereHas('shipmentHistories', function ($q) use ($selectedDate) {
                    $q->where('name', 'DELIVERED')
                        ->whereDate('time', $selectedDate);
                })
                ->count();
            // Pending shipments: shipments created on the selected date that are still pending
            $data['pending_shipments'] = (clone $shipments)->where('merchant_id', $merchantId)
                ->whereDate('created_at', $selectedDate)
                ->where('status', 'OFD')
                ->count();
        } else {
            // Completed shipments: shipments that were delivered on the selected date
            $data['completed_shipments'] = (clone $shipments)
                ->where('status', 'DELIVERED')
                ->whereHas('shipmentHistories', function ($q) use ($selectedDate) {
                    $q->where('name', 'DELIVERED')
                        ->whereDate('created_at', $selectedDate);
                })
                ->count();
            // Pending shipments: shipments created on the selected date that are still pending
            $data['pending_shipments'] = (clone $shipments)
                ->whereDate('created_at', $selectedDate)
                ->where('status', 'OFD')
                ->count();
        }

        // Add the selected date to response for frontend reference
        $data['selected_date'] = $selectedDate->format('Y-m-d');

        return sendResponse("Daily Summary for " . $selectedDate->format('Y-m-d'), $data);
    }
}
