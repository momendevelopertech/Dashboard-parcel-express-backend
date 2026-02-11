<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parcel Express - Container Label</title>

    <style>
        @media print {
            @page {
                size: 100mm 150mm;
                margin: 0;
            }

            body, html {
                width: 100mm;
                margin: 0;
                padding: 0;
            }

            .label {
                width: 98mm;
                height: 148mm;
                margin: 1mm;
                border: 1px solid #000;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                page-break-after: always;
            }

            .label:last-child {
                page-break-after: auto;
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-weight: bold;
        }

        .tracking-number {
            font-size: 20px;
            margin-top: 10px;
            letter-spacing: 1px;
        }

        .barcode img {
            width: 90%;
            height: auto;
        }
    </style>
</head>

<body>

@foreach ($containers as $container)
    @php
        $trackingNo = $container->tracking_no;
        $barcode = DNS1D::getBarcodePNG($trackingNo, 'C128', 2.5, 60);
    @endphp

    <div class="label">
        <div class="barcode">
            <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode">
        </div>

        <div class="tracking-number">
            {{ $trackingNo }}
        </div>
    </div>
@endforeach

</body>
</html>
