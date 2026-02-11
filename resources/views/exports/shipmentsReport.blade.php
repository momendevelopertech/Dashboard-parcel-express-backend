<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shipments Export</title>
    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #fff;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }

        /* Report Container */
        .report-container {
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            padding: 15mm 20mm;
        }

        /* Report Header */
        .report-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .report-date {
            font-size: 14px;
            color: #666;
        }

        /* Table Styles */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .report-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .report-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        /* Status Tags */
        .status-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-delivered {
            background-color: #e6f4ea;
            color: #1e7e34;
        }

        .status-pending {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .status-exception {
            background-color: #fce8e8;
            color: #d32f2f;
        }
    </style>
</head>

<body>
    <div class="report-container">
        <div class="report-header">
            <h1 class="report-title">Shipments Report</h1>
            <div class="report-date">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ ucwords(str_replace(['_', '.'], ' ', $column)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($shipments as $shipment)
                    <tr>
                        @foreach($columns as $column)
                            <td>
                                @if(str_contains($column, '.'))
                                    @php
                                        $parts = explode('.', $column);
                                        $value = $shipment;
                                        foreach($parts as $part) {
                                            $value = $value?->{$part};
                                        }
                                    @endphp
                                    {{ $value }}
                                @elseif($column === 'status')
                                    <span class="status-tag status-{{ strtolower($shipment->status) }}">
                                        {{ $shipment->status }}
                                    </span>
                                @elseif(in_array($column, ['created_at', 'updated_at']))
                                    {{ $shipment->{$column}?->format('Y-m-d H:i:s') }}
                                @elseif($column === 'amount')
                                    {{ number_format($shipment->amount, 2) }}
                                @else
                                    {{ $shipment->{$column} }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>

</html>
