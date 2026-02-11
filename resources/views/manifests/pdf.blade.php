<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Parcel Express Manifest</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            padding: 20px;
            background-color: #ffffff;
            border-bottom: 2px solid #3490dc;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
            color: #3490dc;
        }
        .manifest-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .stat-card h4 {
            color: #3490dc;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
        }
        .shipments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }
        .shipments-table th {
            background-color: #3490dc;
            color: white;
            text-align: left;
            padding: 12px;
            font-weight: 600;
        }
        .shipments-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px;
            text-align: left;
        }
        .shipments-table tr:last-child td {
            border-bottom: none;
        }
        .shipments-table th:first-child,
        .shipments-table td:first-child {
            border-radius: 8px 0 0 8px;
        }
        .shipments-table th:last-child,
        .shipments-table td:last-child {
            border-radius: 0 8px 8px 0;
        }
        .section-title {
            font-size: 18px;
            color: #3490dc;
            margin: 20px 0;
            padding: 10px 0;
            border-bottom: 2px solid #e2e8f0;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #718096;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Parcel Express Manifest</h1>
            <p>Manifest Serial: {{ $manifest->manifest_serial }}</p>
            <p>Generated on: {{ \Carbon\Carbon::parse($manifest->created_at)->format('Y-m-d H:i') }}</p>
        </div>

        <div class="manifest-info">
            <div>
                <h3>Manifest Information</h3>
                <p><strong>Driver:</strong> {{ optional($manifest->driver)->name ?? 'N/A' }}</p>
                <p><strong>Period:</strong> 
                    {{ \Carbon\Carbon::parse($manifest->start_date)->format('Y-m-d') }} to 
                    {{ \Carbon\Carbon::parse($manifest->end_date)->format('Y-m-d') }}</p>
            </div>
            <div>
                <h3>Total Statistics</h3>
                <p><strong>Total Shipments:</strong> {{ $manifest->total_shipments }}</p>
                <p><strong>Total Weight:</strong> {{ number_format($manifest->total_weight, 1) }} kg</p>
                <p><strong>Total Value:</strong> {{ number_format($manifest->total_value, 2) }} EGP</p>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h4>Total Shipments</h4>
                <div class="value">{{ $manifest->total_shipments }}</div>
            </div>
            <div class="stat-card">
                <h4>Total Weight</h4>
                <div class="value">{{ number_format($manifest->total_weight, 1) }} kg</div>
            </div>
            <div class="stat-card">
                <h4>Total Value</h4>
                <div class="value">{{ number_format($manifest->total_value, 2) }} EGP</div>
            </div>
        </div>

        <div class="section-title">Shipments List</div>
        <table class="shipments-table">
            <thead>
                <tr>
                    <th>Tracking Number</th>
                    <th>Weight (kg)</th>
                    <th>Amount (EGP)</th>
                    <th>Delivery Fee (EGP)</th>
                    <th>Payment Type</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                @if($manifest->shipments->isEmpty())
                    <tr>
                        <td colspan="7" class="no-data">No shipments found</td>
                    </tr>
                @else
                    @foreach($manifest->shipments as $shipment)
                        <tr>
                            <td>{{ $shipment->tracking_no }}</td>
                            <td>{{ number_format($shipment->shipment_information->weight ?? 0, 1) }} kg</td>
                            <td>{{ number_format($shipment->amount ?? 0, 2) }} EGP</td>
                            <td>{{ number_format($shipment->delivery_fee ?? 0, 2) }} EGP</td>
                            <td>{{ $shipment->payment_type ?? 'N/A' }}</td>
                            <td>{{ $shipment->status ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($shipment->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</body>
</html>
