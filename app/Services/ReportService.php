<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Storage;

class ReportService
{
    public static function generateDailyReport(array $payload = [])
    {
        $reportType = $payload['report_type'] ?? 'daily';
        $format = $payload['format'] ?? 'pdf';
        $shipments = Shipment::whereDate('created_at', now())->get();
        $content = "Daily Report for " . now()->format('Y-m-d') . "\n\n";
        $content .= "Total Shipments: " . $shipments->count() . "\n\n";
        foreach ($shipments as $shipment) {
            $content .= "Shipment #{$shipment->id}: \n";
            $content .= "Status: {$shipment->status}\n";
            $content .= "Amount: {$shipment->total_cod}\n\n";
        }
        $filename = "daily_report_" . now()->format('Y-m-d_H-i-s') . ".txt";
        Storage::put("reports/{$filename}", $content);
        return $filename;
    }
}
