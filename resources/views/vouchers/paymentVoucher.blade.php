<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher</title>
    <style>
        /* Compact A4 Styles */
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8fafc;
            font-size: 12px;
        }

        .container {
            max-width: 595px;
            /* A4 width */
            min-height: 842px;
            /* A4 height */
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            opacity: 0.1;
            font-size: 80px;
            transform: rotate(-45deg);
            top: 30%;
            left: 10%;
            z-index: 1;
            color: #3b82f6;
        }

        /* Header Section */
        .voucher-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            position: relative;
            z-index: 2;
        }

        .company-info h1 {
            font-size: 22px;
            margin: 0 0 5px 0;
        }

        .company-info p {
            font-size: 12px;
            line-height: 1.4;
        }

        .voucher-title {
            font-size: 20px;
            margin: 10px 0;
        }

        /* Details Section */
        .voucher-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: #f8fafc;
            position: relative;
            z-index: 2;
        }

        .detail-group {
            margin-bottom: 10px;
        }

        .detail-label {
            font-size: 11px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 13px;
            padding: 6px 0;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 30px;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding-top: 15px;
        }

        .signature-box {
            padding: 10px;
        }

        .signature-line {
            width: 70%;
            margin: 30px auto 8px;
        }

        /* Footer Note */
        .footer-note {
            margin-top: 20px;
            font-size: 11px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 640px) {
            .container {
                padding: 20px;
            }

            .voucher-details,
            .signature-section {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        @media print {
            body {
                padding: 0;
                background: white;
                font-size: 10px;
            }

            .container {
                box-shadow: none;
                border: none;
                padding: 20px;
                min-height: auto;
            }

            .watermark {
                opacity: 0.15;
                font-size: 60px;
            }

            .signature-line {
                margin: 20px auto;
            }
        }
    </style>

</head>

<body>
    <div class="container">
        <div class="watermark">PAID</div>

        <!-- Header -->
        <div class="voucher-header">
            <div class="company-info">
                <h1>Parcel Express</h1>
                <p>Muscat, Sultanate of Oman</p>
                <p>TRN: 123456789012345 | Tel: +968 1234 5678</p>
            </div>
            <h2 class="voucher-title">Payment Voucher</h2>
        </div>

        <!-- Voucher Details -->
        <div class="voucher-details">
            <div>
                <div class="detail-group">
                    <div class="detail-label">Voucher Number</div>
                    <div class="detail-value">PV-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Payment Date</div>
                    <div class="detail-value">{{ $payment->paid_at->format('d M Y H:i') }}</div>
                </div>
            </div>
            <div>
                <div class="detail-group">
                    <div class="detail-label">Amount Paid</div>
                    <div class="detail-value">{{getCurrency("en")}}/{{getCurrency("ar")}} {{ number_format($payment->amount, 3) }}</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Paid To</div>
                    <div class="detail-value">{{ $driver->name }} (ID: {{ $driver->id }})</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Paid By</div>
                    <div class="detail-value">{{ $cashier->name }}</div>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="detail-label">Driver's Signature</div>
                <div class="signature-line"></div>
                <div class="detail-value">{{ $driver->name }}</div>
            </div>
            <div class="signature-box">
                <div class="detail-label">Cashier's Signature</div>
                <div class="signature-line"></div>
                <div class="detail-value">{{ $cashier->name }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            <p>** This is a computer generated voucher and does not require manual signature **</p>
        </div>
    </div>
</body>

</html>
