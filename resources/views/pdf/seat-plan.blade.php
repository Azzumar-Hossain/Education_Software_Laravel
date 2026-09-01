<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seat Plan Slips - {{ $exam->name ?? 'Exam' }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #fff;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .slips-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* 2 slips per row */
            gap: 20px;
            padding: 15px;
        }

        .slip {
            border: 2px dashed #666;
            padding: 12px;
            page-break-inside: avoid; /* Prevents slip from splitting across pages */
            border-radius: 8px;
            text-align: center;
        }

        .slip-header {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .student-cluster {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .student-box {
            flex: 1;
            border: 1px solid #333;
            padding: 8px 4px;
            font-size: 11px;
            border-radius: 4px;
            background: #fafafa;
        }

        .pos-badge {
            background: #222;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 6px;
            display: inline-block;
        }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="slips-grid">
        @foreach($generatedAllocation as $benchIndex => $students)
            <div class="slip">
                <div class="slip-header">
                    {{ strtoupper($schoolName) }}<br>
                    <span style="font-size: 12px; color: #444;">{{ strtoupper($exam->name ?? 'EXAM') }} - BENCH {{ sprintf('%02d', $benchIndex) }}</span>
                </div>
                
                <div class="student-cluster">
                    @foreach($students as $student)
                        <div class="student-box">
                            <div class="pos-badge">Position {{ $student['position'] }}</div><br>
                            <span style="font-weight: bold; font-size: 12px;">{{ $student['class_name'] }}</span><br>
                            Roll: <strong style="font-size: 18px;">{{ $student['roll'] }}</strong><br>
                            <span style="display: block; margin-top: 4px; font-size: 10px; line-height: 1.2;">
                                {{ \Illuminate\Support\Str::limit($student['student_name'], 15) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500); 
        };
    </script>
</body>
</html>