<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
            padding: 5px;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Total Revenue</th>
                <th>COD Collected</th>
                <th>Expenses</th>
                <th>Net Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reports as $report)
            <tr>
                <td>{{ $report['date'] }}</td>
                <td>${{ number_format($report['total_revenue'], 2) }}</td>
                <td>${{ number_format($report['cod_collected'], 2) }}</td>
                <td>${{ number_format($report['expenses'], 2) }}</td>
                <td>${{ number_format($report['net_profit'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
