<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Mid Term Mark Sheet - {{ $enrollment->user->name }}</title>
@php
    $settings = \App\Models\SiteSetting::first() ?? \App\Models\Setting::first();

    $logoUrl = ($settings && !empty($settings->logo))
        ? \Illuminate\Support\Facades\Storage::url($settings->logo)
        : null;

    $schoolName = !empty($settings?->school_name_en)
        ? $settings->school_name_en
        : 'Shankarbati High School';

    $schoolAddress = !empty($settings?->address_en)
        ? $settings->address_en
        : 'Chapai Nawabganj, Bangladesh';

    $studentPhoto = null;
    $rawPhotoPath = $enrollment->user->avatar ?? $enrollment->user->photo ?? null;

    if ($rawPhotoPath) {
        $fullPath = storage_path('app/public/' . $rawPhotoPath);
        if (file_exists($fullPath)) {
            $studentPhoto = $fullPath;
        } else {
            $studentPhoto = \Illuminate\Support\Facades\Storage::url($rawPhotoPath);
        }
    }

    $className = strtolower($enrollment->schoolClass->name ?? '');
    $has4thSubjectColumn = str_contains($className, '9') || str_contains($className, '10');

    $getGradeData = function($percentage, $isComponentFailed = false) {
        return \App\Models\GradeScale::getGradeForMark($percentage, $isComponentFailed);
    };

    $formatSubjectWithCode = function($subjectModel) {
        return $subjectModel->name;
    };

    // 🌟 STREAM FILTERING LOGIC 🌟
    $studentGroup = strtolower($enrollment->study_group ?? '');

    $marks = $marks->filter(function($mark) use ($studentGroup) {
        if (!$mark->subject) return false;

        $subCode = (string) ($mark->subject->code ?? '');
        $subName = strtolower($mark->subject->name ?? '');

        // Rule 1: Science students do not take General Science (Code 127)
        if (in_array($studentGroup, ['science'])) {
            if ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান')) {
                return false;
            }
        }

        // Rule 2: Arts / Humanities / Commerce students do not take Pure Science or Science-only BGS (Code 150)
        if (in_array($studentGroup, ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
            if (in_array($subCode, ['136', '137', '138'])) { // Physics, Chemistry, Biology
                return false;
            }
            if ($subCode === '150' && (
                strtolower($mark->subject->studyGroup?->name ?? '') === 'science' || 
                in_array(strtolower($mark->subject->subject_type ?? $mark->subject->type ?? ''), ['group', 'main'])
            )) {
                return false;
            }
        }

        return true;
    });

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
            
            $isComponentFailed = trim($mark->grade) === 'F';
            $mark->marks_obtained = round($cumulativeObtained, 2);
            
            if ($isComponentFailed) {
                $mark->grade = 'F';
                $mark->gpa = 0.00;
            } else {
                $gradeData = $getGradeData(($mark->marks_obtained / $mainMax) * 100, false);
                $mark->grade = $gradeData['grade'];
                $mark->gpa = $gradeData['point'];
            }
        }
    }

    // --- 3. COMBINED SUBJECTS LOGIC & SORTING ---
    $boardSubjectOrder = [
        '101', '102', '107', '108', '109', '127', '150', '111', '112', '154', 
        '153', '140', '110', '126', '136', '137', '138', '134'
    ];

    $marks = $marks->sortBy(function($m) use ($boardSubjectOrder) {
        $code = (string) ($m->subject->code ?? '');
        $idx = array_search($code, $boardSubjectOrder);
        return $idx !== false ? $idx : 999;
    });

    $groupedMarks = [];
    $processedIds = [];

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
            
            // OVERALL PASS RULE INTEGRATION
            $overallPassOnly = ($r1['overall_pass_only'] ?? $mark->subject->overall_pass_only ?? false) || 
                               ($r2['overall_pass_only'] ?? $partnerMark->subject->overall_pass_only ?? false);

            $opm1 = $r1['overall_pass_mark'] ?? $mark->subject->overall_pass_mark ?? 33;
            $opm2 = $r2['overall_pass_mark'] ?? $partnerMark->subject->overall_pass_mark ?? 33;
            $combinedOverallPassMark = $opm1 + $opm2;

            $pass1 = $r1['written_pass_mark'] ?? $mark->subject->written_pass_mark ?? 33;
            $pass2 = $r2['written_pass_mark'] ?? $partnerMark->subject->written_pass_mark ?? 33;
            $combinedRequiredPass = $pass1 + $pass2;

            $mcq1Pass = $r1['mcq_pass_mark'] ?? $mark->subject->mcq_pass_mark ?? 0;
            $mcq2Pass = $r2['mcq_pass_mark'] ?? $partnerMark->subject->mcq_pass_mark ?? 0;
            $combinedMcqObt = ($mark->mcq_mark ?? 0) + ($partnerMark->mcq_mark ?? 0);
            $combinedMcqPass = $mcq1Pass + $mcq2Pass;

            if ($overallPassOnly) {
                $isComponentFailed = ($combinedObt < $combinedOverallPassMark);
            } else {
                $mcqFail = ($combinedMcqPass > 0) && ($combinedMcqObt < $combinedMcqPass);
                $isComponentFailed = ($combinedObt < $combinedRequiredPass) || $mcqFail;
            }

            if ($isComponentFailed) {
                $cGrade = 'F';
                $cGpa = '0.00';
            } else {
                $gradeData = $getGradeData($combinedPerc, false);
                $cGrade = $gradeData['grade'];
                $cGpa = number_format($gradeData['point'], 2);
            }

            $groupedMarks[] = [
                'is_combined' => true,
                'subject_model' => $mark->subject,
                'paper1' => $mark,
                'paper2' => $partnerMark,
                'max1' => $max1,
                'max2' => $max2,
                'combined_max' => $combinedMax,
                'combined_obt' => $combinedObt,
                'combined_grade' => $cGrade,
                'gpa' => $cGpa
            ];
            $processedIds[] = $mark->id;
            $processedIds[] = $partnerMark->id;
        } else {
            $groupedMarks[] = [
                'is_combined' => false,
                'subject_model' => $mark->subject,
                'paper1' => $mark,
                'max1' => $max1,
                'combined_grade' => trim($mark->grade),
                'gpa' => number_format((float)$mark->gpa, 2)
            ];
            $processedIds[] = $mark->id;
        }
    }

    // --- 4. SEPARATE CORE AND 4TH/OPTIONAL SUBJECTS WITH RULES ---
    $coreGroupedMarks = [];
    $optionalGroupedMarks = [];
    $coreGPAs = [];
    $hasCoreFail = false;
    
    $coreTotalObtained = 0.0;
    $optionalBonusMarks = 0.0;
    $optionalBonusPoints = 0.00;

    foreach ($groupedMarks as $gMark) {
        $subName = strtolower($gMark['subject_model']->name ?? '');
        $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');
        $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

        $markVal = (float) ($gMark['is_combined'] ? $gMark['combined_obt'] : $gMark['paper1']->marks_obtained);

        if ($isOptional) {
            $optionalGroupedMarks[] = $gMark;

            $points = (float) $gMark['gpa'];
            if ($points > 2.00) {
                $optionalBonusPoints = $points - 2.00;
            }

            if ($markVal > 40.0) {
                $optionalBonusMarks = $markVal - 40.0;
            }
        } else {
            $coreGroupedMarks[] = $gMark;
            $coreTotalObtained += $markVal;

            if ($gMark['combined_grade'] === 'F') {
                $hasCoreFail = true;
            }
            $coreGPAs[] = (float) $gMark['gpa'];
        }
    }

    $coreCount = count($coreGPAs);
    $gpaWithout4th = ($hasCoreFail || $coreCount === 0) ? '0.00' : number_format(array_sum($coreGPAs) / $coreCount, 2);

    $gpaWith4th = '0.00';
    if (!$hasCoreFail && $coreCount > 0) {
        $rawGpaSum = array_sum($coreGPAs) + $optionalBonusPoints;
        $gpaWith4th = number_format(min(5.00, $rawGpaSum / $coreCount), 2);
    }

    $hasFailed = $hasCoreFail; 
    $failedSubjectsCount = count(array_filter($coreGroupedMarks, fn($g) => $g['combined_grade'] === 'F'));
    $totalObtained = $coreTotalObtained + $optionalBonusMarks;

    // --- 5. MERIT POSITION ---
    $meritPosition = '--';
    $peerTotals = \App\Models\Mark::where('academic_year_id', $enrollment->academic_year_id)
        ->where('school_class_id', $enrollment->school_class_id)
        ->where('exam_id', $exam->id)
        ->select('student_id', \DB::raw('SUM(marks_obtained) as aggregate_score'))
        ->groupBy('student_id')
        ->orderBy('aggregate_score', 'DESC')
        ->get();

    $rankIndex = $peerTotals->search(fn($item) => $item->student_id == $enrollment->user_id);
    if ($rankIndex !== false) {
        $meritPosition = $rankIndex + 1;
    }

    // --- 6. QR CODE ---
    $finalGPA = $has4thSubjectColumn ? $gpaWith4th : $gpaWithout4th;

    $getGradeFromGPA = function($gpaVal, $hasFailed) {
        if ($hasFailed || (float)$gpaVal < 1.00) return 'F';
        $g = (float)$gpaVal;
        if ($g >= 5.00) return 'A+';
        if ($g >= 4.00) return 'A';
        if ($g >= 3.50) return 'A-';
        if ($g >= 3.00) return 'B';
        if ($g >= 2.00) return 'C';
        if ($g >= 1.00) return 'D';
        return 'F';
    };

    $finalGrade = $getGradeFromGPA($finalGPA, $hasCoreFail);

    $qrPayload = "User ID: " . ($enrollment->user->student_id ?? 'N/A') . "\n"
        . "Name: " . strtoupper($enrollment->user->name) . "\n"
        . "Class: " . $enrollment->schoolClass->name . "\n"
        . "Roll: " . $enrollment->roll_number . "\n"
        . "Grand Total: " . number_format($totalObtained, 1) . "\n"
        . "GPA: " . $finalGPA . "\n"
        . "Grade: " . $finalGrade;

    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=" . urlencode($qrPayload);
@endphp

<head>
    <style>
        body {
            font-family: 'SolaimanLipi', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #000;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .mark-sheet {
            width: 100%;
            background: #fff;
            border: 3px solid #e6c84b;
            padding: 8px;
            box-sizing: border-box;
        }
        .inner-border {
            border: 1.5px solid #e6c84b;
            padding: 10px;
        }
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
        }
        .layout-table td {
            border: none !important;
            padding: 0;
            vertical-align: top;
        }
        .school-info {
            text-align: center;
            padding: 0 6px;
        }
        .school-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin: 0 auto 3px auto;
            display: block;
        }
        .school-logo-fallback {
            width: 40px;
            height: 40px;
            margin: 0 auto 3px auto;
            background: #4CAF50;
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            color: #fff;
            font-weight: bold;
            font-size: 20px;
            border: 1.5px solid #e6c84b;
        }
        .school-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            letter-spacing: 0.5px;
            line-height: 1.15;
        }
        .school-address {
            font-size: 10.5px;
            color: #555;
            margin-top: 2px;
        }
        .sheet-title {
            font-size: 12.5px;
            font-weight: 700;
            color: #333;
            margin-top: 4px;
            text-transform: uppercase;
        }
        .academic-year {
            font-size: 10.5px;
            color: #555;
            margin-top: 2px;
        }
        .grade-table {
            border-collapse: collapse;
            font-size: 9px;
            width: 130px;
            float: right;
        }
        .grade-table th {
            background: #333;
            color: #fff;
            padding: 2px 4px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #333;
        }
        .grade-table td {
            border: 1px solid #333;
            padding: 1.5px 4px;
            text-align: center;
            font-size: 8.5px;
        }
        .info-col-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-col-table td {
            padding: 2.5px 1px;
            font-size: 10px;
            vertical-align: baseline;
        }
        .info-label {
            width: 95px;
            font-weight: 600;
            color: #333;
        }
        .info-value {
            border-bottom: 1px solid #333;
        }
        
        .table-transcript-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .table-transcript-grid th, .table-transcript-grid td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            font-size: 9px;
            vertical-align: middle;
            line-height: 1.15;
        }
        .table-transcript-grid th {
            background: #f2f2f2;
            font-weight: bold;
            font-size: 8.5px;
        }
        .table-transcript-grid .subject-name {
            text-align: left;
            padding-left: 5px;
            font-weight: 600;
            font-size: 9px;
            white-space: nowrap;
        }
        .table-transcript-grid .section-divider-row td {
            background: #f5f5f5;
            font-weight: bold;
            text-align: left;
            padding-left: 6px;
            font-size: 9.5px;
            color: #333;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding-top: 2.5px;
            padding-bottom: 2.5px;
        }

        .table-transcript-grid .grand-total-row td {
            background: #e2e8f0 !important;
            font-weight: 800 !important;
            font-size: 10px !important;
            border-top: 2px solid #000 !important;
            border-bottom: 2px solid #000 !important;
            padding: 4px 2px !important;
        }

        .right-side-merged-cell {
            font-size: 11.5px;
            font-weight: bold;
            vertical-align: middle;
            background: #fff;
        }
        .summary-block-matrix {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .summary-block-matrix td {
            border: 1px solid #333 !important;
            padding: 3.5px 6px;
            font-size: 9.5px;
        }
        .summary-lbl {
            background: #f5f5f5;
            font-weight: bold;
            width: 120px;
        }

        .eval-matrix-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .eval-matrix-table th {
            background: #f5f5f5;
            border: 1px solid #333;
            padding: 3px;
            font-size: 9.5px;
            font-weight: bold;
            text-align: center;
        }
        .eval-matrix-table td {
            border: 1px solid #333;
            padding: 2.5px 5px;
            font-size: 9px;
        }
        .eval-checkbox {
            width: 22px;
            text-align: center;
            font-weight: bold;
        }

        .comments-box {
            border: 1px solid #333;
            padding: 6px;
            font-size: 9.5px;
            min-height: 48px;
            margin-top: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        .signatures-table {
            width: 100%;
            margin-top: 45px;
            border-collapse: collapse;
        }
        .signatures-table td {
            text-align: center;
            font-size: 9.5px;
            color: #333;
            font-weight: 600;
            width: 33.33%;
            vertical-align: top;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 150px;
            margin: 0 auto;
            padding-top: 3px;
        }
        .footer-table {
            width: 100%;
            margin-top: 12px;
            border-top: 1px solid #ddd;
            padding-top: 3px;
            font-size: 8.5px;
            color: #666;
        }
        .highlight-green { color: #2e7d32; font-weight: 700; }
        .highlight-blue { color: #1565c0; font-weight: 700; }
        .highlight-teal { color: #00897b; font-weight: 700; }
        .grade-f { color: #dc2626; font-weight: bold; }

        .qr-code-wrapper {
            border: 1px solid #333;
            padding: 2px;
            background: #fff;
            display: inline-block;
            text-align: center;
        }
        .qr-code-img {
            width: 78px;
            height: 78px;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="mark-sheet">
  <div class="inner-border">

    <table class="layout-table" style="margin-bottom: 6px;">
      <tr>
      <td style="width: 70px;">
          @if(!empty($studentPhoto))
              <img src="{{ $studentPhoto }}" alt="Student Photo" style="width: 60px; height: 72px; object-fit: cover; border: 1px solid #333; display: block;">
          @else
              <div style="width:60px; height:72px; border:1px solid #ccc; text-align:center; font-size:8px; padding-top:18px;">Paste<br>Photo<br>Here</div>
          @endif
      </td>
        <td class="school-info">
          @if(!empty($logoUrl))
              <img src="{{ $logoUrl }}" class="school-logo" alt="Logo">
          @else
              <div class="school-logo-fallback">{{ substr($schoolName, 0, 1) }}</div>
          @endif

          <div class="school-name">{{ $schoolName }}</div>
          <div class="school-address">{{ $schoolAddress }}</div>
          <div class="sheet-title">{{ $exam->name ?? 'Mid Term' }} Mark Sheet</div>
          <div class="academic-year">Academic Year: {{ $enrollment->academicYear->name }}</div>
        </td>
        <td style="width: 130px;">
          <table class="grade-table">
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

    <table class="layout-table" style="margin-top: 6px; margin-bottom: 6px;">
      <tr>
        <td style="width: 41%;">
          <table class="info-col-table">
            <tr><td class="info-label">Student Name:</td><td class="info-value">{{ strtoupper($enrollment->user->name) }}</td></tr>
            <tr><td class="info-label">Father's Name:</td><td class="info-value">_______________________</td></tr>
            <tr><td class="info-label">Mother's Name:</td><td class="info-value">_______________________</td></tr>
            <tr><td class="info-label">Class:</td><td class="info-value">{{ $enrollment->schoolClass->name }}</td></tr>
            <tr><td class="info-label">Section:</td><td class="info-value">{{ $enrollment->section->name ?? 'N/A' }}</td></tr>
            <tr><td class="info-label">Section Roll:</td><td class="info-value">{{ $enrollment->roll_number }}</td></tr>
          </table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 38%;">
          <table class="info-col-table">
            <tr><td class="info-label">Student ID:</td><td class="info-value">{{ $enrollment->user->student_id ?? 'N/A' }}</td></tr>
            <tr><td class="info-label">Shift:</td><td class="info-value">Day</td></tr>
            <tr><td class="info-label">Student Type:</td><td class="info-value">Regular</td></tr>
            <tr><td class="info-label">Medium:</td><td class="info-value">{{ ($settings->medium ?? 'Bangla') }}</td></tr>
            <tr><td class="info-label">Department:</td><td class="info-value">{{ ($enrollment->study_group ?? 'General') }}</td></tr>
            <tr><td class="info-label">Exam Year:</td><td class="info-value">{{ $enrollment->academicYear->name }}</td></tr>
          </table>
        </td>
        <td style="width: 19%; text-align: right; vertical-align: middle;">
          <div class="qr-code-wrapper">
            <img src="{{ $qrCodeUrl }}" alt="Verification QR Code" class="qr-code-img">
          </div>
        </td>
      </tr>
    </table>

    <table class="table-transcript-grid">
      <thead>
        <tr>
          <th rowspan="2" style="width: 25%;">Name of Subjects</th>
          <th colspan="2">WRITTEN</th>
          <th colspan="2">MCQ</th>
          <th colspan="2">PRACTICAL</th>
          <th style="width: 8%;">TOTAL MARKS</th>
          <th colspan="2">COMBINED</th>
          <th rowspan="2" style="width: 5%;">GP</th>
          <th rowspan="2" style="width: 6%;">Grade</th>

          @if($has4thSubjectColumn)
            <th rowspan="2" style="width: 9%;">GPA(Without 4th Subject)</th>
          @endif

          <th rowspan="2" style="width: 6%;">GPA</th>
        </tr>
        <tr class="sub-header">
            <th>Full</th><th>Obt</th>
            <th>Full</th><th>Obt</th>
            <th>Full</th><th>Obt</th>
            <th>Obt</th>
            <th>Max</th><th>Obt</th>
        </tr>
      </thead>
      <tbody>
        @php
          $hasRenderedSideAggregateBlock = false;
          $totalSubjectRowsCount = count($coreGroupedMarks) + count(collect($coreGroupedMarks)->where('is_combined', true)) + count($optionalGroupedMarks);
          if(count($optionalGroupedMarks) > 0) { $totalSubjectRowsCount += 1; }
        @endphp

        @foreach($coreGroupedMarks as $group)
          @php
            $rules1 = $group['subject_model']->getMarksForExam($exam->id);
            $writtenMax = $rules1['written_total'] ?? 100;
            $mcqMax = $rules1['mcq_total'] ?? 0;
            $practicalMax = $rules1['practical_total'] ?? 0;
          @endphp

          @if($group['is_combined'])
            @php
              $rules2 = $group['paper2']->subject->getMarksForExam($exam->id);
              $writtenMax2 = $rules2['written_total'] ?? 100;
              $mcqMax2 = $rules2['mcq_total'] ?? 0;
              $practicalMax2 = $rules2['practical_total'] ?? 0;
            @endphp
            <tr>
              <td class="subject-name">{{ $formatSubjectWithCode($group['paper1']->subject) }}</td>
              <td>{{ $writtenMax }}</td><td>{{ number_format($group['paper1']->written_mark, 1) }}</td>
              <td>{{ $mcqMax > 0 ? $mcqMax : '--' }}</td><td>{{ $mcqMax > 0 ? number_format($group['paper1']->mcq_mark, 1) : '0.0' }}</td>
              <td>{{ $practicalMax > 0 ? $practicalMax : '--' }}</td><td>{{ $practicalMax > 0 ? number_format($group['paper1']->practical_mark, 1) : '0.0' }}</td>
              <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>

              <td rowspan="2">{{ number_format($group['combined_max'], 0) }}</td>
              <td rowspan="2" style="font-weight: bold;">{{ number_format($group['combined_obt'], 1) }}</td>
              <td rowspan="2" style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $group['gpa'] }}</td>
              <td rowspan="2" style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $group['combined_grade'] }}</td>

              @if(!$hasRenderedSideAggregateBlock)
                @if($has4thSubjectColumn)
                  <td rowspan="{{ $totalSubjectRowsCount }}" class="right-side-merged-cell highlight-blue" style="border-left: 1.5px solid #333;">
                    {{ $gpaWithout4th }}
                  </td>
                @endif
                <td rowspan="{{ $totalSubjectRowsCount }}" class="right-pinned-gpa-cell right-side-merged-cell highlight-teal" style="{{ !$has4thSubjectColumn ? 'border-left: 1.5px solid #333;' : '' }}">
                  {{ $has4thSubjectColumn ? $gpaWith4th : $gpaWithout4th }}
                </td>
                @php $hasRenderedSideAggregateBlock = true; @endphp
              @endif
            </tr>
            <tr>
              <td class="subject-name">{{ $formatSubjectWithCode($group['paper2']->subject) }}</td>
              <td>{{ $writtenMax2 }}</td><td>{{ number_format($group['paper2']->written_mark, 1) }}</td>
              <td>{{ $mcqMax2 > 0 ? $mcqMax2 : '--' }}</td><td>{{ $mcqMax2 > 0 ? number_format($group['paper2']->mcq_mark, 1) : '0.0' }}</td>
              <td>{{ $practicalMax2 > 0 ? $practicalMax2 : '--' }}</td><td>{{ $practicalMax2 > 0 ? number_format($group['paper2']->practical_mark, 1) : '0.0' }}</td>
              <td>{{ number_format($group['paper2']->marks_obtained, 1) }}</td>
            </tr>
          @else
            <tr>
              <td class="subject-name">{{ $formatSubjectWithCode($group['paper1']->subject) }}</td>
              <td>{{ $writtenMax }}</td><td>{{ number_format($group['paper1']->written_mark, 1) }}</td>
              <td>{{ $mcqMax > 0 ? $mcqMax : '--' }}</td><td>{{ $mcqMax > 0 ? number_format($group['paper1']->mcq_mark, 1) : '0.0' }}</td>
              <td>{{ $practicalMax > 0 ? $practicalMax : '--' }}</td><td>{{ $practicalMax > 0 ? number_format($group['paper1']->practical_mark, 1) : '0.0' }}</td>
              <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>

              <td>{{ number_format($group['max1'], 0) }}</td><td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
              <td style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : '' }}">{{ $group['gpa'] }}</td>
              <td style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $group['combined_grade'] }}</td>

              @if(!$hasRenderedSideAggregateBlock)
                @if($has4thSubjectColumn)
                  <td rowspan="{{ $totalSubjectRowsCount }}" class="right-side-merged-cell highlight-blue" style="border-left: 1.5px solid #333;">
                    {{ $gpaWithout4th }}
                  </td>
                @endif
                <td rowspan="{{ $totalSubjectRowsCount }}" class="right-pinned-gpa-cell right-side-merged-cell highlight-teal" style="{{ !$has4thSubjectColumn ? 'border-left: 1.5px solid #333;' : '' }}">
                  {{ $has4thSubjectColumn ? $gpaWith4th : $gpaWithout4th }}
                </td>
                @php $hasRenderedSideAggregateBlock = true; @endphp
              @endif
            </tr>
          @endif
        @endforeach

        @if(count($optionalGroupedMarks) > 0)
          @php $colSpanCount = $has4thSubjectColumn ? 13 : 12; @endphp
          <tr class="section-divider-row">
              <td colspan="{{ $colSpanCount }}">Optional / 4th Subject</td>
          </tr>
          @foreach($optionalGroupedMarks as $group)
            @php
              $rules1 = $group['subject_model']->getMarksForExam($exam->id);
              $writtenMax = $rules1['written_total'] ?? 100;
              $mcqMax = $rules1['mcq_total'] ?? 0;
              $practicalMax = $rules1['practical_total'] ?? 0;
            @endphp
            <tr>
              <td class="subject-name">{{ $formatSubjectWithCode($group['paper1']->subject) }}</td>
              <td>{{ $writtenMax }}</td><td>{{ number_format($group['paper1']->written_mark, 1) }}</td>
              <td>{{ $mcqMax > 0 ? $mcqMax : '--' }}</td><td>{{ $mcqMax > 0 ? number_format($group['paper1']->mcq_mark, 1) : '0.0' }}</td>
              <td>{{ $practicalMax > 0 ? $practicalMax : '--' }}</td><td>{{ $practicalMax > 0 ? number_format($group['paper1']->practical_mark, 1) : '0.0' }}</td>
              <td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>

              <td>{{ number_format($group['max1'], 0) }}</td><td>{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
              <td style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : '' }}">{{ $group['gpa'] }}</td>
              <td style="font-weight: bold;" class="{{ $group['combined_grade'] === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $group['combined_grade'] }}</td>
            </tr>
          @endforeach
        @endif

        <tr class="grand-total-row">
          <td class="subject-name" style="text-align: left; padding-left: 5px;">Grand Total / Grade</td>
          <td colspan="6"></td>
          <td style="font-weight: 900; font-size: 10px;">{{ number_format($totalObtained, 1) }}</td>
          <td colspan="2"></td>
          <td>--</td>
          <td class="{{ $finalGrade === 'F' ? 'grade-f' : 'highlight-green' }}" style="font-size: 11px; font-weight: 900;">{{ $finalGrade }}</td>
        </tr>
      </tbody>
    </table>

    <table class="layout-table" style="margin-top: 6px; margin-bottom: 6px;">
      <tr>
        <td style="width: 49%;">
          <table class="summary-block-matrix">
            <tr><td class="summary-lbl">Result Status</td><td style="font-weight: bold;" class="{{ $hasFailed ? 'grade-f' : 'grade-pass' }}">{{ $hasFailed ? 'FAILED' : 'PASSED' }}</td></tr>
            <tr><td class="summary-lbl">Publish Date</td><td style="font-weight: bold; color: #444;">{{ date('d-m-Y') }}</td></tr>
            <tr><td class="summary-lbl">Merit Position</td><td style="font-weight: bold;" class="highlight-blue">{{ $meritPosition }}</td></tr>
          </table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%;">
          <table class="summary-block-matrix">
            <tr><td class="summary-lbl">Failed Subject(s)</td><td style="font-weight: bold; color: {{ $hasFailed ? 'red' : 'green' }}">{{ $failedSubjectsCount }}</td></tr>
            <tr><td class="summary-lbl">Working Days</td><td></td></tr>
            <tr><td class="summary-lbl">Present Days</td><td></td></tr>
          </table>
        </td>
      </tr>
    </table>

    <table class="layout-table" style="margin-top: 6px; margin-bottom: 6px;">
      <tr>
        <td style="width: 49%;">
          <table class="eval-matrix-table">
            <thead>
              <tr><th colspan="2">Moral & Behavior</th></tr>
            </thead>
            <tbody>
              <tr><td class="eval-checkbox"></td><td>Best</td></tr>
              <tr><td class="eval-checkbox"></td><td>Better</td></tr>
              <tr><td class="eval-checkbox"></td><td>Good</td></tr>
              <tr><td class="eval-checkbox"></td><td>Need Improvement</td></tr>
            </tbody>
          </table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%;">
          <table class="eval-matrix-table">
            <thead>
              <tr><th colspan="2">Co-Curricular Activities</th></tr>
            </thead>
            <tbody>
              <tr><td class="eval-checkbox"></td><td>Sports</td></tr>
              <tr><td class="eval-checkbox"></td><td>Cultural Function</td></tr>
              <tr><td class="eval-checkbox"></td><td>Scout</td></tr>
              <tr><td class="eval-checkbox"></td><td>Math Olympiad</td></tr>
            </tbody>
          </table>
        </td>
      </tr>
    </table>

    <div class="comments-box">
      <div style="font-weight: bold; color: #333;">Comments / Remarks:</div>
    </div>

    <table class="signatures-table">
      <tr>
        <td><div class="signature-line">Guardian's Signature</div></td>
        <td><div class="signature-line">Class Teacher's Signature</div></td>
        <td><div class="signature-line">Principal / Head Teacher</div></td>
      </tr>
    </table>

    <table class="layout-table footer-table">
      <tr>
        <td style="text-align: left;">Powered by EduSphere</td>
        <td style="text-align: right;">Generated Date: {{ date('d-m-Y H:i') }}</td>
      </tr>
    </table>

  </div>
</div>

</body>
</html>