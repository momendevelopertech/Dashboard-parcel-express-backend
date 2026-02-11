<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        /* Modern Base Styles */
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #f8fafc;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        /* Header Section */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f1f5f9;
        }

        .company-info h1 {
            color: #1e293b;
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .company-info p {
            color: #64748b;
            margin: 0;
            line-height: 1.6;
            font-size: 14px;
        }

        .invoice-details {
            text-align: right;
        }

        .invoice-details h2 {
            margin: 0 0 8px 0;
            color: #3b82f6;
            font-size: 22px;
            font-weight: 600;
        }

        .invoice-details p {
            color: #64748b;
            margin: 4px 0;
            font-size: 14px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            font-size: 14px;
            margin-top: 12px;
            border: 1px solid #bae6fd;
        }

        /* Details Section */
        .details-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 24px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .merchant-info {
            padding-right: 20px;
        }

        .info-label {
            color: #94a3b8;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 15px;
        }

        /* Table Styles */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 28px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .invoice-table thead {
            background: #f1f5f9;
        }

        .invoice-table th {
            color: #64748b;
            padding: 14px 20px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .invoice-table td {
            padding: 16px 20px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .invoice-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Totals Section */
        .totals-section {
            margin-top: 32px;
            padding: 24px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 15px;
            color: #475569;
        }

        .grand-total {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            padding-top: 12px;
            border-top: 2px solid #e2e8f0;
            margin-top: 12px;
        }

        /* Footer Note */
        .footer-note {
            margin-top: 32px;
            padding-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            border-top: 1px solid #f1f5f9;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            body {
                padding: 20px;
            }

            .container {
                padding: 24px;
            }

            .invoice-header {
                flex-direction: column;
                gap: 20px;
            }

            .invoice-details {
                text-align: left;
            }

            .details-row {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .container {
                box-shadow: none;
                border: none;
                padding: 0;
            }

            .status-badge {
                background: #f1f5f9 !important;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <h1>Parcel Express</h1>
                <p>Muscat<br> Oman</p>
            </div>
            <div class="invoice-details">
                <h2>INVOICE #{{ $data['invoice']->id }}</h2>
                <p>Date: {{ $data['invoice']->created_at ?? date('Y-m-d') }}</p>
                <div class="status-badge">
                    Status: {{ strtoupper($data['invoice']->status) }}
                </div>
            </div>
        </div>

        <!-- Driver Details -->
        <div class="details-row">
            <div class="merchant-info">
                <div class="info-label">Driver Details</div>
                <div class="info-value">{{ $data['user']->name }}</div>
                <div class="info-value">{{ $data['user']->email }}</div>
                <div class="info-value">Driver ID: {{ $data['user']->id }}</div>
            </div>
        </div>

        <!-- Delivery Shipments Table -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Delivery Date</th>
                    <th>Delivery Fee</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['invoice']->invoice_shipments as $invoiceShipment)
                <tr>
                    <td>{{ $invoiceShipment->shipment_tracking_no }}</td>
                    <td>{{ $invoiceShipment->created_at->format('Y-m-d') }}</td>
                    <td>{{getCurrency("en")}}/{{getCurrency("ar")}} {{ number_format($invoiceShipment->shipment_finance->driver_delivery_fee, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-line">
                <span>Total Deliveries:</span>
                <span>{{ count($data['invoice']->invoice_shipments) }}</span>
            </div>
            <div class="total-line">
                <span>Subtotal:</span>
                <span>{{getCurrency("en")}}/{{getCurrency("ar")}} {{ number_format($data['invoice']->invoice_shipments->sum(function($item) {
                    return $item->shipment_finance->driver_delivery_fee;
                }), 2) }}</span>
            </div>
            <div class="total-line grand-total">
                <span>TOTAL PAYABLE:</span>
                <span>{{getCurrency("en")}}/{{getCurrency("ar")}} {{ number_format($data['invoice']->invoice_shipments->sum(function($item) {
                    return $item->shipment_finance->driver_delivery_fee;
                }), 2) }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            <p>** Note: This is a system-generated invoice. Please contact accounts department for any discrepancies.</p>
        </div>
    </div>
</body>

</html>
