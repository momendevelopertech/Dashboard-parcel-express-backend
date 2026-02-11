<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>States Export</title>
    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #fff;
            line-height: 1.3;
            color: #333;
            padding: 0 !important;
        }

        /* Report Container */
        .report-container {
            width: 100% !important;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm 15mm !important;
        }

        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ddd;
        }

        .header h2 {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        /* Table Critical Fixes */
        table {
            width: 100% !important;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
            empty-cells: show;
        }

        th {
            background-color: #f8f9fa !important;
            font-weight: 600;
            padding: 8px !important;
            border: 1px solid #ddd !important;
            word-break: break-word;

            width: calc(100% / {
                        {
                        count($columns)
                    }
                });
        }

        td {
            padding: 6px !important;
            border: 1px solid #ddd !important;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;

            width: calc(100% / {
                        {
                        count($columns)
                    }
                });
        }

        tr {
            page-break-inside: avoid !important;
            page-break-after: auto !important;
        }

        /* Print Optimization */
        @media print {
            table {
                table-layout: fixed !important;
                width: 100% !important;
            }

            th,
            td {
                width: calc(100% / {
                            {
                            count($columns)
                        }
                    });
                min-width: 0 !important;
                max-width: none !important;
            }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
            padding: 0;
        }

        /* Content Overflow Handling */
        .cell-content {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
    </style>
</head>

<body>
    <div class="report-container">
        <div class="header">
            <h2>States Report</h2>
            <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        </div>
        <table>
            <thead>
                <tr>
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                            <th style="width: {{ 100 / count($columns) }}%;">
                                @if($column === 'governorate.en_name')
                                Governorate (English)
                                @elseif($column === 'governorate.ar_name')
                                Governorate (Arabic)
                                @else
                                {{ ucwords(str_replace('_', ' ', $column)) }}
                                @endif
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                </tr>
            </thead>
            <tbody>
                @foreach($states as $state)
                <tr>
                    @foreach($columns as $column)
                    <td>
                        <div class="cell-content">
                            @if(str_contains($column, '.'))
                            @php
                            // Split nested column into relation and field
                            [$relation, $field] = explode('.', $column, 2);
                            @endphp
                            {{ optional($state->{$relation})->{$field} ?? 'N/A' }}
                            @else
                            @if(in_array($column, ['created_at', 'updated_at']))
                            {{ $state->{$column}?->format('Y-m-d H:i:s') ?? '' }}
                            @else
                            {{ $state->{$column} ?? 'N/A' }}
                            @endif
                            @endif
                        </div>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <p>This is an automatically generated report. Valid at time of generation.</p>
        </div>
    </div>
</body>

</html>