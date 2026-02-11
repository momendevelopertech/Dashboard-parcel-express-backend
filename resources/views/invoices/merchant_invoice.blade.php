<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merchant Invoice - {{ $invoice->invoice_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .invoice-info div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .invoice-details {
            margin-bottom: 30px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total {
            text-align: right;
            margin-top: 20px;
            font-size: 16px;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PARCEL EXPRESS</h1>
        <h2>Merchant Invoice</h2>
    </div>

    <div class="invoice-info">
        <div>
            <h3>Invoice Details:</h3>
            <p><strong>Invoice No:</strong> {{ $invoice->invoice_no }}</p>
            <p><strong>Status:</strong> {{ ucfirst($invoice->status) }}</p>
            <p><strong>Date:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
        </div>
        <div>
            <h3>Merchant Details:</h3>
            @if($invoice->owner && $invoice->owner->user)
                <p><strong>Name:</strong> {{ $invoice->owner->user->name }}</p>
                <p><strong>Email:</strong> {{ $invoice->owner->user->email }}</p>
            @endif
            @if($invoice->owner)
                <p><strong>Contact:</strong> {{ $invoice->owner->contact_no }}</p>
                <p><strong>Address:</strong> {{ $invoice->owner->address }}</p>
            @endif
        </div>
    </div>

    <div class="invoice-details">
        <h3>Shipment Details:</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Tracking No</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->invoice_shipments as $shipment)
                <tr>
                    <td>{{ $shipment->shipment_tracking_no }}</td>
                    <td>{{ ucfirst($shipment->status) }}</td>
                    <td>{{ $shipment->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="total">
        <p>Total Amount: {{getCurrency("en")}} {{ number_format($invoice->amount, 2) }}</p>
    </div>

    @if($invoice->notes)
    <div class="notes">
        <h3>Notes:</h3>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Generated on {{ $date }} | Parcel Express - Delivery Solutions</p>
    </div>
</body>
</html>
