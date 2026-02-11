<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $name }} Export</title>
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
            /* max-width: 210mm; */
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
             word-wrap: break-word; /* break long words */
              overflow-wrap: break-word; /* ensures old browsers wrap too */
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ececec;
            word-wrap: break-word;
            vertical-align: top;
                word-wrap: break-word; /* break long words */
              overflow-wrap: break-word; /* ensures old browsers wrap too */
        }

        tr:nth-child(even) {
            background-color: #fbfcfd;
        }

        /* Arabic text styling */
        .arabic-text {
            direction: rtl;
            unicode-bidi: bidi-override; /* Ensures characters are joined correctly */
            font-family: 'Cairo', 'Noto Kufi Arabic', 'DejaVu Sans', Tahoma, Arial, sans-serif;
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
            size: A3 landscape;
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
            <h2>{{ $name }} Report</h2>
            <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    @if(!empty($headers))
                    @foreach($headers as $header)
                    <th style="width:80%;">{{ $header }}</th>
                    @endforeach
                    @else
                    @foreach($columns as $column)
                    <th style="width: {{ 100 / count($columns) }}%;">
                        @if($column === 'governorate.en_name')
                        Governorate (English)
                        @elseif($column === 'governorate.ar_name')
                        Governorate (Arabic)
                        @elseif($column === 'hub.name')
                        Hub
                        @elseif($column === 'driver.company.name')
                        Company
                        @elseif($column === 'driver.phone')
                        Phone
                        @elseif($column === 'authenticatable.name')
                        Name
                        @elseif($column === 'user.name')
                        Name
                        @else
                        {{ ucwords(str_replace('_', ' ', $column)) }}
                        @endif
                    </th>
                    @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    @foreach($columns as $column)
                    @php
                    $cellValue = in_array($column, ['created_at', 'updated_at'])
                        ? ($row->$column ? $row->$column->format('Y-m-d H:i:s') : '')
                        : ($row->$column ?? 'N/A');
                    // Detect presence of Arabic characters
                    $isArabic = preg_match('/\p{Arabic}/u', $cellValue);
                    @endphp
                    <td class="{{ $isArabic ? 'arabic-text' : '' }}">
                        {{ $cellValue??'N/A' }}
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