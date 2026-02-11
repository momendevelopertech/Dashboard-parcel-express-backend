<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Merchant Settlement Invoice</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.6;
        }

        .container {
            padding: 32px 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px dashed #eee;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .arabic {
            direction: rtl;
            text-align: right;
        }

        .label {
            font-size: 11px;
            color: #666;
        }

        .divider {
            border-top: 1px dashed #e6e6e6;
            margin: 18px 0;
        }

        h1 {
            font-size: 22px;
            margin: 0;
        }

        h2 {
            font-size: 14px;
            margin: 0;
            font-weight: normal;
        }

        /* ORANGE TABLE HEADER */
        .table-header th {
            background: #ff5a00;
            color: #fff;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: none;
        }

        .card {
            padding: 6px 0;
        }

        .total-row td {
            border-bottom: none;
            font-weight: bold;
            font-size: 13px;
            padding-top: 14px;
        }

        .footer {
            margin-top: 40px;
            font-size: 10px;
            color: #777;
            line-height: 1.5;
            text-align: center;
        }

        .page-break {
            page-break-before: always;
        }

        .th-wrap {
            line-height: 1.2;
        }

        .th-en {
            display: block;
            font-size: 11px;
            font-weight: bold;
        }

        .th-ar {
            display: block;
            font-size: 10px;
            font-weight: normal;
            opacity: 0.9;
            margin-top: 2px;
            direction: rtl;
        }

        .invoice-meta {
            font-size: 10px;
            color: #666;
            margin-top: 6px;
        }
    </style>
</head>

<body>

    @php
        $codCollected = $shipments->sum(fn($s) => $s->value ?? 0);
        $deliveryFee = $shipments->sum(fn($s) => $s->fee_payer == 'merchant' ? $s->delivery_fee : 0);
        $deliveryRebate = $shipments->sum(fn($s) => $s->fee_payer === 'customer' ? $s->delivery_fee_discount ?? 0 : 0);
        $pickupDeposits = $pickup_tasks->sum(fn($p) => $p->amount ?? 0);
    @endphp

    <div class="container">

        <!-- HEADER -->
        <table>
            <tr>
                <td width="50%">
                    <img src="{{ public_path('images/logo.png') }}" style="height:40px;"><br>
                    <span class="label">Settlement Invoice</span>
                    <h1>Settlement Invoice</h1>
                </td>
                <td width="50%" class="arabic">
                    <h1>فاتورة تسوية</h1>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- META -->
        <table>
            <tr>
                <td width="50%">
                    <div class="label">Invoice Reference</div>
                    <strong>{{ $reference ?? '—' }}</strong><br><br>

                    <div class="label">Date</div>
                    <strong>{{ $date ?? '—' }}</strong><br><br>

                    <div class="label">From</div>
                    <strong>{{ $from->format('Y-m-d') ?? '—' }}</strong>

                    <div class="label">To</div>
                    <strong>{{ $to->format('Y-m-d') ?? '—' }}</strong>

                </td>

                <td width="50%" class="arabic">
                    <div class="label">رقم الفاتورة</div>
                    <strong>{{ $reference ?? '—' }}</strong><br><br>

                    <div class="label">التاريخ</div>
                    <strong>{{ $date ?? '—' }}</strong>

                    <div class="label">من</div>
                    <strong>{{ $from->format('Y-m-d') ?? '—' }}</strong>

                    <div class="label">الي</div>
                    <strong>{{ $to->format('Y-m-d') ?? '—' }}</strong>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- MERCHANT -->
        <table>
            <tr>
                <td width="50%">
                    <div class="label">Billed To</div>
                    <strong>{{ $merchant_name ?? '—' }}</strong><br>
                    {{ $merchant_address ?? '—' }}<br>
                    {{ $merchant_contact ?? '—' }}
                </td>

                <td width="50%" class="arabic">
                    <div class="label">الفاتورة إلى</div>
                    <strong>{{ $merchant_name ?? '—' }}</strong><br>
                    {{ $merchant_address ?? '—' }}<br>
                    {{ $merchant_contact ?? '—' }}
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- SUMMARY -->
        <table>
            <tr>
                <td width="50%">
                    <div class="label">Settlement Summary</div><br>

                    COD Collected<br>
                    <strong>+ {{ number_format($codCollected, 3) }} OMR</strong><br><br>

                    Pickup Deposits<br>
                    <strong>+ {{ number_format($pickupDeposits, 3) }} OMR</strong><br><br>

                    Delivery Fee<br>
                    <strong>- {{ number_format($deliveryFee, 3) }} OMR</strong><br><br>

                    @if ($deliveryRebate != 0)
                        Delivery Rebate<br>
                        <strong>+ {{ number_format($deliveryRebate, 3) }} OMR</strong>
                    @endif
                </td>

                <td width="50%" class="arabic">
                    <div class="label">ملخص التسوية</div><br>

                    تحصيل الدفع عند الاستلام<br>
                    <strong>+ {{ number_format($codCollected, 3) }} ر.ع</strong><br><br>

                    إيداعات الاستلام<br>
                    <strong>+ {{ number_format($pickupDeposits, 3) }} ر.ع</strong><br><br>

                    رسوم التوصيل<br>
                    <strong>- {{ number_format($deliveryFee, 3) }} ر.ع</strong><br><br>

                    @if ($deliveryRebate != 0)
                        خصم التوصيل<br>
                        <strong>+ {{ number_format($deliveryRebate, 3) }} ر.ع</strong>
                    @endif
                </td>
            </tr>
        </table>

    </div>

    <!-- PAGE 2 -->
    <div class="container page-break">

        <table>
            <thead class="table-header">
                <tr>
                    <th class="text-center">
                        <span class="th-wrap">
                            <span class="th-en">#</span>
                            <span class="th-ar">#</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Tracking No</span>
                            <span class="th-ar">رقم التتبع</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Pickup Ref</span>
                            <span class="th-ar">مرجع الاستلام</span>
                        </span>
                    </th>

                    @if ($deliveryRebate != 0)
                        <th class="text-right">
                            <span class="th-wrap">
                                <span class="th-en">Delivery Rebate</span>
                                <span class="th-ar">خصم التوصيل</span>
                            </span>
                        </th>
                    @endif

                    <th class="text-right">
                        <span class="th-wrap">
                            <span class="th-en">Delivery Fee</span>
                            <span class="th-ar">رسوم التوصيل</span>
                        </span>
                    </th>

                    <th class="text-right">
                        <span class="th-wrap">
                            <span class="th-en">COD Collected</span>
                            <span class="th-ar">تحصيل الدفع عند الاستلام</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Delivered At</span>
                            <span class="th-ar">تاريخ التوصيل</span>
                        </span>
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse($shipments as $index => $shipment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $shipment->tracking_no ?? '—' }}</td>
                        <td>{{ optional($shipment->merchant_pickup_shipment?->pickup_task)->ref ?? '—' }}</td>
                        @if ($deliveryRebate != 0)
                            <td class="text-right">
                                {{ $shipment->fee_payer === 'customer' ? number_format($shipment->delivery_fee_discount ?? 0, 2) : '—' }}
                            </td>
                        @endif
                        <td class="text-right">
                            {{ number_format($shipment->fee_payer == 'merchant' ? -$shipment->delivery_fee : 0, 2) }}
                        </td>
                        <td class="text-right">{{ number_format($shipment->value ?? 0, 2) }}</td>
                        <td>{{ isset($shipment->delivered_at) ? $shipment->delivered_at->format('Y-m-d') : '—' }}</td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $deliveryRebate != 0 ? 7 : 6 }}" style="text-align:center;color:#777;">
                            No shipments | <span class="arabic">لا توجد شحنات</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>


        <h3>Pickup Transactions | <span class="arabic">معاملات الاستلام</span></h3>
        <table>
            <thead class="table-header">
                <tr>
                    <th class="text-center">
                        <span class="th-wrap">
                            <span class="th-en">#</span>
                            <span class="th-ar">#</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Pickup Ref</span>
                            <span class="th-ar">مرجع الاستلام</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Manifest</span>
                            <span class="th-ar">رقم البيان</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Shipments</span>
                            <span class="th-ar">عدد الشحنات</span>
                        </span>
                    </th>

                    <th class="text-right">
                        <span class="th-wrap">
                            <span class="th-en">Total</span>
                            <span class="th-ar">المجموع</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Status</span>
                            <span class="th-ar">الحالة</span>
                        </span>
                    </th>

                    <th>
                        <span class="th-wrap">
                            <span class="th-en">Date</span>
                            <span class="th-ar">التاريخ</span>
                        </span>
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse($pickup_tasks as $index => $transaction)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $transaction->pickupTask->ref ?? '—' }}</td>
                        <td>{{ $transaction->pickupTask->manifest_id ?? '—' }}</td>
                        <td>{{ $transaction->pickupTask->no_of_shipments ?? '—' }}</td>
                        <td class="text-right"><strong>{{ number_format($transaction->amount ?? 0, 2) }}</strong></td>
                        <td>{{ ucfirst($transaction->status ?? '—') }}</td>

                        <td>{{ optional($transaction->created_at)->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;color:#777;">
                            No pickup transactions | <span class="arabic">لا توجد معاملات</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            <strong>Parcel Express</strong><br>
            Enterprise Logistics & Transportation<br>
            Sultanate of Oman<br>
            support@parcelexpress.om
        </div>

    </div>

</body>

</html>
