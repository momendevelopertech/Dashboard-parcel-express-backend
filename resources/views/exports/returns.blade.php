<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Returns Export</title>
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
            max-width: 210mm;
            /* A4 width */
            margin: 0 auto;
            padding: 15mm 20mm;
        }

        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .header h2 {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 0.85em;
            table-layout: fixed;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 12px 10px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ececec;
            word-wrap: break-word;
            vertical-align: top;
        }

        tr:nth-child(even) {
            background-color: #fbfcfd;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            font-size: 0.8em;
            color: #95a5a6;
        }

        /* Print Optimization */
        @media print {
            body {
                padding: 0;
                background: none;
            }

            .report-container {
                padding: 0;
                max-width: 100%;
                box-shadow: none;
            }

            table {
                font-size: 10pt;
            }

            .header,
            .footer {
                border-color: #ddd;
            }
        }

        /* Prevent Page Breaks */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>
    <div class="report-container">
        <div class="header">
            <h2>Returns Report</h2>
            <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    @foreach($columns as $column)
                    <th style="width: {{ 100 / count($columns) }}%;">
                        @if($column == 'return_id')
                            Return ID
                        @elseif($column == 'shipment_tracking_no')
                            Shipment Tracking No
                        @elseif($column == 'customer_name')
                            Customer Name
                        @elseif($column == 'reason')
                            Return Reason
                        @elseif($column == 'status')
                            Status
                        @elseif($column == 'created_at')
                            Created At
                        @elseif($column == 'updated_at')
                            Updated At
                        @else
                            {{ ucwords(str_replace('_', ' ', $column)) }}
                        @endif
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($returns as $return)
                <tr>
                    @foreach($columns as $column)
                    <td>
                        @if(in_array($column, ['created_at', 'updated_at']))
                            {{ $return[$column] ? date('Y-m-d H:i:s', strtotime($return[$column])) : 'N/A' }}
                        @else
                            {{ $return[$column] ?? 'N/A' }}
                        @endif
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
