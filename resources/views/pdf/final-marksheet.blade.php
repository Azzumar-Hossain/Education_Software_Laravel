<!DOCTYPE html>
@php
    $settings = \App\Models\SiteSetting::first();
    
    // --- 1. HELPER FUNCTIONS ---
    $getGrade = function($percentage) {
        if ($percentage >= 80) return 'A+';
        if ($percentage >= 70) return 'A';
        if ($percentage >= 60) return 'A-';
        if ($percentage >= 50) return 'B';
        if ($percentage >= 40) return 'C';
        if ($percentage >= 33) return 'D';
        return 'F';
    };

    $getGPA = function($percentage) {
        if ($percentage >= 80) return '5.00';
        if ($percentage >= 70) return '4.00';
        if ($percentage >= 60) return '3.50';
        if ($percentage >= 50) return '3.00';
        if ($percentage >= 40) return '2.00';
        if ($percentage >= 33) return '1.00';
        return '0.00';
    };

    // --- 2. SMART CUMULATIVE MATH ---
    $mainExamIds = \App\Models\Exam::whereNull('parent_exam_id')->pluck('id')->toArray();
    $validAllMarks = $allMarks->filter(fn($m) => in_array($m->exam_id, $mainExamIds));
    
    $finalGroupedMarks = [];
    $processedFinalIds = [];
    $grandMax = 0;
    $grandObtained = 0;
    $totalGPA = 0;
    $gpaCount = 0;
    $hasFailed = false;
    $failedSubjectsCount = 0;
    
    foreach($validAllMarks as $mark) {
        if(in_array($mark->subject_id, $processedFinalIds)) continue;
        
        $partnerMark = null;
        $partnerSubjectId = null;
        
        // Check for linked 1st & 2nd papers
        if ($mark->subject && $mark->subject->linked_subject_id) {
            $partnerSubjectId = $mark->subject->linked_subject_id;
            $partnerMark = $validAllMarks->firstWhere('subject_id', $partnerSubjectId);
        } else {
            $partnerMark = $validAllMarks->where('subject.linked_subject_id', $mark->subject_id)->first();
            if ($partnerMark) {
                $partnerSubjectId = $partnerMark->subject_id;
                $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
            }
        }
        
        if ($partnerMark && $partnerSubjectId) {
            // Combined Subject Logic
            $p1 = $validAllMarks->where('subject_id', $mark->subject_id);
            $p2 = $validAllMarks->where('subject_id', $partnerSubjectId);

            $combinedMax = (($p1->sum(fn($m) => $m->subject->full_marks ?? 100) + $p2->sum(fn($m) => $m->subject->full_marks ?? 100)) / 2);
            $combinedObt = ($p1->sum('marks_obtained') + $p2->sum('marks_obtained')) / 2;
            $combinedWritten = ($p1->sum('written_mark') + $p2->sum('written_mark')) / 2;
            $combinedMcq = ($p1->sum('mcq_mark') + $p2->sum('mcq_mark')) / 2;
            
            $perc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;
            $grade = $getGrade($perc);
            $gpa = $getGPA($perc);
            
            $finalGroupedMarks[] = [
                'name' => trim(str_replace([' 1st', ' 2nd', ' Paper', ' I', ' II'], '', $mark->subject->name ?? 'Subject')),
                'written' => $combinedWritten,
                'mcq' => $combinedMcq,
                'max' => $combinedMax,
                'obt' => $combinedObt,
                'grade' => $grade,
                'gpa' => $gpa
            ];
            
            $grandMax += $combinedMax;
            $grandObtained += $combinedObt;
            $totalGPA += (float)$gpa;
            $gpaCount++;
            
            if ($grade === 'F') {
                $hasFailed = true;
                $failedSubjectsCount++;
            }
            
            $processedFinalIds[] = $mark->subject_id;
            $processedFinalIds[] = $partnerSubjectId;
        } else {
            // Single Subject Logic
            $subMax = $validAllMarks->where('subject_id', $mark->subject_id)->sum(fn($m) => $m->subject->full_marks ?? 100);
            $subObt = $validAllMarks->where('subject_id', $mark->subject_id)->sum('marks_obtained');
            $perc = $subMax > 0 ? ($subObt / $subMax) * 100 : 0;
            $grade = $getGrade($perc);
            $gpa = $getGPA($perc);
            
            $finalGroupedMarks[] = [
                'name' => $mark->subject->name ?? 'Subject',
                'written' => $validAllMarks->where('subject_id', $mark->subject_id)->sum('written_mark'),
                'mcq' => $validAllMarks->where('subject_id', $mark->subject_id)->sum('mcq_mark'),
                'max' => $subMax,
                'obt' => $subObt,
                'grade' => $grade,
                'gpa' => $gpa
            ];
            
            $grandMax += $subMax;
            $grandObtained += $subObt;
            $totalGPA += (float)$gpa;
            $gpaCount++;
            
            if ($grade === 'F') {
                $hasFailed = true;
                $failedSubjectsCount++;
            }
            
            $processedFinalIds[] = $mark->subject_id;
        }
    }
    
    // Final Summaries
    $finalGPA = ($gpaCount > 0 && !$hasFailed) ? number_format($totalGPA / $gpaCount, 2) : '0.00';
    $finalPercentage = $grandMax > 0 ? ($grandObtained / $grandMax) * 100 : 0;
    $finalOverallGrade = $hasFailed ? 'F' : $getGrade($finalPercentage);
@endphp

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Final Cumulative Marksheet - {{ $enrollment->user->name }}</title>
    <style>
        body { font-family: 'kalpurush', sans-serif; margin: 0; padding: 15px; background: #e9e9e9; color: #1d1d1d; }
        
        .page { background: #fff; border: 5px solid #d4af37; padding: 20px; margin: 0 auto; }
        
        /* Layout Tables */
        table { width: 100%; border-collapse: collapse; }
        .no-border td { border: none; padding: 2px; }
        
        /* Header Elements */
        .school-name { font-size: 20px; font-weight: 700; color: #0a6b3d; margin: 0; }
        .school-address { font-size: 12px; color: #1d1d1d; }
        .logo { max-width: 70px; max-height: 70px; }
        
        /* Grade Scale Table */
        .grade-scale { font-size: 10px; width: 180px; float: right; }
        .grade-scale th, .grade-scale td { border: 1px solid #999; padding: 2px 5px; text-align: center; }
        .grade-scale th { background: #0a5c36; color: #fff; font-weight: 600; }
        
        /* Photo & Badge */
        .photo-box { width: 88px; height: 104px; border: 1px solid #999; background: #eee; text-align: center; font-size: 10px; padding: 20px 5px; color: #888; }
        .badge { background: #16a394; color: #fff; font-weight: bold; font-size: 16px; padding: 6px 20px; border-radius: 4px; text-align: center; display: inline-block; }
        
        /* Info Section */
        .info-table { font-size: 12px; margin-top: 15px; }
        .info-table td { padding: 3px 0; }
        .info-label { font-weight: bold; width: 110px; }
        
        /* Main Subjects Table */
        .subjects { margin-top: 15px; font-size: 11px; }
        .subjects th { background: #0a5c36; color: #fff; padding: 6px; border: 1px solid #0a5c36; }
        .subjects td { padding: 4px; border: 1px solid #ccc; text-align: center; }
        .subjects .text-left { text-align: left; padding-left: 8px; font-weight: bold; }
        .subjects tr.alt { background: #f7f7f2; }
        .subjects tfoot td { font-weight: bold; border-top: 2px solid #0a5c36; }
        .subjects tfoot .label { color: #0a6b3d; text-align: left; }
        
        /* Results Section */
        .result-wrapper { margin-top: 15px; }
        .result-table { font-size: 12px; width: 100%; }
        .result-table td { border: 1px solid #999; padding: 5px 8px; }
        .result-table .pass { color: #0a8a3c; font-weight: bold; }
        .result-table .fail { color: #d32f2f; font-weight: bold; }
        
        /* Comments Box */
        .comments-box { border: 1px solid #999; padding: 8px; font-size: 12px; height: 60px; vertical-align: top; }
        
        /* Signatures */
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
                <div class="badge">FINAL CUMULATIVE MARKSHEET</div>
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
                    <tr><td class="info-label">Exam</td><td>: FINAL RESULT ({{ $enrollment->academicYear->name }})</td></tr>
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
                <th class="text-left" style="width: 28%;">Name of Subjects</th>
                <th>TOTAL WRITTEN</th>
                <th>TOTAL MCQ / ORAL</th>
                <th>TOTAL MARKS</th>
                <th>TOTAL OBTAINED</th>
                <th>Letter Grade</th>
                <th>Grade Point</th>
            </tr>
        </thead>
        <tbody>
            @foreach($finalGroupedMarks as $index => $row)
                @php $rowClass = $index % 2 == 0 ? '' : 'alt'; @endphp
                <tr class="{{ $rowClass }}">
                    <td class="text-left">{{ $row['name'] }}</td>
                    <td>{{ number_format($row['written'], 1) }}</td>
                    <td>{{ number_format($row['mcq'], 1) }}</td>
                    <td>{{ number_format($row['max'], 0) }}</td>
                    <td>{{ number_format($row['obt'], 1) }}</td>
                    <td style="font-weight: bold;">{{ $row['grade'] }}</td>
                    <td style="font-weight: bold;">{{ $row['gpa'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="text-left label">Obtained Marks & GPA</td>
                <td colspan="2"></td>
                <td>{{ number_format($grandMax, 0) }}</td>
                <td>{{ number_format($grandObtained, 1) }}</td>
                <td style="color: #0a6b3d;">{{ $finalOverallGrade }}</td>
                <td style="color: #0a6b3d;">{{ $finalGPA }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="no-border result-wrapper">
        <tr>
            <td style="width: 32%; padding-right: 8px; vertical-align: top;">
                <table class="result-table">
                    <tr><td class="label" style="width:100px;">Result Status</td><td class="{{ $hasFailed ? 'fail' : 'pass' }}">{{ $hasFailed ? 'Failed' : 'Passed (Promoted)' }}</td></tr>
                    <tr><td class="label">Class Position</td><td>--</td></tr>
                    <tr><td class="label">GPA</td><td style="font-weight: bold;">{{ $finalGPA }}</td></tr>
                </table>
            </td>
            
            <td style="width: 32%; padding-right: 8px; vertical-align: top;">
                <table class="result-table">
                    <tr><td class="label" style="width:115px;">Failed Subject(s)</td><td>{{ $failedSubjectsCount }}</td></tr>
                    <tr><td class="label">Working Days</td><td></td></tr>
                    <tr><td class="label">Present Days</td><td></td></tr>
                </table>
            </td>
            
            <td style="width: 36%; vertical-align: top;">
                <div class="comments-box">
                    <strong>Comments:</strong><br>
                </div>
            </td>
        </tr>
    </table>

    <table class="no-border signatures">
        <tr>
            <td style="width: 33%;"><div class="sig-line">Guardian's Signature</div></td>
            <td style="width: 33%;"><div class="sig-line">Class Teacher's Signature</div></td>
            <td style="width: 33%;">
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