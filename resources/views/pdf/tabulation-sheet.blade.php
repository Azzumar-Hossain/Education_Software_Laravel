<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tabulation Sheet - {{ $schoolClass->name ?? 'Class' }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 10px;
            font-size: 10px; /* Condensed font for extreme data density */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header h3 { margin: 3px 0; font-size: 14px; }
        .header p { margin: 2px 0; font-size: 11px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        th { background-color: #f0f0f0; font-weight: bold; font-size: 9px; }
        
        .col-name { text-align: left; max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
        
        .sub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
        .sub-grid div { text-align: center; }
        .sub-grid div:first-child { border-right: 1px dotted #888; font-weight: bold; }
        
        .failed-row { background-color: #fff0f0; }
        .failed-text { color: #dc2626; font-weight: bold; }
        .highlight { font-weight: bold; background-color: #fafafa; font-size: 11px;}

        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { padding: 0; }
            .page-break { page-break-after: always; break-after: page; }
            .page-break:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>

    <!-- 🌟 We automatically split the students based on the Rows/Page dropdown! 🌟 -->
    @foreach(collect($studentResults)->chunk($rowsPerPage) as $pageIndex => $studentChunk)
        <div class="page-break">
            
            <div class="header">
                <h1>Krisnagobindapur High School</h1>
                <h3>TABULATION SHEET - {{ strtoupper($exam->name ?? 'EXAM') }}</h3>
                <p>
                    Class: {{ $schoolClass->name ?? 'N/A' }} | 
                    Year: {{ $academicYear->name ?? 'N/A' }} 
                    @if($groupName)| Group: {{ $groupName }} @endif
                    | Page {{ $pageIndex + 1 }}
                </p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 25px;">Rank</th>
                        <th rowspan="2" style="width: 35px;">Roll</th>
                        <th rowspan="2" class="col-name">Student Name</th>
                        
                        @foreach($subjects as $sub)
                            <th colspan="1">{{ \Illuminate\Support\Str::limit(preg_replace('/\(.*\)/u', '', $sub->name), 12, '') }}</th>
                        @endforeach
                        
                        <th rowspan="2" class="highlight">Total</th>
                        <th rowspan="2" class="highlight">GPA</th>
                        <th rowspan="2" class="highlight">Grade</th>
                    </tr>
                    <tr>
                        @foreach($subjects as $sub)
                            <th>
                                <div class="sub-grid">
                                    <div>Obt</div>
                                    <div>Gr</div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentChunk as $student)
                        <tr class="{{ $student['has_core_fail'] ? 'failed-row' : '' }}">
                            <td style="font-weight: bold;">{{ $student['position'] }}</td>
                            <td style="font-weight: bold;">{{ $student['enrollment']->roll_number }}</td>
                            <td class="col-name">{{ $student['enrollment']->user->name ?? 'N/A' }}</td>
                            
                            @foreach($subjects as $sub)
                                @php
                                    $markRecord = $student['marks'][$sub->id] ?? null;
                                @endphp
                                
                                <td>
                                    @if($markRecord)
                                        <div class="sub-grid">
                                            <div>{{ $markRecord->marks_obtained + 0 }}</div>
                                            <div class="{{ trim($markRecord->grade) === 'F' ? 'failed-text' : '' }}">{{ $markRecord->grade }}</div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach

                            <td class="highlight">{{ number_format($student['grand_total'], 0) }}</td>
                            <td class="highlight {{ $student['has_core_fail'] ? 'failed-text' : '' }}">{{ $student['gpa'] }}</td>
                            <td class="highlight {{ $student['has_core_fail'] ? 'failed-text' : '' }}">{{ $student['grade'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500); 
        };
    </script>
</body>
</html>