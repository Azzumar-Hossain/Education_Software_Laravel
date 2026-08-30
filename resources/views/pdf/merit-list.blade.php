<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Merit List - {{ $exam->name ?? 'Exam' }}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #c9a24b;
            padding-bottom: 10px;
        }
        
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .header h3 { margin: 5px 0; font-size: 16px; color: #444; }
        .header p { margin: 2px 0; font-size: 13px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        td { font-size: 11px; }
        .text-left { text-align: left; font-weight: bold; }
        .failed-row { color: #dc2626; }
        .highlight { font-weight: bold; color: #166534; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Krisnagobindapur High School</h1>
        <h3>OFFICIAL MERIT LIST - {{ strtoupper($exam->name ?? 'EXAM') }}</h3>
        <p>Class: {{ $schoolClass->name ?? 'N/A' }} | Academic Year: {{ $exam->academicYear->name ?? 'N/A' }}</p>
        <p>Ranking Scope: {{ ucfirst($scope) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Roll</th>
                <th>Section</th>
                <th>Group</th>
                <th>Total Marks</th>
                <th>GPA</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calculatedRankings as $index => $student)
                <tr class="{{ $student['failed'] ? 'failed-row' : '' }}">
                    <td style="font-weight: bold;">{{ $index + 1 }}</td>
                    <td>{{ $student['student_id'] }}</td>
                    <td class="text-left">{{ $student['name'] }}</td>
                    <td>{{ $student['roll'] }}</td>
                    <td>{{ $student['section'] }}</td>
                    <td>{{ $student['group'] }}</td>
                    <td class="highlight">{{ number_format($student['total'], 1) }}</td>
                    <td class="highlight">{{ $student['gpa'] }}</td>
                    <td style="font-weight: bold;">{{ $student['grade'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>