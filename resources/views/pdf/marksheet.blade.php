<!DOCTYPE html>
@php
    $settings = \App\Models\SiteSetting::first();
    
    // 1. Helper functions
    $getGrade = function($perc) {
        if($perc >= 80) return 'A+';
        if($perc >= 70) return 'A';
        if($perc >= 60) return 'A-';
        if($perc >= 50) return 'B';
        if($perc >= 40) return 'C';
        if($perc >= 33) return 'D';
        return 'F';
    };

    $getGPA = function($perc) {
        if($perc >= 80) return '5.00';
        if($perc >= 70) return '4.00';
        if($perc >= 60) return '3.50';
        if($perc >= 50) return '3.00';
        if($perc >= 40) return '2.00';
        if($perc >= 33) return '1.00';
        return '0.00';
    };

    $getHighest = function($subjectId, $column) use ($marks) {
        if ($marks->isEmpty()) return '--';
        $sampleMark = $marks->first();
        $highest = $sampleMark->newQuery()
            ->where('exam_id', $sampleMark->exam_id)
            ->where('subject_id', $subjectId)
            ->max($column);
        return ($highest !== null && $highest > 0) ? number_format($highest, 2) : '--';
    };

    // --- 2. SINGLE TERM CUMULATIVE WEIGHTAGE ---
    $childExams = \App\Models\Exam::where('parent_exam_id', $exam->id)->get();
    
    if ($childExams->count() > 0) {
        $childrenTotalWeight = $childExams->sum('contribution_percentage');
        $mainExamWeight = 100 - $childrenTotalWeight; 

        foreach($marks as $mark) {
            $cumulativeObtained = 0;
            $r = $mark->subject->getMarksForExam($exam->id);
            $mainMax = $r['full_marks'] > 0 ? $r['full_marks'] : 100;
            
            $mainWeighted = $mainMax > 0 ? ($mark->marks_obtained / $mainMax) * ($mainMax * ($mainExamWeight / 100)) : 0;
            $cumulativeObtained += $mainWeighted;
            
            foreach($childExams as $childExam) {
                $childMark = \App\Models\Mark::where('exam_id', $childExam->id)
                    ->where('student_id', $mark->student_id)
                    ->where('subject_id', $mark->subject_id)
                    ->first();
                    
                if ($childMark) {
                    $cRules = $childMark->subject->getMarksForExam($childExam->id);
                    $childMax = $cRules['full_marks'] > 0 ? $cRules['full_marks'] : 100;
                    $childWeighted = $childMax > 0 ? ($childMark->marks_obtained / $childMax) * ($mainMax * ($childExam->contribution_percentage / 100)) : 0;
                    $cumulativeObtained += $childWeighted;
                }
            }
            $mark->marks_obtained = round($cumulativeObtained, 2);
            $mark->grade = $getGrade(($mark->marks_obtained / $mainMax) * 100);
        }
    }

    // --- 3. COMBINED SUBJECTS LOGIC ---
    $groupedMarks = [];
    $processedIds = [];
    $totalGPA = 0;

    foreach($marks as $mark) {
        if(in_array($mark->id, $processedIds)) continue;

        $partnerMark = null;
        if ($mark->subject->linked_subject_id) {
            $partnerMark = $marks->firstWhere('subject_id', $mark->subject->linked_subject_id);
        } else {
            $partnerMark = $marks->where('subject.linked_subject_id', $mark->subject_id)->first();
            if ($partnerMark) {
                $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
            }
        }

        $r1 = $mark->subject->getMarksForExam($exam->id);
        $max1 = $r1['full_marks'] > 0 ? $r1['full_marks'] : 100;

        if ($partnerMark) {
            $r2 = $partnerMark->subject->getMarksForExam($exam->id);
            $max2 = $r2['full_marks'] > 0 ? $r2['full_marks'] : 100;

            $combinedMax = $max1 + $max2;
            $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;
            $combinedPerc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;
            $gpa = $getGPA($combinedPerc);

            $groupedMarks[] = [
                'is_combined' => true,
                'subject_model' => $mark->subject,
                'paper1' => $mark,
                'paper2' => $partnerMark,
                'max1' => $max1,
                'max2' => $max2,
                'combined_max' => $combinedMax,
                'combined_obt' => $combinedObt,
                'combined_grade' => $getGrade($combinedPerc),
                'gpa' => $gpa
            ];
            $totalGPA += (float)$gpa;
            $processedIds[] = $mark->id;
            $processedIds[] = $partnerMark->id;
        } else {
            $perc = $max1 > 0 ? ($mark->marks_obtained / $max1) * 100 : 0;
            $gpa = $getGPA($perc);
            
            $groupedMarks[] = [
                'is_combined' => false,
                'subject_model' => $mark->subject,
                'paper1' => $mark,
                'max1' => $max1,
                'combined_grade' => $getGrade($perc),
                'gpa' => $gpa
            ];
            $totalGPA += (float)$gpa;
            $processedIds[] = $mark->id;
        }
    }
    
    $totalMax = $marks->sum(function($m) use ($exam) {
        $r = $m->subject->getMarksForExam($exam->id);
        return $r['full_marks'] > 0 ? $r['full_marks'] : 100;
    });
    $totalObtained = $marks->sum('marks_obtained');
    
    $finalGPA = count($groupedMarks) > 0 ? number_format($totalGPA / count($groupedMarks), 2) : '0.00';
    $finalGrade = $getGrade($totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0);
    $hasFailed = $marks->where('grade', 'F')->count() > 0;
    if($hasFailed) {
        $finalGPA = '0.00';
        $finalGrade = 'F';
    }

    // 🌟 NEW: ISOLATED GPA & GRADE WITHOUT 4TH SUBJECT CALCULATION LOGIC 🌟
    $coreGPAs = [];
    $hasCoreFail = false;

    foreach ($groupedMarks as $gMark) {
        $subName = strtolower($gMark['subject_model']->name ?? '');
        $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');

        // Detect and skip optional fields to satisfy board regulations
        if (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional') {
            continue;
        }

        if ($gMark['combined_grade'] === 'F') {
            $hasCoreFail = true;
        }
        $coreGPAs[] = (float) $gMark['gpa'];
    }

    $coreCount = count($coreGPAs);
    $gpaWithout4th = ($hasCoreFail || $coreCount === 0) ? '0.00' : number_format(array_sum($coreGPAs) / $coreCount, 2);
    
    $gradeWithout4th = 'F';
    $gpaFloatWithout4th = (float) $gpaWithout4th;
    if ($gpaFloatWithout4th >= 5.00) $gradeWithout4th = 'A+';
    elseif ($gpaFloatWithout4th >= 4.00) $gradeWithout4th = 'A';
    elseif ($gpaFloatWithout4th >= 3.50) $gradeWithout4th = 'A-';
    elseif ($gpaFloatWithout4th >= 3.00) $gradeWithout4th = 'B';
    elseif ($gpaFloatWithout4th >= 2.00) $gradeWithout4th = 'C';
    elseif ($gpaFloatWithout4th >= 1.00) $gradeWithout4th = 'D';
@endphp

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Marksheet - {{ $enrollment->user->name }}</title>
    <style>
        body { font-family: 'kalpurush', sans-serif; margin: 0; padding: 15px; background: #e9e9e9; color: #1d1d1d; }
        .page { background: #fff; border: 5px solid #d4af37; padding: 20px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .no-border td { border: none; padding: 2px; }
        .school-name { font-size: 20px; font-weight: 700; color: #0a6b3d; margin: 0; }
        .school-address { font-size: 12px; color: #1d1d1d; }
        .logo { max-width: 70px; max-height: 70px; }
        .grade-scale { font-size: 10px; width: 180px; float: right; }
        .grade-scale th, .grade-scale td { border: 1px solid #999; padding: 2px 5px; text-align: center; }
        .grade-scale th { background: #0a5c36; color: #fff; font-weight: 600; }
        .photo-box { width: 88px; height: 104px; border: 1px solid #999; background: #eee; text-align: center; font-size: 10px; padding: 20px 5px; color: #888; }
        .badge { background: #16a394; color: #fff; font-weight: bold; font-size: 16px; padding: 6px 20px; border-radius: 4px; text-align: center; display: inline-block; }
        .info-table { font-size: 12px; margin-top: 15px; }
        .info-table td { padding: 3px 0; }
        .info-label { font-weight: bold; width: 110px; }
        .subjects { margin-top: 15px; font-size: 11px; }
        .subjects th { background: #0a5c36; color: #fff; padding: 6px; border: 1px solid #0a5c36; }
        .subjects td { padding: 4px; border: 1px solid #ccc; text-align: center; }
        .subjects .text-left { text-align: left; padding-left: 8px; font-weight: bold; }
        .subjects tr.alt { background: #f7f7f2; }
        .subjects tfoot td { font-weight: bold; border-top: 2px solid #0a5c36; }
        .subjects tfoot .label { color: #0a6b3d; text-align: left; }
        .result-wrapper { margin-top: 15px; }
        .result-table { font-size: 12px; width: 100%; }
        .result-table td { border: 1px solid #999; padding: 5px 8px; }
        .result-table .pass { color: #0a8a3c; font-weight: bold; }
        .result-table .fail { color: #d32f2f; font-weight: bold; }
        .comments-box { border: 1px solid #999; padding: 8px; font-size: 12px; height: 60px; vertical-align: top; }
        .signatures { margin-top: 40px; font-size: 12px; text-align: center; }
        .sig-line { border-top: 1px dotted #555; padding-top: 5px; margin-top: 50px; width: 160px; display: inline-block; }
        .footer { margin-top: 15px; font-size: 10px; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }
    </style>
</head>
<body>

<div class="page">
    <table class="no-border">
        <tr>
            <td style="width: 15%; vertical-align: top;">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo">
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; padding-top: 10px;">
                <div class="school-name">{{ strtoupper($settings->school_name_en ?? 'HARIMOHAN GOVT. HIGH SCHOOL') }}</div>
                <div class="school-address">&#9670; {{ strtoupper($settings->address_en ?? 'NEW MARKET, CHAPAI NAWABGANJ') }}</div>
            </td>
            <td style="width: 35%; vertical-align: top;">
                <table class="grade-scale">
                    <thead>
                        <tr><th>Range</th><th>Grade</th><th>GPA</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>80 - 100</td><td>A+</td><td>5.00</td></tr>
                        <tr><td>70 - 79</td><td>A</td><td>4.00</td></tr>
                        <tr><td>60 - 69</td><td>A-</td><td>3.50</td></tr>
                        <tr><td>50 - 59</td><td>B</td><td>3.00</td></tr>
                        <tr><td>40 - 49</td><td>C</td><td>2.00</td></tr>
                        <tr><td>33 - 39</td><td>D</td><td>1.00</td></tr>
                        <tr><td>0 - 32</td><td>F</td><td>0.00</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="no-border" style="margin-top: 10px;">
        <tr>
            <td style="width: 20%;">
                <div class="photo-box">
                    Paste<br>Photograph<br>Here<br>3.5 x 4.5
                </div>
            </td>
            <td style="width: 60%; text-align: center; vertical-align: middle;">
                <div class="badge">Marks Sheet</div>
            </td>
            <td style="width: 20%;"></td>
        </tr>
    </table>

    <table class="no-border info-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <table class="no-border">
                    <tr><td class="info-label">Student's Name</td><td>: {{ strtoupper($enrollment->user->name) }}</td></tr>
                    <tr><td class="info-label">Father's Name</td><td>: _______________________</td></tr>
                    <tr><td class="info-label">Mother's Name</td><td>: _______________________</td></tr>
                    <tr><td class="info-label">Roll No.</td><td>: {{ $enrollment->roll_number }}</td></tr>
                    <tr><td class="info-label">Student ID</td><td>: {{ $enrollment->user->student_id ?? 'N/A' }}</td></tr>
                    <tr><td class="info-label">Class</td><td>: {{ strtoupper($enrollment->schoolClass->name) }}</td></tr>
                </table>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="no-border">
                    <tr><td class="info-label">Exam</td><td>: {{ strtoupper($exam->name ?? 'Exam') }}</td></tr>
                    <tr><td class="info-label">Section</td><td>: {{ strtoupper($enrollment->section->name ?? 'N/A') }}</td></tr>
                    <tr><td class="info-label">Medium</td><td>: {{ strtoupper($settings->medium ?? 'Bangla') }}</td></tr>
                    <tr><td class="info-label">Shift</td><td>: Day</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="subjects">
        <thead>
            <tr>
                <th rowspan="2" class="text-left" style="width: 25%;">Name of Subjects</th>
                <th colspan="2">WRITTEN</th>
                <th colspan="2">MCQ / ORAL</th>
                <th colspan="2">TOTAL MARKS</th>
                <th colspan="2">COMBINED</th>
                <th rowspan="2">Letter<br>Grade</th>
                <th rowspan="2">Grade<br>Point</th>
            </tr>
            <tr>
                <th>High</th><th>Obt</th>
                <th>High</th><th>Obt</th>
                <th>Max</th><th>Obt</th>
                <th>Max</th><th>Obt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupedMarks as $index => $group)
                @php $rowClass = $index % 2 == 0 ? '' : 'alt'; @endphp
                
                @if($group['is_combined'])
                    <tr class="{{ $rowClass }}">
                        <td class="text-left">{{ $group['paper1']->subject->name ?? 'Subject' }}</td>
                        <td>{{ $getHighest($group['paper1']->subject_id, 'written_mark') }}</td><td>{{ number_format($group['paper1']->written_mark, 1) }}</td>
                        <td>{{ $getHighest($group['paper1']->subject_id, 'mcq_mark') }}</td><td>{{ number_format($group['paper1']->mcq_mark, 1) }}</td>
                        
                        <td>{{ number_format($group['max1'], 0) }}</td>
                        <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                        
                        <td rowspan="2" style="vertical-align: middle;">{{ number_format($group['combined_max'], 0) }}</td>
                        <td rowspan="2" style="vertical-align: middle;">{{ number_format($group['combined_obt'], 1) }}</td>
                        <td rowspan="2" style="vertical-align: middle; font-weight: bold;">{{ $group['combined_grade'] }}</td>
                        <td rowspan="2" style="vertical-align: middle; font-weight: bold;">{{ $group['gpa'] }}</td>
                    </tr>
                    <tr class="{{ $rowClass }}">
                        <td class="text-left">{{ $group['paper2']->subject->name ?? 'Subject' }}</td>
                        <td>{{ $getHighest($group['paper2']->subject_id, 'written_mark') }}</td><td>{{ number_format($group['paper2']->written_mark, 1) }}</td>
                        <td>{{ $getHighest($group['paper2']->subject_id, 'mcq_mark') }}</td><td>{{ number_format($group['paper2']->mcq_mark, 1) }}</td>
                        
                        <td>{{ number_format($group['max2'], 0) }}</td>
                        <td>{{ number_format($group['paper2']->marks_obtained, 1) }}</td>
                    </tr>
                @else
                    <tr class="{{ $rowClass }}">
                        <td class="text-left">{{ $group['paper1']->subject->name ?? 'Subject' }}</td>
                        <td>{{ $getHighest($group['paper1']->subject_id, 'written_mark') }}</td><td>{{ number_format($group['paper1']->written_mark, 1) }}</td>
                        <td>{{ $getHighest($group['paper1']->subject_id, 'mcq_mark') }}</td><td>{{ number_format($group['paper1']->mcq_mark, 1) }}</td>
                        
                        <td>{{ number_format($group['max1'], 0) }}</td>
                        <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                        
                        <td>{{ number_format($group['max1'], 0) }}</td>
                        <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                        <td style="font-weight: bold;">{{ $group['combined_grade'] }}</td>
                        <td style="font-weight: bold;">{{ $group['gpa'] }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="text-left label">Obtained Marks & GPA</td>
                <td colspan="4"></td>
                <td>{{ $totalMax }}</td>
                <td>{{ number_format($totalObtained, 1) }}</td>
                <td colspan="2"></td>
                <td style="color: #0a6b3d;">{{ $finalGrade }}</td>
                <td style="color: #0a6b3d;">{{ $finalGPA }}</td>
            </tr>
            <tr style="background: #f0fdf4;">
                <td class="text-left label" style="color: #2563eb;">Core Results (Without 4th Sub)</td>
                <td colspan="8"></td>
                <td style="color: #2563eb; font-weight: bold;">{{ $gradeWithout4th }}</td>
                <td style="color: #2563eb; font-weight: bold;">{{ $gpaWithout4th }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="no-border result-wrapper">
        <tr>
            <td style="width: 35%; padding-right: 8px; vertical-align: top;">
                <table class="result-table">
                    <tr><td class="label" style="width:110px;">Result Status</td><td class="{{ $hasFailed ? 'fail' : 'pass' }}">{{ $hasFailed ? 'Failed' : 'Passed' }}</td></tr>
                    <tr><td class="label">Class Position</td><td>--</td></tr>
                    <tr><td class="label">GPA (With 4th Sub)</td><td style="font-weight: bold;">{{ $finalGPA }}</td></tr>
                    <tr style="background: #f8fafc;"><td class="label" style="color: #2563eb;">GPA (Without 4th)</td><td style="font-weight: bold; color: #2563eb;">{{ $gpaWithout4th }}</td></tr>
                </table>
            </td>
            
            <td style="width: 32%; padding-right: 8px; vertical-align: top;">
                <table class="result-table">
                    <tr><td class="label" style="width:115px;">Failed Subject(s)</td><td>{{ $marks->where('grade', 'F')->count() }}</td></tr>
                    <tr><td class="label">Working Days</td><td></td></tr>
                    <tr><td class="label">Present Days</td><td></td></tr>
                </table>
            </td>
            
            <td style="width: 33%; vertical-align: top;">
                <div class="comments-box">
                    <strong>Comments:</strong><br>
                </div>
            </td>
        </tr>
    </table>

    <table class="no-border signatures">
        <tr>
            <td><div class="sig-line">Guardian's Signature</div></td>
            <td><div class="sig-line">Class Teacher's Signature</div></td>
            <td>
                <div class="sig-line">Principal's/Head Teacher's Signature</div>
            </td>
        </tr>
    </table>

    <table class="no-border footer">
        <tr>
            <td style="text-align: left;">Powered by EduSphere</td>
            <td style="text-align: right;">Published Date: {{ date('d-m-Y') }}</td>
        </tr>
    </table>

</div>

</body>
</html>