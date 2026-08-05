<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mid Term Mark Sheet - {{ $enrollment->user->name }}</title>
@php
    $settings = \App\Models\SiteSetting::first() ?? \App\Models\Setting::first();

    // 🌟 DYNAMIC LOGO FETCH 🌟
    $logoUrl = ($settings && !empty($settings->logo))
        ? \Illuminate\Support\Facades\Storage::url($settings->logo)
        : null;

    $schoolName = !empty($settings?->school_name_en)
        ? $settings->school_name_en
        : 'Shankarbati High School';

    $schoolAddress = !empty($settings?->address_en)
        ? $settings->address_en
        : 'Chapai Nawabganj, Bangladesh';

        // 🌟 DYNAMIC STUDENT PHOTO FETCH 🌟
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

    // Logic to determine if class is junior or senior
    $className = strtolower($enrollment->schoolClass->name ?? '');
    $has4thSubjectColumn = str_contains($className, '9') || str_contains($className, '10');

    // 🌟 1. DYNAMIC GRADE AND FAIL CHECKER FUNCTIONS 🌟
    $getGradeData = function($percentage, $isComponentFailed = false) {
        return \App\Models\GradeScale::getGradeForMark($percentage, $isComponentFailed);
    };

    // 🌟 FULLY CORRECTED COMPONENT FAIL CHECKER (SUPPORTS EXAM OVERRIDES & DB GRADES) 🌟
    $checkComponentFail = function($markObj, $examId) {
        if (!$markObj || !$markObj->subject) return false;
        
        // 1. Trust the database: If Marks Entry already saved an 'F', enforce it.
        if (trim($markObj->grade) === 'F') return true;

        // 2. Fetch specific exam rules (supports Custom Exam Rules overrides)
        $subject = $markObj->subject;
        $rules = $subject->getMarksForExam($examId); 
        
        $overallPass = $rules['overall_pass_rule'] ?? $subject->overall_pass_rule ?? false;
        if ($overallPass) return false;
        
        $wPass = $rules['written_pass'] ?? $subject->written_pass ?? 0;
        $mPass = $rules['mcq_pass'] ?? $subject->mcq_pass ?? 0;
        $pPass = $rules['practical_pass'] ?? $subject->practical_pass ?? 0;
        
        $wFail = ($wPass > 0) && ((float)$markObj->written_mark < (float)$wPass);
        $mFail = ($mPass > 0) && ((float)$markObj->mcq_mark < (float)$mPass);
        $pFail = ($pPass > 0) && ((float)$markObj->practical_mark < (float)$pPass);
        
        return $wFail || $mFail || $pFail;
    };

    $getHighest = function($subjectId, $column) use ($marks, $exam) {
        if ($marks->isEmpty()) return '--';
        $sampleMark = $marks->first();
        $highest = $sampleMark->newQuery()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subjectId)
            ->max($column);
        return ($highest !== null && $highest > 0) ? number_format($highest, 1) : '--';
    };

    $formatSubjectWithCode = function($subjectModel) {
        return $subjectModel->name;
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

    // --- 3. COMBINED SUBJECTS LOGIC ---
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
            
            // 🌟 Trust the DB: If either paper failed components, the combined fails
            $isComponentFailed = (trim($mark->grade) === 'F' || trim($partnerMark->grade) === 'F');
            
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
            // 🌟 ULTIMATE FIX: 100% BLIND TRUST IN THE DATABASE FOR SINGLE SUBJECTS 🌟
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

    $totalMax = $marks->sum(function($m) use ($exam) {
        $r = $m->subject->getMarksForExam($exam->id);
        return $r['full_marks'] > 0 ? $r['full_marks'] : 100;
    });
    $totalObtained = $marks->sum('marks_obtained');
    
    // Dynamic failure calculation based on strictly evaluated grouped marks
    $failedSubjectsCount = count(array_filter($groupedMarks, fn($g) => $g['combined_grade'] === 'F'));
    $hasFailed = $failedSubjectsCount > 0;

    // --- 4. SEPARATE CORE AND 4TH/OPTIONAL SUBJECTS ---
    $coreGroupedMarks = [];
    $optionalGroupedMarks = [];
    $coreGPAs = [];
    $hasCoreFail = false;

    foreach ($groupedMarks as $gMark) {
        $subName = strtolower($gMark['subject_model']->name ?? '');
        $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');

        if (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional') {
            $optionalGroupedMarks[] = $gMark;
        } else {
            $coreGroupedMarks[] = $gMark;
            if ($gMark['combined_grade'] === 'F') {
                $hasCoreFail = true;
            }
            $coreGPAs[] = (float) $gMark['gpa'];
        }
    }

    $coreCount = count($coreGPAs);
    $gpaWithout4th = ($hasCoreFail || $coreCount === 0) ? '0.00' : number_format(array_sum($coreGPAs) / $coreCount, 2);

    $gpaWith4th = '0.00';
    if (!$hasFailed && count($groupedMarks) > 0) {
        $rawGpaSum = 0;
        foreach ($groupedMarks as $gMark) {
            $subName = strtolower($gMark['subject_model']->name ?? '');
            $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');
            if (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional') {
                $points = (float) $gMark['gpa'];
                if ($points > 2.00) $rawGpaSum += ($points - 2.00);
            } else {
                $rawGpaSum += (float) $gMark['gpa'];
            }
        }
        $gpaWith4th = number_format(min(5.00, $rawGpaSum / $coreCount), 2);
    }

    // --- 5. DYNAMIC MERIT POSITION RANKING CALCULATOR ENGINE ---
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

    // --- 🌟 6. GENERATE QR CODE PAYLOAD 🌟 ---
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

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Mid Term Mark Sheet - {{ $enrollment->user->name }}</title>
    <style>
        body {
            font-family: 'SolaimanLipi', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #000;
            font-size: 12px;
            padding: 5px;
        }
        .mark-sheet {
            width: 100%;
            background: #fff;
            border: 4px solid #e6c84b;
            padding: 12px;
        }
        .inner-border {
            border: 2px solid #e6c84b;
            padding: 16px;
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
        .student-photo {
            width: 80px;
            height: 100px;
            border: 1px solid #ccc;
            text-align: center;
            font-size: 10px;
            color: #333;
            padding-top: 25px;
        }
        .school-info {
            text-align: center;
            padding: 0 10px;
        }
        .school-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin: 0 auto 4px auto;
            display: block;
        }
        .school-logo-fallback {
            width: 40px;
            height: 40px;
            margin: 0 auto 4px auto;
            background: #4CAF50;
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            color: #fff;
            font-weight: bold;
            font-size: 20px;
            border: 2px solid #e6c84b;
        }
        .school-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            letter-spacing: 0.5px;
        }
        .school-address {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }
        .sheet-title {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            margin-top: 6px;
            text-transform: uppercase;
        }
        .academic-year {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }
        .grade-table {
            border-collapse: collapse;
            font-size: 10px;
            width: 140px;
            float: right;
        }
        .grade-table th {
            background: #333;
            color: #fff;
            padding: 3px 6px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #333;
        }
        .grade-table td {
            border: 1px solid #333;
            padding: 2px 6px;
            text-align: center;
            font-size: 9px;
        }
        .grade-table tr:nth-child(even) td {
            background: #f9f9f9;
        }
        .info-col-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-col-table td {
            padding: 3.5px 2px;
            font-size: 10px;
            vertical-align: baseline;
        }
        .info-label {
            width: 110px;
            font-weight: 600;
            color: #333;
        }
        .info-value {
            border-bottom: 1px solid #333;
        }
        .table-transcript-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .table-transcript-grid th, .table-transcript-grid td {
            border: 1px solid #000;
            padding: 4px 1px;
            text-align: center;
            font-size: 10px;
            vertical-align: middle;
        }
        .table-transcript-grid th {
            background: #f2f2f2;
            font-weight: bold;
            font-size: 9.5px;
        }
        .table-transcript-grid .subject-name {
            text-align: left;
            padding-left: 5px;
            font-weight: 600;
            font-size: 10.5px;
        }
        .table-transcript-grid .section-divider-row td {
            background: #f5f5f5;
            font-weight: bold;
            text-align: left;
            padding-left: 8px;
            font-size: 11px;
            color: #333;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .table-transcript-grid .grand-total-row td {
            background: #e2e8f0 !important;
            font-weight: 800 !important;
            font-size: 11px !important;
            border-top: 2.5px solid #000 !important;
            border-bottom: 2.5px solid #000 !important;
            padding: 6px 2px !important;
        }

        .right-side-merged-cell {
            font-size: 13px;
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
            padding: 5px 10px;
            font-size: 11.5px;
        }
        .summary-lbl {
            background: #f5f5f5;
            font-weight: bold;
            width: 145px;
        }
        .comments-box {
            border: 1px solid #333;
            padding: 8px;
            font-size: 12px;
            min-height: 45px;
            margin-top: 15px;
            width: 100%;
        }
        .signatures-table {
            width: 100%;
            margin-top: 55px;
            border-collapse: collapse;
        }
        .signatures-table td {
            text-align: center;
            font-size: 11px;
            color: #333;
            font-weight: 600;
            width: 33.33%;
            vertical-align: top;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 170px;
            margin: 0 auto;
            padding-top: 4px;
        }
        .footer-table {
            width: 100%;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 4px;
            font-size: 10px;
            color: #666;
        }
        .highlight-green { color: #2e7d32; font-weight: 700; }
        .highlight-blue { color: #1565c0; font-weight: 700; }
        .highlight-teal { color: #00897b; font-weight: 700; }
        .grade-f { color: #dc2626; font-weight: bold; }

        /* 🌟 QR CODE BOX STYLING 🌟 */
        .qr-code-wrapper {
            border: 1.5px solid #333;
            padding: 4px;
            background: #fff;
            display: inline-block;
            text-align: center;
        }
        .qr-code-img {
            width: 95px;
            height: 95px;
            display: block;
            margin: 0 auto;
        }
        .qr-caption {
            font-size: 8px;
            font-weight: bold;
            color: #333;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="mark-sheet">
  <div class="inner-border">

    <table class="layout-table" style="margin-bottom: 10px;">
      <tr>
      <!-- ✅ AFTER: Dynamic Student Photo with Fallback -->
      <td style="width: 70px;">
          @if(!empty($studentPhoto))
              <img src="{{ $studentPhoto }}" alt="Student Photo" style="width: 65px; height: 75px; object-fit: cover; border: 1px solid #333; display: block;">
          @else
              <div class="photo-box">Paste<br>Photo<br>Here</div>
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
        <td style="width: 145px;">
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

    <table class="layout-table" style="margin-top: 15px; margin-bottom: 10px;">
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
        <!-- 🌟 DYNAMIC QR CODE CONTAINER (RED HIGHLIGHTED SPOT) 🌟 -->
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
          <tr class="section-divider-row">
              <td colspan="8">Optional / 4th Subject</td>
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
          <td class="subject-name" style="text-align: left; padding-left: 8px;">Grand Total / Grade</td>
          <td colspan="6"></td>
          <td style="font-weight: 900; font-size: 11px;">{{ number_format($totalObtained, 1) }}</td>
          <td colspan="2"></td>
          <td>--</td>
          <td class="{{ $finalGrade === 'F' ? 'grade-f' : 'highlight-green' }}" style="font-size: 12px; font-weight: 900;">{{ $finalGrade }}</td>
        </tr>
      </tbody>
    </table>

    <table class="layout-table" style="margin-top: 15px; margin-bottom: 10px;">
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