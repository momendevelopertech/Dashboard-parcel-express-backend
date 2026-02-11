<!DOCTYPE html>
<html>
<head>
    <title>Driver Performance Export</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>Driver Performance Report</h1>
    <table>
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ ucwords(str_replace('_', ' ', $column)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($drivers as $driver)
                <tr>
                    @foreach($columns as $column)
                        <td>{{ $driver[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
