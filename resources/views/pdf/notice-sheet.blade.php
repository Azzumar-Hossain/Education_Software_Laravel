<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notice Board Sheet - {{ $schoolClass->name ?? 'Class' }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 10px;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .header h3 { margin: 5px 0; font-size: 15px; }
        .header p { margin: 2px 0; font-size: 12px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        th, td {
            border: 1px solid #444;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
            font-size: 10px;
        }
        
        .col-name { text-align: left; max-width: 130px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .col-sub { min-width: 35px; line-height: 1.2; }
        .val-obt { display: block; font-size: 11px; }
        .val-grd { display: block; font-weight: bold; color: #111; }
        
        .failed-row { background-color: #fff0f0; }
        .failed-grade { color: #dc2626; }
        
        .highlight { font-weight: bold; background-color: #fcfcfc; }

        /* 🌟 Force Landscape Printing 🌟 */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Krisnagobindapur High School</h1>
        <h3>RESULT SHEET (NOTICE BOARD) - {{ strtoupper($exam->name ?? 'EXAM') }}</h3>
        <p>
            Class: {{ $schoolClass->name ?? 'N/A' }} | 
            Year: {{ $academicYear->name ?? 'N/A' }} 
            @if($groupName)| Group: {{ $groupName }} @endif
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">Roll</th>
                <th class="col-name">Student Name</th>
                
                <!-- Dynamically generate subject columns -->
                @foreach($subjects as $sub)
                    <!-- Removes brackets like (Compulsory) to save space -->
                    <th class="col-sub">{{ \Illuminate\Support\Str::limit(preg_replace('/\(.*\)/u', '', $sub->name), 15, '..') }}</th>
                @endforeach
                
                <th class="highlight">Total</th>
                <th class="highlight">GPA</th>
                <th class="highlight">Rank</th>
            </tr>
        </thead>
        <tbody>
            @foreach($studentResults as $student)
                <tr class="{{ $student['has_core_fail'] ? 'failed-row' : '' }}">
                    <td style="font-weight: bold;">{{ $student['enrollment']->roll_number }}</td>
                    <td class="col-name">{{ $student['enrollment']->user->name ?? 'N/A' }}</td>
                    
                    @foreach($subjects as $sub)
                        @php
                            $markRecord = $student['marks'][$sub->id] ?? null;
                        @endphp
                        
                        <td class="col-sub">
                            @if($markRecord)
                                <span class="val-obt">{{ $markRecord->marks_obtained + 0 }}</span> <!-- +0 removes trailing .00 decimals -->
                                <span class="val-grd {{ trim($markRecord->grade) === 'F' ? 'failed-grade' : '' }}">{{ $markRecord->grade }}</span>
                            @else
                                -
                            @endif
                        </td>
                    @endforeach

                    <td class="highlight">{{ number_format($student['grand_total'], 0) }}</td>
                    <td class="highlight {{ $student['has_core_fail'] ? 'failed-grade' : '' }}">{{ $student['gpa'] }}</td>
                    <td class="highlight {{ $student['has_core_fail'] ? 'failed-grade' : '' }}">{{ $student['position'] }}</td>
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