<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Routine - {{ $exam->name ?? 'Exam' }}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 { margin: 0; font-size: 26px; text-transform: uppercase; color: #111; }
        .header h2 { margin: 8px 0; font-size: 18px; color: #444; }
        .header p { margin: 4px 0; font-size: 14px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #222;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }

        td { font-size: 14px; font-weight: 500; }
        .subject-col { text-align: left; font-weight: bold; font-size: 15px; }
        
        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }

        .footer-sig { border-top: 1px solid #000; padding-top: 5px; width: 200px; text-align: center; }

        @media print {
            @page { size: A4 portrait; margin: 15mm; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ strtoupper($schoolName) }}</h1>
        <h2>OFFICIAL EXAM ROUTINE - {{ strtoupper($exam->name ?? 'EXAM') }}</h2>
        <p>Class: {{ $schoolClass->name ?? 'N/A' }} | Academic Year: {{ $academicYear->name ?? 'N/A' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25%;">Date & Day</th>
                <th style="width: 40%;">Subject</th>
                <th style="width: 35%;">Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($routines as $routine)
                <tr>
                    <td>
                        {{ \Carbon\Carbon::parse($routine->exam_date)->format('d F Y') }}<br>
                        <strong>{{ \Carbon\Carbon::parse($routine->exam_date)->format('l') }}</strong>
                    </td>
                    <td class="subject-col">{{ $routine->subject->name ?? 'N/A' }}</td>
                    <td>
                        {{ \Carbon\Carbon::parse($routine->start_time)->format('h:i A') }} 
                        - 
                        {{ \Carbon\Carbon::parse($routine->end_time)->format('h:i A') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-sig">Exam Controller</div>
        <div class="footer-sig">Headmaster</div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>