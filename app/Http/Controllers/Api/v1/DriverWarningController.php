<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


    use App\Http\Resources\GeneralResource;
    use App\Models\DriverWarning;
    use Exception;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;

class DriverWarningController extends Controller
{
    public function index()
    {
        $warnings = DriverWarning::query();
        $warnings->with(["shipment", "driver"]);
        if (request()->has('query') && request()->input('query')) {
            $query = request()->input('query');
            $warnings->whereHas('shipment', function ($q) use ($query) {
                $q->where('tracking_no', 'like', '%' . $query . '%');
            });
        }
        if (request()->has('driver_id') && request()->input('driver_id')) {
            $driverId = request()->input('driver_id');
            $warnings->where('driver_id', $driverId);
        }

        $warnings = $warnings->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Driver Warnings retrieved successfully.", new GeneralResource($warnings));
    }
}
