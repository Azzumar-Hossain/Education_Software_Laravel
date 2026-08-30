<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Failed Students List - {{ $schoolClass->name ?? 'Class' }}</title>
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
            border-bottom: 2px solid #dc2626; /* Red border to indicate fail list */
            padding-bottom: 10px;
        }
        
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .header h3 { margin: 5px 0; font-size: 16px; color: #dc2626; } /* Red title */
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
        .failed-row { background-color: #fef2f2; }
        .fail-count { font-weight: bold; color: #dc2626; font-size: 13px; }
        .subjects { color: #555; font-style: italic; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Krisnagobindapur High School</h1>
        <h3>FAILED STUDENTS REPORT</h3>
        <p>Class: {{ $schoolClass->name ?? 'N/A' }} | Academic Year: {{ $academicYear->name ?? 'N/A' }}</p>
        <p>Scope: {{ ucfirst($scope) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Roll</th>
                <th>Section</th>
                <th>Total Marks</th>
                <th>Fail Count</th>
                <th>Failed Subjects</th>
            </tr>
        </thead>
        <tbody>
            @forelse($calculatedFailRecords as $index => $student)
                <tr class="failed-row">
                    <td style="font-weight: bold;">{{ $index + 1 }}</td>
                    <td>{{ $student['student_id'] }}</td>
                    <td class="text-left">{{ $student['student_name'] }}</td>
                    <td>{{ $student['roll_number'] }}</td>
                    <td>{{ $student['section_name'] }}</td>
                    <td style="font-weight: bold;">{{ number_format($student['total_marks'], 1) }}</td>
                    <td class="fail-count">{{ $student['fail_count'] }}</td>
                    <td class="text-left subjects">{{ $student['failed_subjects_summary'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="padding: 20px; font-weight: bold; color: #166534;">
                        Excellent News! No failed students were found in this selection.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>