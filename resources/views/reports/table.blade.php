<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 8mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #777;
            padding: 3px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 8px;
        }

        td {
            font-size: 7px;
        }
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        @foreach ($headers as $header)
            <th>{{ $header }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            @foreach ($row as $cell)
                <td>{{ $cell }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>