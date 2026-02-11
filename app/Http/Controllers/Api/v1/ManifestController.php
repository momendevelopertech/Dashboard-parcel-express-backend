<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Manifest;
use App\Models\DriverShipmentAssignment;
use App\Models\Shipment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ManifestController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'tz' => 'nullable|timezone',
        ]);
        $driverId = $request->driver_id;
        $tz = $request->input('tz', config('app.timezone', 'UTC'));
        if (!in_array($tz, \DateTimeZone::listIdentifiers())) {
            return sendResponse('Invalid timezone identifier.', [], false, [], 422);
        }
        $manifestSerial = $this->generateManifestSerial();
        $driver = User::findOrFail($driverId);
        
        // Convert dates to UTC range
        [$startUtc] = localDayToUtcRange($request->start_date, $tz);
        [, $endUtc] = localDayToUtcRange($request->end_date, $tz);

        // Build query
        $query = DriverShipmentAssignment::query()
            ->where('driver_id', $driverId)
            ->select('id', 'shipment_id', 'shipment_tracking_no', 'assigned_at', 'status')
            ->orderByDesc('assigned_at')
            ->where('assigned_at', '>=', $startUtc)
            ->where('assigned_at', '<=', $endUtc)
            ->with([
                'shipment:id,consignee_id,tracking_no,value,total_cod,delivery_fee,payment_type,status,customer_name',
                'shipment.consignee:id,governorate_id,state_id,place_id,cellphone,alternatePhone,streetAddress,longitude,latitude',
                'shipment.consignee.governorate:id,en_name,ar_name,lat,lng',
                'shipment.consignee.state:id,en_name,ar_name,lat,lng',
                'shipment.consignee.place:id,en_name,ar_name,lat,lng',
            ]);
        $assignedShipments = $query->get();
        $shipments = $assignedShipments->pluck('shipment');
        $totalWeight = $assignedShipments->sum(function ($assignment) {
            return $assignment->shipment->shipment_information->weight ?? 0;
        });
        $manifest = Manifest::create([
            'manifest_serial' => $manifestSerial,
            'driver_id' => $driver->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_shipments' => $shipments->count(),
            'total_weight' => $totalWeight,
            'total_value' => $shipments->sum('value'),
        ]);

        $manifest->shipments()->attach($shipments);
         activityLog("manifest generated", "Manifest generated for driver #{$driver->username}");
        return sendResponse(
            'Manifest generated successfully',
            [
                'manifest' => $manifest,
                'shipments' => $shipments
            ]
        );
    }

    public function show($id)
    {
        $manifest = Manifest::with(['driver', 'shipments'])->findOrFail($id);
        return sendResponse(
            'Manifest details retrieved successfully',
            $manifest
        );
    }
    public function export($id)
    {
        try {
            $manifest = Manifest::with(['driver'])->findOrFail($id);
            if (!$manifest->driver) {
                $driver = Driver::find($manifest->driver_id);
                if ($driver) {
                    $manifest->driver = $driver;
                    $manifest->driver->load('user');
                }
            }
            $pdf = Pdf::loadView('manifests.pdf', compact('manifest'));
            $filename = 'manifest_' . $manifest->manifest_serial . '.pdf';
            
            // Create manifests directory if it doesn't exist
            $manifestsPath = storage_path('app/manifests');
            if (!file_exists($manifestsPath)) {
                mkdir($manifestsPath, 0777, true);
            }
            
            // Save PDF to storage
            $pdfPath = $manifestsPath . '/' . $filename;
            $pdf->save($pdfPath);
            
            // Return the PDF directly
            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateManifestSerial()
    {
        $date = new \DateTime();
        $year = $date->format('y');
        $month = $date->format('m');
        $day = $date->format('d');
        $random = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return "MS{$year}{$month}{$day}-{$random}";
    }
}
