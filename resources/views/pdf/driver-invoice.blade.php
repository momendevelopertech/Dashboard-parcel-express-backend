<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Driver Invoice | فاتورة المندوب</title>

    <style>
        @page {
            margin: 15mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
        }

        /* Header */
        .header {
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 3px solid #1b3884;
            padding-bottom: 15px;
        }

        .header table {
            width: 100%;
            border: none;
        }

        .header td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .logo {
            height: 70px;
            margin-bottom: 16px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1b3884;
            margin: 5px 0 2px 0;
        }

        .company-tagline {
            font-size: 9px;
            color: #666;
        }

        .invoice-title-box {
            display: block;
            padding-bottom: 50px;
        }

        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            color: #1b3884;
            margin: 0 0 5px 0;
        }

        .invoice-subtitle {
            font-size: 20px;
            font-weight: bold;
            color: #eb702d;
            margin: 0 0 10px 0;
        }

        .invoice-meta {
            font-size: 10px;
            color: #666;
            margin-top: 6px;
        }

        .invoice-number {
            font-size: 12px;
            color: #1b3884;
            font-weight: bold;
            margin-top: 4px;
        }

        .driver-name-box {
            font-size: 11px;
            color: #666;
            margin-top: 8px;
        }

        .driver-name-value {
            font-size: 22px;
            font-weight: bold;
            color: #1b3884;
            margin-top: 3px;
        }

        /* Section Headers */
        .section-header {
            background: #f8f9fa;
            border-left: 4px solid #eb702d;
            padding: 10px 15px;
            margin: 20px 0 12px 0;
        }

        .section-header table {
            width: 100%;
            border: none;
        }

        .section-header td {
            border: none;
            padding: 0;
        }

        .section-title-en {
            font-size: 14px;
            font-weight: bold;
            color: #1b3884;
        }

        .section-title-ar {
            font-size: 13px;
            font-weight: bold;
            color: #eb702d;
            text-align: right;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        /* Summary Table */
        .summary-table {
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .summary-table thead th {
            background: #1b3884;
            color: #fff;
            padding: 10px 12px;
            font-weight: bold;
            font-size: 11px;
            text-align: left;
            border: none;
        }

        .summary-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-table tbody tr:last-child td {
            border-bottom: none;
        }

        .summary-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .metric-label {
            font-size: 11px;
            color: #2c3e50;
            font-weight: 500;
        }

        .metric-value {
            font-size: 11px;
            font-weight: bold;
            color: #1b3884;
        }

        .subtotal-row {
            background: #f0f4f8 !important;
        }

        .subtotal-row td {
            font-weight: bold;
            padding-top: 12px !important;
            padding-bottom: 12px !important;
        }

        .total-row {
            background: #1b3884 !important;
        }

        .total-row td {
            padding: 13px 12px !important;
            font-size: 12px;
            font-weight: bold;
            border-bottom: none !important;
            color: #fff;
        }

        .total-row .metric-label,
        .total-row .metric-value {
            color: #fff;
        }

        .negative-amount {
            color: #dc3545;
        }

        .currency {
            font-weight: bold;
            color: #eb702d;
        }

        /* Shipment Tables */
        .shipment-table {
            border: 1px solid #e0e0e0;
            page-break-inside: auto;
        }

        .shipment-table thead {
            display: table-header-group;
            background: #1b3884;
        }

        .shipment-table thead th {
            background: #1b3884;
            color: #fff;
            padding: 10px 8px;
            font-weight: bold;
            font-size: 10px;
            text-align: left;
            border: none;
        }

        .shipment-table tbody {
            display: table-row-group;
        }

        .shipment-table tbody td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 10px;
        }

        .shipment-table tbody tr {
            page-break-inside: avoid;
        }

        .shipment-table tbody tr:last-child td {
            border-bottom: none;
        }

        .shipment-table tbody tr:nth-child(odd) {
            background: #fff;
        }

        .shipment-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .th-bilingual {
            line-height: 1.3;
        }

        .th-en {
            display: block;
            font-weight: bold;
        }

        .th-ar {
            display: block;
            font-size: 9px;
            opacity: 0.9;
            margin-top: 2px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .empty-state {
            color: #999;
            font-style: italic;
            padding: 18px !important;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 18px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #666;
            line-height: 1.7;
        }

        .footer-company {
            font-weight: bold;
            color: #1b3884;
            font-size: 11px;
            margin-bottom: 4px;
        }

        /* Page Break Utilities */
        .page-break {
            page-break-before: always;
        }

        .page-break-after {
            page-break-after: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Table Section Wrapper */
        .table-section {
            page-break-before: always;
        }
    </style>
</head>

<body>
<div class="container">

    <!-- FIRST PAGE: HEADER + SUMMARY + FOOTER -->
    <div>
        <!-- HEADER -->
        <div class="header">
            <div style="width: 35%;">
                <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo">
                <div class="invoice-number">Invoice #: INV-2024-XXXX</div>
                <div class="invoice-meta">Generated: {{ $date ?? date('Y-m-d') }}</div>
                <div class="invoice-meta">From: {{ $from }}</div>
                <div class="invoice-meta">To: {{ $to }}</div>
            </div>
            <div style="width: 65%; text-align: right;">
                <div class="invoice-title-box">
                    <span class="invoice-title">DRIVER INVOICE</span>
                    <span>&nbsp;|&nbsp;</span>
                    <span class="invoice-subtitle">فاتورة المندوب</span>
                </div>
                <div class="driver-name-value">{{ $driver->name ?? 'Driver Name' }}</div>
            </div>
        </div>

        <!-- SUMMARY SECTION -->
        <div class="section-header">
            <table>
                <tr>
                    <td class="section-title-en">Financial Summary</td>
                    <td class="section-title-ar">الملخص المالي</td>
                </tr>
            </table>
        </div>

        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description / الوصف</th>
                    <th style="width: 15%; text-align: center;">Count / العدد</th>
                    <th style="width: 35%; text-align: right;">Amount (OMR) / المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="metric-label">Pickup Bonus / مكافأة الاستلام</span>
                    </td>
                    <td class="text-center">
                        <span class="metric-value">{{ count($pickup_shipments ?? []) }}</span>
                    </td>
                    <td class="text-right">
                        <span class="metric-value currency">{{ number_format($totalPickupBonus ?? 0, 3) }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="metric-label">Delivery Bonus / مكافأة التوصيل</span>
                    </td>
                    <td class="text-center">
                        <span class="metric-value">{{ count($deliver_shipments ?? []) }}</span>
                    </td>
                    <td class="text-right">
                        <span class="metric-value currency">{{ number_format($totalDeliveryBonus ?? 0, 3) }}</span>
                    </td>
                </tr>
                <tr class="subtotal-row">
                    <td>
                        <span class="metric-label"><strong>Subtotal / المجموع الفرعي</strong></span>
                    </td>
                    <td class="text-center">
                        <span class="metric-value">{{ count($pickup_shipments ?? []) + count($deliver_shipments ?? []) }}</span>
                    </td>
                    <td class="text-right">
                        <span class="metric-value">{{ number_format(($totalPickupBonus ?? 0) + ($totalDeliveryBonus ?? 0), 3) }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="metric-label">Penalties / الغرامات</span>
                    </td>
                    <td class="text-center">—</td>
                    <td class="text-right">
                        <span class="metric-value negative-amount">({{ number_format($total_penalities ?? 0, 3) }})</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="metric-label">Advances / السلف</span>
                    </td>
                    <td class="text-center">—</td>
                    <td class="text-right">
                        <span class="metric-value negative-amount">({{ number_format($total_advances ?? 0, 3) }})</span>
                    </td>
                </tr>
                <tr class="total-row">
                    <td>
                        <span class="metric-label">TOTAL AMOUNT / المبلغ الإجمالي</span>
                    </td>
                    <td class="text-center">—</td>
                    <td class="text-right">
                        <span class="metric-value" style="font-size: 14px;">{{ number_format($amount ?? 0, 3) }} OMR</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- FOOTER -->
        <div class="footer">
            <div class="footer-company">PARCEL EXPRESS</div>
            Enterprise Logistics & Transportation<br>
            Sultanate of Oman<br>
            Email: support@parcelexpress.om | Tel: +968 XXXX XXXX
        </div>
    </div>

    <!-- SECOND PAGE: PICKUP SHIPMENTS -->
    <div class="table-section">
        <div class="section-header">
            <table>
                <tr>
                    <td class="section-title-en">Pickup Shipments</td>
                    <td class="section-title-ar">شحنات الاستلام</td>
                </tr>
            </table>
        </div>

        <table class="shipment-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">
                        <div class="th-bilingual">
                            <span class="th-en">#</span>
                        </div>
                    </th>
                    <th style="width: 30%;">
                        <div class="th-bilingual">
                            <span class="th-en">Tracking No</span>
                            <span class="th-ar">رقم التتبع</span>
                        </div>
                    </th>
                    <th style="width: 20%;">
                        <div class="th-bilingual">
                            <span class="th-en">Bonus (OMR)</span>
                            <span class="th-ar">المكافأة</span>
                        </div>
                    </th>
                    <th style="width: 22.5%;">
                        <div class="th-bilingual">
                            <span class="th-en">Picked At</span>
                            <span class="th-ar">تاريخ الاستلام</span>
                        </div>
                    </th>
                    <th style="width: 22.5%;">
                        <div class="th-bilingual">
                            <span class="th-en">Delivered At</span>
                            <span class="th-ar">تاريخ التوصيل</span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
            @forelse($pickup_shipments ?? [] as $index => $shipment)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $shipment->tracking_no ?? '—' }}</td>
                    <td class="currency">{{ getDriverBonus($driver->id ?? 0, $shipment->id ?? 0, 'pickup') }}</td>
                    <td>{{ optional($shipment->picked_at)->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ optional($shipment->delivered_at)->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center empty-state">
                        No pickup shipments found | لا توجد شحنات استلام
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <!-- THIRD PAGE: DELIVERED SHIPMENTS -->
    <div class="table-section">
        <div class="section-header">
            <table>
                <tr>
                    <td class="section-title-en">Delivered Shipments</td>
                    <td class="section-title-ar">شحنات التوصيل</td>
                </tr>
            </table>
        </div>

        <table class="shipment-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">
                        <div class="th-bilingual">
                            <span class="th-en">#</span>
                        </div>
                    </th>
                    <th style="width: 35%;">
                        <div class="th-bilingual">
                            <span class="th-en">Tracking No</span>
                            <span class="th-ar">رقم التتبع</span>
                        </div>
                    </th>
                    <th style="width: 25%;">
                        <div class="th-bilingual">
                            <span class="th-en">Bonus (OMR)</span>
                            <span class="th-ar">المكافأة</span>
                        </div>
                    </th>
                    <th style="width: 30%;">
                        <div class="th-bilingual">
                            <span class="th-en">Delivered At</span>
                            <span class="th-ar">تاريخ التوصيل</span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
            @forelse($deliver_shipments ?? [] as $index => $shipment)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $shipment->tracking_no ?? '—' }}</td>
                    <td class="currency">{{ getDriverBonus($driver->id ?? 0, $shipment->id ?? 0, 'delivery') }}</td>
                    <td>{{ optional($shipment->delivered_at)->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center empty-state">
                        No delivered shipments found | لا توجد شحنات مسلّمة
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
</body>
</html>