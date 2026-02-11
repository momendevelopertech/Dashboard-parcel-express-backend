{{-- resources/views/printShelf.blade.php --}}
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Shelf Labels</title>
    <style>
        @media print {
            @page {
                size: 100mm 150mm;
                margin: 0;
            }

            html,
            body {
                margin: 0;
                padding: 0;
                display: block !important;
                /* تأكد إن مافيش flex/grid وِراثة */
                overflow: visible !important;
                /* مهم لكروم */
            }

            /* كل ليبل = صفحة مستقلة */
            .page {
                width: 100mm;
                height: 150mm;
                box-sizing: border-box;

                display: block !important;
                /* لازم block */
                break-after: page;
                /* حديث */
                page-break-after: always;
                /* قديم */

                break-inside: avoid;
                /* لا تكسر الصفحة داخليًا */
                page-break-inside: avoid;
                background: #fff;
            }

            .page:last-of-type {
                break-after: auto;
                page-break-after: auto;
            }
        }

        /* بدون @media (يشتغل في الشاشة برضو) */
        .label-container {
            width: 98mm;
            height: 148mm;
            border: 1px solid #000;
            box-sizing: border-box;
            padding: 5mm;

            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
        }

        .barcode {
            margin-bottom: 10px;
        }

        .barcode img {
            width: 90mm;
            height: auto;
        }

        .shelf-name {
            font-size: 30px;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>

<body>

    {{-- رف واحد --}}
    @if (isset($shelf))
        <div class="page">
            <div class="label-container">
                <div class="barcode">
                    <?php echo '<img src="data:image/png;base64,' . DNS1D::getBarcodePNG($shelf['barcode'], 'C39', 2, 80) . '" />'; ?>
                </div>
                <div class="shelf-name">{{ $shelf->location }}</div>
            </div>
        </div>
    @endif

    {{-- مجموعة رفوف (bulk) --}}
    @if (!empty($shelves))
        @foreach ($shelves as $s)
            <div class="page">
                <div class="label-container">
                    <div class="barcode">
                        <?php echo '<img src="data:image/png;base64,' . DNS1D::getBarcodePNG($s['barcode'], 'C39', 2, 80) . '" />'; ?>
                    </div>
                    <div class="shelf-name">{{ $s->location }}</div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- كاتيجوري (اختياري) --}}
    @if (isset($category))
        @php $includeCategoryLabel = request()->boolean('includeCategoryLabel', true); @endphp

        @if ($includeCategoryLabel)
            <div class="page">
                <div class="label-container">
                    <div class="barcode">
                        <?php echo '<img src="data:image/png;base64,' . DNS1D::getBarcodePNG($category['barcode'], 'C39', 2, 80) . '" />'; ?>
                    </div>
                    <div class="shelf-name">{{ $category->name }}</div>
                </div>
            </div>
        @endif

        @foreach ($category->shelves ?? [] as $s)
            <div class="page">
                <div class="label-container">
                    <div class="barcode">
                        <?php echo '<img src="data:image/png;base64,' . DNS1D::getBarcodePNG($s['barcode'], 'C39', 2, 80) . '" />'; ?>
                    </div>
                    <div class="shelf-name">{{ $s->location }}</div>
                </div>
            </div>
        @endforeach
    @endif

</body>

</html>
