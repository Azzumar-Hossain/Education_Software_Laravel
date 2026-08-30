<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admit Cards - {{ $exam->name ?? 'Exam' }}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .card-container {
            width: 100%;
            border: 2px solid #222;
            padding: 20px;
            box-sizing: border-box;
            margin-bottom: 30px;
            page-break-inside: avoid; /* Prevents a card from splitting across pages */
        }

        /* 🌟 Force 1 per page if routine is attached, otherwise allow stacking 🌟 */
        .force-page-break {
            page-break-after: always;
            break-after: page;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .header h2 { margin: 5px 0; font-size: 18px; letter-spacing: 2px; }
        .header p { margin: 0; font-size: 14px; font-weight: bold; }

        .student-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-col {
            display: table-cell;
            width: 75%;
            vertical-align: top;
            font-size: 14px;
            line-height: 1.8;
        }
        .photo-col {
            display: table-cell;
            width: 25%;
            vertical-align: top;
            text-align: right;
        }
        .photo-box {
            width: 100px;
            height: 120px;
            border: 1px solid #333;
            display: inline-block;
            text-align: center;
            line-height: 120px;
            color: #777;
            font-size: 12px;
        }

        table.routine-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .routine-table th, .routine-table td {
            border: 1px solid #333;
            padding: 6px;
            text-align: center;
            font-size: 12px;
        }
        .routine-table th { background-color: #f4f4f4; }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            font-weight: bold;
            font-size: 14px;
        }
        .sig-line { border-top: 1px solid #000; padding-top: 5px; width: 200px; text-align: center; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { padding: 0; margin: 0; }
            .card-container:last-child, .force-page-break:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>

    @foreach($enrollments as $enrollment)
        <div class="card-container {{ $includeRoutine ? 'force-page-break' : '' }}">
            
            <div class="header">
                <h1>{{ strtoupper($schoolName) }}</h1>
                <h2>ADMIT CARD</h2>
                <p>{{ strtoupper($exam->name ?? 'EXAM') }} - {{ $academicYear->name ?? '' }}</p>
            </div>

            <div class="student-info">
                <div class="info-col">
                    <strong>Student Name:</strong> {{ $enrollment->user->name ?? 'N/A' }} <br>
                    <strong>Student ID:</strong> {{ $enrollment->user->student_id ?? 'N/A' }} <br>
                    <strong>Class:</strong> {{ $enrollment->schoolClass->name ?? 'N/A' }} <br>
                    <strong>Roll Number:</strong> {{ $enrollment->roll_number }} <br>
                    <strong>Section:</strong> {{ $enrollment->section->name ?? 'N/A' }} <br>
                    <strong>Group:</strong> {{ $enrollment->study_group ?? 'General' }}
                </div>
                <div class="photo-col">
                    <!-- Swap this with an actual <img> tag if you store profile pictures -->
                    <div class="photo-box">Attach Photo</div>
                </div>
            </div>

            @if($includeRoutine && $routines->isNotEmpty())
                <table class="routine-table">
                    <thead>
                        <tr>
                            <th>Date & Day</th>
                            <th>Subject</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($routines as $routine)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($routine->exam_date)->format('d-M-Y') }} 
                                    ({{ \Carbon\Carbon::parse($routine->exam_date)->format('D') }})
                                </td>
                                <td style="text-align: left; padding-left: 10px;">{{ $routine->subject->name ?? 'N/A' }}</td>
                                <td>
                                    {{ \Carbon\Carbon::parse($routine->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($routine->end_time)->format('h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="signatures">
                <div class="sig-line">Class Teacher</div>
                <div class="sig-line">Principal / Headmaster</div>
            </div>

        </div>
        
        <!-- If routines are OFF, page break every 2 cards so they don't break in the middle -->
        @if(!$includeRoutine && $loop->iteration % 2 == 0)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500); 
        };
    </script>
</body>
</html>