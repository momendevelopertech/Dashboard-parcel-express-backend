<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Parcel Express - Reverse Waybill</title>

    <style>
        @media print {
            @page {
                size: 100mm 150mm;
                margin: 0;
            }

            body,
            html {
                width: 100mm;
                height: auto;
                margin: 0;
                padding: 0;
            }

            .waybill-wrapper {
                page-break-after: always;
                break-after: page;
            }

            .waybill-wrapper:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .container {
                width: calc(98mm - 2px);
                height: calc(148mm - 2px);
                margin: 0 1mm 1mm 1mm;
                border: 1px solid #000;
                display: grid;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            font-weight: bold;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container {
            overflow: hidden;
            width: calc(98mm - 2px);
            height: calc(148mm - 2px);
            margin: 1mm 1mm 0 1mm;
            border: 1px solid #000;
            position: relative;
            display: grid;
            grid-template-rows: 20mm 12.9mm 50.5mm 13mm 24.5mm 20mm 9mm;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(0);
            font-size: 80px;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.1);
            z-index: -1;
            pointer-events: none;
            user-select: none;
        }

        .hidden {
            display: none;
        }

        .squared {
            border: 1px solid #000;
            padding-inline: 10px;
        }

        section {
            padding: 5px;
            border-top: 1px solid #000;
        }

        section:first-child {
            border-top: none;
        }

        .section-title-container {
            display: inline-block;
            color: #000;
            margin-bottom: 2mm;
            white-space: nowrap;
        }

        section>.section-title-container {
            color: #000;
            margin-bottom: 3mm;

        }

        .normal {
            font-weight: normal;
        }


        .gap-3px {
            gap: 3px !important;
        }

        .gap-10px {
            gap: 10px !important;
        }

        .flex {
            display: flex;
        }

        .flex-column {
            flex-direction: column;
        }

        .flex-wrap {
            flex-wrap: wrap;
        }

        .flex-grow {
            flex-grow: 1;
        }

        .flex-centered {
            justify-content: center;
            align-items: center;
        }

        .space-between {
            justify-content: space-between;
        }

        .space-around {
            justify-content: space-around;
        }

        .grid {
            display: grid;
        }

        .horizontally-separated,
        .vertically-separated {
            gap: 10px;
        }

        .horizontally-separated>div,
        .vertically-separated>div {
            position: relative;
        }

        .horizontally-separated>div::before,
        .vertically-separated>div::before {
            content: "";
            position: absolute;
            background: #000;
        }

        .horizontally-separated>div:last-child::before,
        .vertically-separated>div:last-child::before {
            display: none;
        }

        .horizontally-separated>div::before {
            top: 0;
            right: -5px;
            width: .4px;
            height: 100%;
        }

        .vertically-separated>div::before {
            left: 0;
            bottom: -5px;
            width: 100%;
            height: .4px;
        }

        .grid-tabular {
            grid-template-columns: auto 1fr auto;
        }

        .grid-3-3-2 {
            grid-template-columns: 3fr 3fr 2fr;
        }

        p.data-content {
            margin: 0;
            padding: 0;
            font-size: 16px;
            text-align: center;
            line-height: .9;
        }

        .tracking-number-section {
            display: flex;
            align-items: center;
            padding: 5px 10px 2px 10px;
            gap: 20px;
            height: 18mm;
        }

        .tracking-number-section.footer {
            flex-direction: row-reverse;
        }

        .tracking-number {
            margin: 0;
        }

        .last-four-digits-container {
            width: 85px;
            text-align: center;
        }

        .last-four-digits {
            display: inline-block;
            border: 1px solid #000;
            padding: 5px;
            font-size: 35px;
        }

        .header-logo {
            height: 60px;
        }

        .barcode-container {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .barcode-container img {
            max-width: 100%;
            height: 40px;
            width: auto;
            margin-bottom: 3px;
        }

        .cod-protected {}

        .cod-protected .section-title-container {}

        .cod-protected .cod-value {}
    </style>
</head>

<body>
    @php
        $currencyEn = getCurrency('en');
        $currencyAr = getCurrency('ar');

        // In Reverse Shipment:
        // Sender is the Customer (who has the item)
        // Receiver is the Merchant (who gets the item back)

        $customerName = $shipment->customer_name ?? ($shipment->consignee->name ?? '');
        $customerPhone = $shipment->customer_phone ?? ($shipment->consignee->cellphone ?? '');
        $customerAddress = $shipment->customer_address ?? ($shipment->consignee->streetAddress ?? '');

        // Merchant Info (Receiver)
        $merchant = $shipment->merchant; // User model
        $merchantName = $merchant->name ?? '';
        // Use receiverAddress if available (Merchant's address)
$merchantAddressObj = $shipment->receiverAddress;

$items = $shipment->parentShipment->shipment_items ?? collect([]);
$shipmentInfo = $shipment->parentShipment->shipment_information ?? null;

$trackingNo = $shipment->tracking_no;
$barcodeImage = DNS1D::getBarcodePNG($trackingNo, 'C128', 2.5, 55);
$timestamp = now()->format('Y-m-d H:i:s');
    @endphp

    <div class="waybill-wrapper">
        <div class="container">
            <div class="watermark">RETURN</div>

            {{-- Header with Logo and Barcode --}}
            <section class="header tracking-number-section">
                <div class="header-icon">
                    <img src="{{ asset('images/logo.jpg') }}" class="header-logo" alt="Parcel Express Logo">
                </div>
                <div class="barcode-container">
                    <img class="barcode-img" src="data:image/png;base64,{{ $barcodeImage }}" />
                    <p class="tracking-number">{{ $trackingNo }}</p>
                </div>
            </section>

            {{-- Customer Information (Sender) --}}
            <section class="flex gap-10px">
                <span class="section-title-container">👤 Customer (Sender) | العميل</span>
                <p class="data-content flex-grow flex flex-centered">
                    {{ $customerName }} - {{ $customerPhone }}
                </p>
                @if ($customerAddress)
                    <div style="font-size: 10px; margin-top:5px; text-align:center; width:100%;">
                        {{ Str::limit($customerAddress, 60) }}
                    </div>
                @endif
            </section>

            {{-- Merchant Information (Receiver) --}}
            <section>
                <span class="section-title-container">🏢 Merchant (Receiver) | التاجر المستلم</span>
                <div class="grid vertically-separated" style="grid-template-rows: 12.5mm 12.5mm auto;">
                    <div class="flex horizontally-separated">
                        {{-- Merchant Name --}}
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Name | الاسم</span>
                            <p class="data-content flex-grow flex flex-centered">
                                {{ $merchantName }}
                            </p>
                        </div>
                        {{-- Merchant Phone --}}
                        <div class="flex flex-column" style="flex: 2;">
                            <span class="section-title-container">Phone | الهاتف</span>
                            <p class="data-content flex-grow flex flex-centered">
                                {{ $merchant->phone ?? '' }}
                            </p>
                        </div>
                    </div>

                    {{-- Merchant Region --}}
                    <div class="flex horizontally-separated">
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Governorate | المحافظة</span>
                            <p class="data-content flex-grow flex flex-centered">
                                {{ $merchantAddressObj->governorate->ar_name ?? '' }}
                            </p>
                        </div>
                        <div class="flex flex-column" style="flex: 4;">
                            <span class="section-title-container">State | الولاية</span>
                            <p class="data-content flex-grow flex flex-centered">
                                {{ $merchantAddressObj->state->ar_name ?? '' }}
                            </p>
                        </div>
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Place | المكان</span>
                            <p class="data-content flex-grow flex flex-centered">
                                {{ $merchantAddressObj->place->ar_name ?? '' }}
                            </p>
                        </div>
                    </div>

                    {{-- Merchant Address --}}
                    <div>
                        <span class="section-title-container">Address | العنوان</span>
                        <p class="data-content">
                            {{ $merchantAddressObj->streetAddress ?? '' }}
                        </p>
                    </div>
                </div>
            </section>

            {{-- Pricing / Fees (Usually N/A for driver collecting from customer unless specified) --}}
            {{-- We can keep Delivery Fees / COD section if relevant, or hide it if Reverse doesn't have COD --}}
            <section class="flex horizontally-separated">
                <div class="flex flex-column">
                    <span class="section-title-container">Reason | السبب</span>
                    <div class="flex">
                        <span style="padding: 5px;">Returns / استرجاع</span>
                    </div>
                </div>
                <div style="flex: 5;">
                    {{-- Leaving blank for now or could put Original Tracking No --}}
                    <span class="section-title-container">Original Tracking | الشحنة الأصلية</span>
                    <p class="data-content">
                        {{ $shipment->original_tracking_no ?? 'N/A' }}
                    </p>
                </div>
            </section>

            {{-- Items --}}
            <section>
                <div class="grid grid-3-3-2 horizontally-separated" style="grid-template-columns: 5fr 5fr 3fr;">
                    <div>
                        <span class="section-title-container">ITEMS | المنتجات</span>
                        <div class="normal">
                            @if ($items->count() > 0)
                                @foreach ($items as $item)
                                    <div>
                                        {{ $item->name ?? '' }} — Qty: {{ $item->quantity ?? '0' }}
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center">--</div>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-column vertically-separated" style="max-height: 100%;">
                        <div class="flex flex-column" style="flex: 2">
                            <span class="section-title-container">Category | التصنيف</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                REVERSE / استرجاع
                            </span>
                        </div>
                        <div class="flex flex-column" style="flex: 1">
                            <span class="section-title-container">PCS | القطع</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                {{ $items->count() }}
                            </span>
                        </div>
                        <div class="flex flex-column" style="flex: 1">
                            <span class="section-title-container">QTY | الكمية</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                {{ $items->sum('quantity') }}
                            </span>
                        </div>
                    </div>

                    {{-- Weight Dimensions --}}
                    <div>
                        <div class="section-title-container">
                            <span>D&W</span>
                            <span>الأبعاد والوزن</span>
                        </div>
                        <div class="grid grid-tabular gap-3px">
                            <span>H:</span>
                            <span class="text-center">{{ $shipmentInfo ? ($shipmentInfo->height ?: '') : '' }}</span>
                            <span>cm</span>

                            <span>W:</span>
                            <span class="text-center">{{ $shipmentInfo ? ($shipmentInfo->width ?: '') : '' }}</span>
                            <span>cm</span>

                            <span>L:</span>
                            <span class="text-center">{{ $shipmentInfo ? ($shipmentInfo->length ?: '') : '' }}</span>
                            <span>cm</span>

                            <span>Wt:</span>
                            <span class="text-center">{{ $shipmentInfo ? ($shipmentInfo->weight ?: '') : '' }}</span>
                            <span>kg</span>
                        </div>
                    </div>
            </section>

            {{-- Footer --}}
            <section class="tracking-number-section footer">
                <div class="last-four-digits-container">
                    <span class="last-four-digits">{{ substr($trackingNo, -4) }}</span>
                </div>
                <div class="barcode-container">
                    <img class="barcode-img" src="data:image/png;base64,{{ $barcodeImage }}" />
                    <p class="tracking-number">{{ $trackingNo }}</p>
                </div>
            </section>

            {{-- Bottom Info --}}
            <section>
                <div class="flex" style="justify-content: space-between;">
                    <span>📞+968 72224866</span>
                    <div class="flex flex-column flex-centered">
                        <span>Reverse Logistics Service</span>
                    </div>
                    <span>{{ $timestamp }}</span>
                </div>
            </section>
        </div>
    </div>
</body>

</html>
