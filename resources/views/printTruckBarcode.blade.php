<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Truck Barcode</title>
    <link rel="preload" as="image" href="data:image/png;base64,{{ DNS1D::getBarcodePNG($truck->barcode, 'C39') }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f3f3;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .barcode-container {
            background-color: #fff;
            padding: 30px;
            text-align: center;
            max-width: 90%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .barcode-image {
            width: 1000px;
            height: 300px;
            image-rendering: crisp-edges;
            display: block;
            margin: 0 auto;
        }

        .barcode-number {
            margin-top: 25px;
            font-size: 40px;
            font-family: 'Courier New', monospace;
            letter-spacing: 8px;
            font-weight: bold;
            color: #000;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="barcode-container">
        <?php echo '<img src="data:image/png;base64,' . DNS1D::getBarcodePNG($truck->barcode, 'C39') . '" class="barcode-image"/>'; ?>
        <div class="barcode-number">{{ $truck->barcode }}</div>
    </div>
</body>

</html>
