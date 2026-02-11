<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parcel Express - Container Waybill</title>
    <style>
        @media print {
            @page { size: 100mm 150mm; margin: 0; }
            body, html { width: 100mm; height: auto; margin: 0; padding: 0; }
            .container { page-break-after: always; }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 98mm;
            height: 148mm;
            margin: 1mm auto;
            border: 1px solid #000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .barcode-container img {
            max-width: 90mm;
            height: 60px;
            margin-bottom: 10px;
        }

        .tracking-number {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .last-four-digits {
            font-size: 45px;
            font-weight: bold;
            border: 2px solid #000;
            padding: 10px 15px;
            margin-top: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
@php
    $container = $container ?? null;
    if (!$container) {
        echo '<div class="container"><p>No container data</p></div>';
        return;
    }
    $trackingNo = $container->tracking_no ?? '';
    $barcodeImage = DNS1D::getBarcodePNG($trackingNo, 'C128', 3, 70);
@endphp

<div class="container">
    <div class="barcode-container">
        <img src="data:image/png;base64,{{ $barcodeImage }}" alt="Barcode">
        <div class="tracking-number">{{ $trackingNo }}</div>
    </div>
    <div class="last-four-digits">{{ substr($trackingNo, -4) }}</div>
</div>
</body>
</html>
