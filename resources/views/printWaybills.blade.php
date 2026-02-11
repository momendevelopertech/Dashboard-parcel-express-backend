<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Parcel Express - waybills</title>

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
                /* padding-top: 1mm; */
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
            font-size: 120px;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.2);
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
            /* color: #000000bb; */
            color:#000;
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
            /* display: none; */
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

        .cod-protected {
            /* background: #00000066;
            color: #fff;
            padding: 5px;
            margin: -5px; */
        }

        .cod-protected .section-title-container {
            /* color: #fff; */
        }

        .cod-protected .cod-value {
            /* background: #fff;
            color: #000;
            padding: 3px 10px;
            display: inline-block; */
        }
    </style>
</head>

<body>
@php
    $waybills = $waybills ?? null;
    $shipments = $shipments ?? null;
    $shipment = $shipment ?? null;

    $isIterable = static fn ($value) => is_array($value) || $value instanceof \Illuminate\Support\Collection;

    if ($isIterable($waybills)) {
        $items = $waybills;
        $itemType = 'waybill';
        $defaultShowWatermark = false;
        $defaultShowTimestamp = true;
    } elseif ($isIterable($shipments)) {
        $items = $shipments;
        $itemType = 'shipment';
        $defaultShowWatermark = true;
        $defaultShowTimestamp = false;
    } elseif (is_object($shipment)) {
        $items = [$shipment];
        $itemType = 'shipment';
        $defaultShowWatermark = true;
        $defaultShowTimestamp = true;
    } else {
        $items = [];
        $itemType = 'waybill';
        $defaultShowWatermark = false;
        $defaultShowTimestamp = true;
    }

    $currencyEn = getCurrency('en');
    $currencyAr = getCurrency('ar');
    $forcePrintMode = $forcePrintMode ?? 'none';
@endphp

@foreach ($items as $item)
    @php
        $actualData = $itemType === 'waybill'
            ? (data_get($item, 'shipment') ?: $item)
            : $item;

        $hasWaybillMerchant = (bool) data_get($item, 'merchant_id') || data_get($item, 'merchant');
        $hasShipmentMerchant = (bool) data_get($actualData, 'merchant_id') || data_get($actualData, 'merchant');
        $hasMerchant = $itemType === 'waybill'
            ? ($hasWaybillMerchant || $hasShipmentMerchant)
            : $hasShipmentMerchant;

        if ($forcePrintMode === 'international-waybill') {
            $useInternationalWaybill = true;
            $useLocalWaybill = false;
        } elseif ($forcePrintMode === 'local-waybill') {
            $useInternationalWaybill = false;
            $useLocalWaybill = true;
        } else {
            $useInternationalWaybill = !$hasMerchant;
            $useLocalWaybill = !$useInternationalWaybill;
        }

        $currentShowWatermark = $showWatermark ?? $defaultShowWatermark ?? $useInternationalWaybill;
        $currentShowTimestamp = $showTimestamp ?? $defaultShowTimestamp ?? ($itemType === 'waybill');

        $trackingNo = data_get($item, 'tracking_no') ?? data_get($actualData, 'tracking_no', '');
        $barcodeImage = DNS1D::getBarcodePNG($trackingNo, 'C128', 2.5, 55);

        $consignee = data_get($actualData, 'consignee');
        $deliveryAddress = data_get($actualData, 'deliveryAddress');
        $shipmentInfo = data_get($actualData, 'shipment_information');
        $shipmentItems = data_get($actualData, 'shipment_items');
        $merchant = data_get($actualData, 'merchant');
        $shipper = data_get($actualData, 'shipper');

        $isShipment = $itemType === 'shipment' || $consignee || $shipmentItems;
        $totalCod = $isShipment ? data_get($actualData, 'total_cod') : null;
        $timestamp = $currentShowTimestamp ? now()->format('Y-m-d H:i:s') : null;
        $driverCode = !$shipmentInfo && data_get($actualData, 'driver')? 'DR-'.substr(data_get($actualData, 'driver.phone', ''), -4) : null;
    @endphp

    @if($useLocalWaybill || $useInternationalWaybill)
        {{-- Local Waybill Template --}}
        <div class="waybill-wrapper">
        <div class="container" data-order="{{ $loop->iteration }}">
            @if($currentShowWatermark && isset($actualData->payment_type))
                <div class="watermark">{{ $actualData->payment_type }}</div>
            @endif
            <section class="header tracking-number-section">
                <div class="header-icon">
                    <img src="{{ asset('images/logo.jpg') }}" class="header-logo" alt="Parcel Express Logo">
                </div>
                <div class="barcode-container">
                    <img class="barcode-img" src="data:image/png;base64,{{ $barcodeImage }}" />
                    <p class="tracking-number">{{ $trackingNo }}</p>
                </div>
            </section>
            @if(!$isShipment || ($hasMerchant && $merchant))
            <section class="flex gap-10px">
                <span class="section-title-container">📥Merchant Information | معلومات التاجر</span>
                <p class="data-content flex-grow flex flex-centered">
                    {{ $merchant->name ?? '' }}
                </p>
            </section>
            @elseif($shipper)
            <section class="flex flex-column gap-10px">
                <span class="section-title-container">✈️Shipper Information | معلومات المرسل</span>
                <div class="data-content flex flex-centered space-around" style="margin-top: -15px;font-size: 14px;">
                    <span>{{ $shipper->name ?? '' }}</span>
                    <span>
                        {{ $shipper ? ($shipper->country_key_contact . $shipper->contact) : '' }}
                    </span>
                </div>
            </section>
            @endif
            <section>
                <span class="section-title-container">CONSIGNEE INFORMATION | معلومات المستلم</span>
                <div class="grid vertically-separated" style="grid-template-rows: 12.5mm 12.5mm auto;">
                    <div class="flex horizontally-separated">
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Name | الاسم</span>
                            <p class="data-content flex-grow flex flex-centered">
                                @if($consignee)
                                    {{ $consignee->name ?? '' }}
                                @endif</p>
                        </div>
                        <div class="flex flex-column" style="flex: 2;">
                            <span class="section-title-container">Phone | الهاتف</span>
                            <p class="data-content flex-grow flex flex-centered">
                                @if($consignee)
                                    {{ ($consignee->country_key_cellphone ?? '') . ($consignee->cellphone ?? '') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex horizontally-separated">
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Governorate | المحافظة</span>
                            <p class="data-content flex-grow flex flex-centered">
                                @if($isShipment && $deliveryAddress && isset($deliveryAddress->governorate))
                                    {{ $deliveryAddress->governorate->ar_name ?? '' }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-column" style="flex: 4;">
                            <span class="section-title-container">State | الولاية</span>
                            <p class="data-content flex-grow flex flex-centered">
                                @if($isShipment && $deliveryAddress && isset($deliveryAddress->state))
                                    {{ $deliveryAddress->state->ar_name ?? '' }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-column" style="flex: 3;">
                            <span class="section-title-container">Place | المكان</span>
                            <p class="data-content flex-grow flex flex-centered">
                                @if($isShipment && $deliveryAddress && isset($deliveryAddress->place))
                                    {{ $deliveryAddress->place->ar_name ?? '' }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div>
                        <span class="section-title-container">ِAddress | العنوان</span>
                        <p class="data-content">
                            @if($isShipment && $deliveryAddress && isset($deliveryAddress->streetAddress))
                                {{ $deliveryAddress->streetAddress ?? '' }}
                            @endif
                        </p>
                    </div>
                </div>
            </section>
            <section class="flex horizontally-separated">
                @if(!$consignee)
                    <div class="flex flex-column">
                        <span class="section-title-container">Delivery fees | رسوم التوصيل</span>
                        <div class="flex">
                            <div>
                                <input type="checkbox" name="" id="">
                                <span>غير مدفوع</span>
                            </div>
                            <div>
                                <input type="checkbox" name="" id="">
                                <span>مدفوع</span>
                            </div>
                        </div>
                    </div>
                @endif
                <div style="flex: 5;" class="{{ $shipmentInfo ? 'cod-protected' : '' }}">
                    <span class="section-title-container">Cach On Delivery | المستحق عند الاستلام</span>
                    <p class="data-content">
                        <span class="{{ $shipmentInfo ? 'cod-value' : '' }}">
                            @if(!is_null($totalCod))
                                {{ number_format($totalCod, 3) }} {{ $currencyEn }}/{{ $currencyAr }}
                            @endif
                        </span>
                    </p>
                </div>
            </section>
            <section>
                <div class="grid grid-3-3-2 horizontally-separated" style="grid-template-columns: 5fr 5fr 3fr;">
                    <div>
                        <span class="section-title-container">ITEMS | المنتجات</span>
                        <div class="normal">
                        @if($shipmentItems)
                            @foreach ($shipmentItems as $shipmentItem)
                                <div>
                                    {{ $shipmentItem->name ?? '' }} —
                                    Qty: {{ $shipmentItem->quantity ?? '0' }},
                                    Price: {{ number_format($shipmentItem->price ?? 0, 2) }} {{ $currencyEn }}/{{ $currencyAr }},
                                    Weight: {{ $shipmentItem->weight ?? '0' }} kg
                                </div>
                            @endforeach
                        @endif
                        </div>
                    </div>
                    <div class="flex flex-column vertically-separated" style="max-height: 100%;">
                        <div class="flex flex-column" style="flex: 2">
                            <span class="section-title-container">Category | التصنيف</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                @if($shipmentInfo)
                                    {{ $shipmentInfo->package_id ? 'Package' : 'General' }}
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-column" style="flex: 1">
                            <span class="section-title-container">PCS | القطع</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                @if($shipmentItems)
                                    {{ $shipmentItems->count() }}
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-column" style="flex: 1">
                            <span class="section-title-container">QTY | الكمية</span>
                            <span class="flex flex-centered" style="margin-top: -12px;justify-content: end;">
                                @if($shipmentItems)
                                    {{ $shipmentItems->sum('quantity') }}
                                @endif
                            </span>
                        </div>

                    </div>
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
            <section class="tracking-number-section footer">
                <div class="last-four-digits-container">
                    <span class="last-four-digits">{{ substr($trackingNo, -4) }}</span>
                    @if($driverCode)
                        <span style="font-size: 10px; display: block; margin-top: 2px;">{{ $driverCode }}</span>
                    @endif
                </div>
                <div class="barcode-container">
                    <img class="barcode-img" src="data:image/png;base64,{{ $barcodeImage }}" />
                    <p class="tracking-number">{{ $trackingNo }}</p>
                </div>
            </section>
            <section>
                <div class="flex" style="justify-content: space-between;">
                    <span>📞+968 72224866</span>
                    <div class="flex flex-column flex-centered">
                        <span>{{$useInternationalWaybill? "Your Package, Our Priority" : "شحن محلي بمعايير عالمية"}}</span>
                    </div>
                    @if($timestamp)
                        <span>{{ $timestamp }}</span>
                    @endif
                </div>
            </section>
        </div>
        </div>
    @endif
@endforeach
</body>
</html>
