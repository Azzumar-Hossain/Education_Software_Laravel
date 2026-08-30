<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Final Mark Sheet - {{ $enrollment->user->name }}</title>
@php
    $settings = \App\Models\SiteSetting::first() ?? \App\Models\Setting::first();
    $schoolName = !empty($settings?->school_name_en) ? $settings->school_name_en : 'Harimohan Govt. High School';
    $schoolAddress = !empty($settings?->address_en) ? $settings->address_en : 'Kathal Bagicha, Chapai Nawabganj';

    $studentPhoto = null;
    $rawPhotoPath = $enrollment->user->avatar ?? $enrollment->user->photo ?? null;
    if ($rawPhotoPath) {
        $studentPhoto = file_exists(storage_path('app/public/' . $rawPhotoPath))
            ? asset('storage/' . $rawPhotoPath)
            : \Illuminate\Support\Facades\Storage::url($rawPhotoPath);
    }

    $getGradeData = function($percentage, $isComponentFailed = false) {
        return \App\Models\GradeScale::getGradeForMark($percentage, $isComponentFailed);
    };

    $studentExams = $allMarks->pluck('exam')->filter()->unique('id')->sortBy('id')->values();
    $mainExams = $studentExams->filter(fn($ex) => empty($ex->parent_exam_id))->values();
    if ($mainExams->isEmpty()) {
        $mainExams = $studentExams;
    }
    $exam1 = $mainExams->first();
    $exam2 = $mainExams->count() > 1 ? $mainExams->skip(1)->first() : null;

    $studentGroup = strtolower($enrollment->study_group ?? '');

    $filteredMarks = $allMarks->filter(function($mark) use ($studentGroup) {
        if (!$mark->subject) return false;
        $subCode = (string) ($mark->subject->code ?? '');
        $subName = strtolower($mark->subject->name ?? '');

        if ($studentGroup === 'science' && ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান'))) return false;
        if (in_array($studentGroup, ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
            if (in_array($subCode, ['136', '137', '138'])) return false;
            if ($subCode === '150' && (strtolower($mark->subject->studyGroup?->name ?? '') === 'science' || in_array(strtolower($mark->subject->subject_type ?? $mark->subject->type ?? ''), ['group', 'main']))) return false;
        }
        return true;
    });

    $uniqueSubjects = $filteredMarks->pluck('subject')->unique('id')->sortBy('code');
    $subjectGroups = [];
    $processedIds = [];

    foreach($uniqueSubjects as $subject) {
        if(in_array($subject->id, $processedIds)) continue;

        $partner = null;
        if ($subject->linked_subject_id) {
            $partner = $uniqueSubjects->where('id', $subject->linked_subject_id)->first();
        } else {
            $partner = $uniqueSubjects->where('linked_subject_id', $subject->id)->first();
        }

        $subjectGroups[] = [
            'is_combined' => $partner ? true : false,
            'paper1' => $subject,
            'paper2' => $partner
        ];
        $processedIds[] = $subject->id;
        if ($partner) $processedIds[] = $partner->id;
    }

    $coreGroups = [];
    $optionalGroups = [];

    foreach($subjectGroups as $group) {
        $subName = strtolower($group['paper1']->name);
        $subType = strtolower($group['paper1']->subject_type ?? $group['paper1']->type ?? '');
        $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional' || (int)$enrollment->optional_subject_id === (int)$group['paper1']->id);

        if ($isOptional) {
            $optionalGroups[] = $group;
        } else {
            $coreGroups[] = $group;
        }
    }

    $evalTerm = function($p1, $p2, $exam, $isCombined) use ($filteredMarks, $getGradeData) {
        $empty = [ 'm1' => null, 'r1' => [], 'm2' => null, 'r2' => [], 'total_max' => 0, 'total_obt' => 0, 'grade' => '-', 'gpa' => '-', 'failed' => false ];
        if (!$exam) return $empty;

        $m1 = $filteredMarks->where('subject_id', $p1->id)->where('exam_id', $exam->id)->first();
        $r1 = $p1->getMarksForExam($exam->id);
        if (!is_array($r1)) $r1 = [];

        $m2 = $isCombined && $p2 ? $filteredMarks->where('subject_id', $p2->id)->where('exam_id', $exam->id)->first() : null;
        $r2 = [];
        if ($isCombined && $p2) {
            $r2 = $p2->getMarksForExam($exam->id);
            if (!is_array($r2)) $r2 = [];
        }

        if (!$m1 && !$m2) return $empty;

        $max1 = isset($r1['full_marks']) && $r1['full_marks'] > 0 ? $r1['full_marks'] : 100;
        
        $obt1 = 0;
        if ($m1) {
            $childExams = \App\Models\Exam::where('parent_exam_id', $exam->id)->get();
            if ($childExams->count() > 0) {
                $cWeight = $childExams->sum('contribution_percentage');
                $mWeight = 100 - $cWeight;
                $obt1 += ($m1->marks_obtained / $max1) * ($max1 * ($mWeight / 100));
                
                foreach($childExams as $cExam) {
                    $cMark = \App\Models\Mark::where('exam_id', $cExam->id)->where('student_id', $m1->student_id)->where('subject_id', $m1->subject_id)->first();
                    if ($cMark) {
                        $cRules = $cMark->subject->getMarksForExam($cExam->id);
                        $cMax = isset($cRules['full_marks']) && $cRules['full_marks'] > 0 ? $cRules['full_marks'] : 100;
                        $obt1 += ($cMark->marks_obtained / $cMax) * ($max1 * ($cExam->contribution_percentage / 100));
                    }
                }
            } else {
                $obt1 = (float)$m1->marks_obtained;
            }
        }

        if ($isCombined && $p2) {
            $max2 = isset($r2['full_marks']) && $r2['full_marks'] > 0 ? $r2['full_marks'] : 100;
            
            $obt2 = 0;
            if ($m2) {
                $childExams = \App\Models\Exam::where('parent_exam_id', $exam->id)->get();
                if ($childExams->count() > 0) {
                    $cWeight = $childExams->sum('contribution_percentage');
                    $mWeight = 100 - $cWeight;
                    $obt2 += ($m2->marks_obtained / $max2) * ($max2 * ($mWeight / 100));
                    
                    foreach($childExams as $cExam) {
                        $cMark = \App\Models\Mark::where('exam_id', $cExam->id)->where('student_id', $m2->student_id)->where('subject_id', $m2->subject_id)->first();
                        if ($cMark) {
                            $cRules = $cMark->subject->getMarksForExam($cExam->id);
                            $cMax = isset($cRules['full_marks']) && $cRules['full_marks'] > 0 ? $cRules['full_marks'] : 100;
                            $obt2 += ($cMark->marks_obtained / $cMax) * ($max2 * ($cExam->contribution_percentage / 100));
                        }
                    }
                } else {
                    $obt2 = (float)$m2->marks_obtained;
                }
            }

            $combinedMax = $max1 + $max2;
            $combinedObt = $obt1 + $obt2;
            $perc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;

            $overallPassOnly = ($r1['overall_pass_only'] ?? $p1->overall_pass_only ?? false) || ($r2['overall_pass_only'] ?? $p2->overall_pass_only ?? false);
            $combinedOverallPassMark = ($r1['overall_pass_mark'] ?? 33) + ($r2['overall_pass_mark'] ?? 33);
            $combinedRequiredPass = ($r1['written_pass_mark'] ?? 33) + ($r2['written_pass_mark'] ?? 33);
            $mcqPass = ($r1['mcq_pass_mark'] ?? 0) + ($r2['mcq_pass_mark'] ?? 0);
            $mcqObt = ($m1 ? $m1->mcq_mark : 0) + ($m2 ? $m2->mcq_mark : 0);

            if ($overallPassOnly) {
                $isFailed = ($combinedObt < $combinedOverallPassMark);
            } else {
                $mcqFail = ($mcqPass > 0) && ($mcqObt < $mcqPass);
                $isFailed = ($combinedObt < $combinedRequiredPass) || $mcqFail;
            }

            $gData = $getGradeData($perc, $isFailed);
            return ['m1' => $m1, 'r1' => $r1, 'm2' => $m2, 'r2' => $r2, 'total_max' => $combinedMax, 'total_obt' => $combinedObt, 'grade' => $isFailed ? 'F' : $gData['grade'], 'gpa' => $isFailed ? '0.00' : number_format($gData['point'], 2), 'failed' => $isFailed];
        } else {
            $perc = $max1 > 0 ? ($obt1 / $max1) * 100 : 0;
            $isFailed = $m1 && trim($m1->grade) === 'F';
            $gData = $getGradeData($perc, $isFailed);
            return ['m1' => $m1, 'r1' => $r1, 'm2' => null, 'r2' => [], 'total_max' => $max1, 'total_obt' => $obt1, 'grade' => $isFailed ? 'F' : $gData['grade'], 'gpa' => $isFailed ? '0.00' : number_format($gData['point'], 2), 'failed' => $isFailed];
        }
    };

    $getRule = fn($t, $p, $k) => isset($t[$p][$k]) && $t[$p][$k] > 0 ? $t[$p][$k] : '--';
    $getMark = fn($t, $p, $k) => isset($t[$p]) && is_object($t[$p]) ? number_format((float)$t[$p]->$k, 1) : '--';

    $coreGpas = [];
    $coreTotalObtained = 0;
    $hasCoreFail = false;
    $optionalBonusPoints = 0;
    $optionalBonusMarks = 0;
    $failedSubjectsCount = 0;

    $qrPayload = "ID: " . ($enrollment->user->student_id ?? 'N/A') . "\nName: " . strtoupper($enrollment->user->name) . "\nClass: " . $enrollment->schoolClass->name . "\nRoll: " . $enrollment->roll_number;
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=" . urlencode($qrPayload);
@endphp
<style>
  * { box-sizing: border-box; }

  @media print {
      @page { 
          size: A4 landscape; 
          margin: 5mm; 
      }
      * { 
          -webkit-print-color-adjust: exact !important; 
          print-color-adjust: exact !important; 
      }
      body { margin: 0; }
  }

  body {
    font-family: 'Times New Roman', Times, serif;
    background: #fff;
    margin: 0;
    padding: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .sheet {
    width: 100%;
    height: 195mm; /* 🌟 Forces the border to perfectly fit the A4 height */
    background: #fff;
    border: 3px solid #c9a24b;
    padding: 25px; /* 🌟 Sets perfectly equal inner spacing on all 4 sides */
    position: relative;
    margin: 0 auto;
    display: flex;
    flex-direction: column; /* Allows us to push the signatures down */
  }

  .header {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    position: relative;
    padding-bottom: 4px;
    margin-bottom: 6px;
  }

  .photo-box {
    position: absolute;
    left: 0;
    top: 0;
    width: 55px;
    height: 65px;
    border: 1px solid #333;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f2f2f2;
    overflow: hidden;
  }
  .photo-box img { width: 100%; height: 100%; object-fit: cover; }

  .school-title { text-align: center; }
  .school-title .name-row { display: flex; align-items: center; justify-content: center; gap: 10px; }
  .logo {
    width: 44px; height: 44px; border-radius: 50%; border: 1px solid #2a6b2a;
    background: radial-gradient(circle at 30% 30%, #eaf6ea, #bfe3bf 70%);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; color: #1c5c1c; text-align: center; line-height: 1.1; overflow: hidden;
  }
  .logo img { width: 100%; height: 100%; object-fit: contain; }
  .school-title h1 { margin: 0; font-size: 22px; letter-spacing: .5px; color: #111; }
  .school-title .addr { font-size: 11px; margin: 1px 0; }
  .school-title .sheet-title { font-size: 13px; font-weight: bold; margin-top: 2px; }
  .school-title .sheet-sub { font-size: 11px; }

  .qr-wrapper-top { position: absolute; right: 135px; top: 0; width: 62px; height: 62px; border: 1px solid #ddd; border-radius: 4px; padding: 2px; }
  .qr-wrapper-top img { width: 100%; height: 100%; object-fit: contain; }

  /* 🌟 Table borders strictly enforced for ALL tables */
  table { border-collapse: collapse !important; }
  
  .grade-range { position: absolute; right: 0; top: 0; font-size: 9px; }
  .grade-range th, .grade-range td { border: 1px solid #333 !important; padding: 1px 5px; text-align: center; }
  .grade-range th { background: #f0f0f0; }

  .info { display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 4px; }
  .info-col { flex: 1; }
  .info-col div { margin: 1.5px 0; white-space: nowrap; }

  table.marks { width: 100%; margin-top: 4px; font-size: 10px; }
  table.marks th, table.marks td { border: 1px solid #333 !important; text-align: center; padding: 2px 2px; }
  table.marks thead th { background: #f4f4f4; font-weight: bold; font-size: 9px; }
  table.marks .subjects-col { width: 150px; text-align: left; padding-left: 5px; }
  table.marks tbody td.subject-name { text-align: left; padding-left: 5px; font-weight: bold; }
  
  .grade-f { color: #dc2626; font-weight: bold; }
  .highlight-green { color: #166534; font-weight: bold; }
  .highlight-blue { color: #1d4ed8; font-weight: bold; }

  .bottom { display: flex; gap: 8px; margin-top: 6px; font-size: 11px; }
  table.info-table { width: 100%; }
  table.info-table td { border: 1px solid #333 !important; padding: 2.5px 5px; }
  table.info-table td.label { background: #f7f7f7; font-weight: bold; width: 40%; }

  .bottom .left-block { flex: 1.3; }
  .bottom .right-block { flex: 1.7; }
  .right-block table.info-table td.label { width: 30%; }

  .comments { margin-top: 6px; border: 1px solid #333; min-height: 38px; font-size: 11px; padding: 3px 6px; font-weight: bold; }

  .signatures { 
    display: flex; 
    justify-content: space-between; 
    margin-top: auto; /* 🌟 This forces the signatures to sit at the absolute bottom of the border box! */
    font-size: 11px; 
    font-weight: bold; 
  }
  .signatures div { border-top: 1px solid #333; padding-top: 2px; width: 160px; text-align: center; }

</style>
</head>
<body>

<div class="sheet">
  <div class="header">
    <div class="photo-box">
      @if($studentPhoto)
        <img src="{{ $studentPhoto }}" alt="Student">
      @else
        <div style="font-size: 8px; padding-top: 18px; color: #555;">Paste<br>Photo</div>
      @endif
    </div>

    <div class="qr-wrapper-top">
        <img src="{{ $qrCodeUrl }}" alt="QR Code">
    </div>

    <table class="grade-range">
      <tr><th>Range</th><th>Grade</th><th>GPA</th></tr>
      <tr><td>80-100</td><td>A+</td><td>5.00</td></tr>
      <tr><td>70-79</td><td>A</td><td>4.00</td></tr>
      <tr><td>60-69</td><td>A-</td><td>3.50</td></tr>
      <tr><td>50-59</td><td>B</td><td>3.00</td></tr>
      <tr><td>40-49</td><td>C</td><td>2.00</td></tr>
      <tr><td>33-39</td><td>D</td><td>1.00</td></tr>
      <tr><td>0-32</td><td>F</td><td>0.00</td></tr>
    </table>

    <div class="school-title">
      <div class="name-row">
        <div class="logo">
            @if(isset($settings->logo) && $settings->logo)
                <img src="{{ asset('storage/' . $settings->logo) }}" alt="Logo">
            @else
                LOGO
            @endif
        </div>
        <h1>{{ strtoupper($schoolName) }}</h1>
      </div>
      <div class="addr">{{ $schoolAddress }}</div>
      <div class="sheet-title">FINAL CUMULATIVE MARK SHEET</div>
      <div class="sheet-sub">Academic Year: {{ $enrollment->academicYear->name ?? '2026' }}</div>
    </div>
  </div>

  <div class="info">
    <div class="info-col">
      <div><strong>Student Name:</strong> {{ strtoupper($enrollment->user->name) }}</div>
      <div><strong>Father's Name:</strong> ____________________</div>
      <div><strong>Mother's Name:</strong> ___________________</div>
      <div><strong>Class:</strong> {{ $enrollment->schoolClass->name }}</div>
    </div>
    <div class="info-col">
      <div><strong>Section:</strong> {{ $enrollment->section->name ?? 'N/A' }}</div>
      <div><strong>Section Roll:</strong> {{ $enrollment->roll_number }}</div>
      <div><strong>Student ID:</strong> {{ $enrollment->user->student_id ?? 'N/A' }}</div>
      <div><strong>Shift:</strong> Day</div>
    </div>
    <div class="info-col">
      <div><strong>Student Type:</strong> Regular</div>
      <div><strong>Medium:</strong> {{ $settings->medium ?? 'Bangla' }}</div>
      <div><strong>Department:</strong> {{ $enrollment->study_group ?? 'General' }}</div>
      <div><strong>Exam Year:</strong> {{ $enrollment->academicYear->name }}</div>
    </div>
  </div>

  <table class="marks">
    <thead>
      <tr>
        <th rowspan="3" class="subjects-col">Name of Subjects</th>
        <th colspan="9">{{ $exam1?->name ?? 'Half Yearly Exam' }}</th>
        <th colspan="9">{{ $exam2?->name ?? 'Final Year Exam' }}</th>
        <th colspan="3">(Half+Final) Average</th>
      </tr>
      <tr>
        <th colspan="2">Written</th><th colspan="2">MCQ</th><th colspan="2">Practical</th><th rowspan="2">Total</th><th rowspan="2">Grade</th><th rowspan="2">GPA</th>
        <th colspan="2">Written</th><th colspan="2">MCQ</th><th colspan="2">Practical</th><th rowspan="2">Total</th><th rowspan="2">Grade</th><th rowspan="2">GPA</th>
        <th rowspan="2">Grand<br>Total</th><th rowspan="2">Final<br>Grade</th><th rowspan="2">Final<br>GPA</th>
      </tr>
      <tr>
        <th>Full</th><th>Obt.</th><th>Full</th><th>Obt.</th><th>Full</th><th>Obt.</th>
        <th>Full</th><th>Obt.</th><th>Full</th><th>Obt.</th><th>Full</th><th>Obt.</th>
      </tr>
    </thead>
    <tbody>
      @foreach($coreGroups as $group)
        @php
            $isCombined = $group['is_combined'];
            $p1 = $group['paper1'];
            $p2 = $group['paper2'];
            $t1 = $evalTerm($p1, $p2, $exam1, $isCombined);
            $t2 = $evalTerm($p1, $p2, $exam2, $isCombined);
            $activeTerms = 0; $sumObt = 0; $sumMax = 0;
            if ($t1 && $t1['total_max'] > 0) { $activeTerms++; $sumObt += $t1['total_obt']; $sumMax += $t1['total_max']; }
            if ($t2 && $t2['total_max'] > 0) { $activeTerms++; $sumObt += $t2['total_obt']; $sumMax += $t2['total_max']; }
            $activeTerms = max(1, $activeTerms);
            $finalAvgObt = $sumObt / $activeTerms;
            $finalAvgMax = $sumMax / $activeTerms;
            $finalPerc = $finalAvgMax > 0 ? ($finalAvgObt / $finalAvgMax) * 100 : 0;
            $finalFailed = ($t1['failed'] || $t2['failed']);
            $finalGradeData = $getGradeData($finalPerc, $finalFailed);
            $finalGpa = $finalFailed ? '0.00' : number_format($finalGradeData['point'], 2);
            $finalGrade = $finalFailed ? 'F' : $finalGradeData['grade'];
            $coreTotalObtained += $finalAvgObt;
            $coreGpas[] = (float)$finalGpa;
            if ($finalFailed) { $hasCoreFail = true; $failedSubjectsCount++; }
        @endphp
        @if($isCombined)
            <tr>
                <td class="subject-name">{{ $p1->name }}</td>
                <td>{{ $getRule($t1, 'r1', 'written_total') }}</td><td>{{ $getMark($t1, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'practical_total') }}</td><td>{{ $getMark($t1, 'm1', 'practical_mark') }}</td>
                <!-- ROWSPAN ADDED FOR EXAM 1 -->
                <td class="highlight-blue" rowspan="2">{{ $t1['total_max'] > 0 ? number_format($t1['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t1['total_max'] > 0 ? $t1['grade'] : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t1['total_max'] > 0 ? $t1['gpa'] : '--' }}</td>
                
                <td>{{ $getRule($t2, 'r1', 'written_total') }}</td><td>{{ $getMark($t2, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'practical_total') }}</td><td>{{ $getMark($t2, 'm1', 'practical_mark') }}</td>
                <!-- ROWSPAN ADDED FOR EXAM 2 -->
                <td class="highlight-blue" rowspan="2">{{ $t2['total_max'] > 0 ? number_format($t2['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t2['total_max'] > 0 ? $t2['grade'] : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t2['total_max'] > 0 ? $t2['gpa'] : '--' }}</td>
                
                <td class="highlight-blue" rowspan="2">{{ number_format($finalAvgObt, 1) }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $finalGrade }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $finalGpa }}</td>
            </tr>
            <tr>
                <td class="subject-name">{{ $p2->name }}</td>
                <td>{{ $getRule($t1, 'r2', 'written_total') }}</td><td>{{ $getMark($t1, 'm2', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r2', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm2', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r2', 'practical_total') }}</td><td>{{ $getMark($t1, 'm2', 'practical_mark') }}</td>
                <!-- EMPTY TDs REMOVED HERE -->
                
                <td>{{ $getRule($t2, 'r2', 'written_total') }}</td><td>{{ $getMark($t2, 'm2', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r2', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm2', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r2', 'practical_total') }}</td><td>{{ $getMark($t2, 'm2', 'practical_mark') }}</td>
                <!-- EMPTY TDs REMOVED HERE -->
            </tr>
        @else
            <tr>
                <td class="subject-name">{{ $p1->name }}</td>
                <td>{{ $getRule($t1, 'r1', 'written_total') }}</td><td>{{ $getMark($t1, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'practical_total') }}</td><td>{{ $getMark($t1, 'm1', 'practical_mark') }}</td>
                <td class="highlight-blue">{{ $t1['total_max'] > 0 ? number_format($t1['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t1['total_max'] > 0 ? $t1['grade'] : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t1['total_max'] > 0 ? $t1['gpa'] : '--' }}</td>
                <td>{{ $getRule($t2, 'r1', 'written_total') }}</td><td>{{ $getMark($t2, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'practical_total') }}</td><td>{{ $getMark($t2, 'm1', 'practical_mark') }}</td>
                <td class="highlight-blue">{{ $t2['total_max'] > 0 ? number_format($t2['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t2['total_max'] > 0 ? $t2['grade'] : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t2['total_max'] > 0 ? $t2['gpa'] : '--' }}</td>
                <td class="highlight-blue">{{ number_format($finalAvgObt, 1) }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}">{{ $finalGrade }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}">{{ $finalGpa }}</td>
            </tr>
        @endif
      @endforeach

      @if(count($optionalGroups) > 0)
        <tr><td colspan="22" style="background:#eef2ff; text-align:left; font-weight:bold; padding-left:5px;">Optional / 4th Subject</td></tr>
        @foreach($optionalGroups as $group)
          @php
            $isCombined = $group['is_combined'];
            $p1 = $group['paper1'];
            $p2 = $group['paper2'];
            $t1 = $evalTerm($p1, $p2, $exam1, $isCombined);
            $t2 = $evalTerm($p1, $p2, $exam2, $isCombined);
            $activeTerms = 0; $sumObt = 0; $sumMax = 0;
            if ($t1 && $t1['total_max'] > 0) { $activeTerms++; $sumObt += $t1['total_obt']; $sumMax += $t1['total_max']; }
            if ($t2 && $t2['total_max'] > 0) { $activeTerms++; $sumObt += $t2['total_obt']; $sumMax += $t2['total_max']; }
            $activeTerms = max(1, $activeTerms);
            $finalAvgObt = $sumObt / $activeTerms;
            $finalAvgMax = $sumMax / $activeTerms;
            $finalPerc = $finalAvgMax > 0 ? ($finalAvgObt / $finalAvgMax) * 100 : 0;
            $finalFailed = ($t1['failed'] || $t2['failed']);
            $finalGradeData = $getGradeData($finalPerc, $finalFailed);
            $finalGpa = $finalFailed ? '0.00' : number_format($finalGradeData['point'], 2);
            $finalGrade = $finalFailed ? 'F' : $finalGradeData['grade'];
            if ($finalAvgObt > 40) $optionalBonusMarks += ($finalAvgObt - 40);
            if ($finalGradeData['point'] > 2.0) $optionalBonusPoints += ($finalGradeData['point'] - 2.0);
          @endphp
          @if($isCombined)
            <tr>
                <td class="subject-name">{{ $p1->name }}</td>
                <td>{{ $getRule($t1, 'r1', 'written_total') }}</td><td>{{ $getMark($t1, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'practical_total') }}</td><td>{{ $getMark($t1, 'm1', 'practical_mark') }}</td>
                <!-- ROWSPAN ADDED FOR EXAM 1 -->
                <td class="highlight-blue" rowspan="2">{{ $t1['total_max'] > 0 ? number_format($t1['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t1['total_max'] > 0 ? $t1['grade'] : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t1['total_max'] > 0 ? $t1['gpa'] : '--' }}</td>
                
                <td>{{ $getRule($t2, 'r1', 'written_total') }}</td><td>{{ $getMark($t2, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'practical_total') }}</td><td>{{ $getMark($t2, 'm1', 'practical_mark') }}</td>
                <!-- ROWSPAN ADDED FOR EXAM 2 -->
                <td class="highlight-blue" rowspan="2">{{ $t2['total_max'] > 0 ? number_format($t2['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t2['total_max'] > 0 ? $t2['grade'] : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $t2['total_max'] > 0 ? $t2['gpa'] : '--' }}</td>
                
                <td class="highlight-blue" rowspan="2">{{ number_format($finalAvgObt, 1) }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $finalGrade }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}" rowspan="2">{{ $finalGpa }}</td>
            </tr>
            <tr>
                <td class="subject-name">{{ $p2->name }}</td>
                <td>{{ $getRule($t1, 'r2', 'written_total') }}</td><td>{{ $getMark($t1, 'm2', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r2', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm2', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r2', 'practical_total') }}</td><td>{{ $getMark($t1, 'm2', 'practical_mark') }}</td>
                <!-- EMPTY TDs REMOVED HERE -->
                
                <td>{{ $getRule($t2, 'r2', 'written_total') }}</td><td>{{ $getMark($t2, 'm2', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r2', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm2', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r2', 'practical_total') }}</td><td>{{ $getMark($t2, 'm2', 'practical_mark') }}</td>
                <!-- EMPTY TDs REMOVED HERE -->
            </tr>
          @else
            <tr>
                <td class="subject-name">{{ $p1->name }}</td>
                <td>{{ $getRule($t1, 'r1', 'written_total') }}</td><td>{{ $getMark($t1, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t1, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t1, 'r1', 'practical_total') }}</td><td>{{ $getMark($t1, 'm1', 'practical_mark') }}</td>
                <td class="highlight-blue">{{ $t1['total_max'] > 0 ? number_format($t1['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t1['total_max'] > 0 ? $t1['grade'] : '--' }}</td>
                <td class="{{ $t1['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t1['total_max'] > 0 ? $t1['gpa'] : '--' }}</td>
                <td>{{ $getRule($t2, 'r1', 'written_total') }}</td><td>{{ $getMark($t2, 'm1', 'written_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'mcq_total') }}</td><td>{{ $getMark($t2, 'm1', 'mcq_mark') }}</td>
                <td>{{ $getRule($t2, 'r1', 'practical_total') }}</td><td>{{ $getMark($t2, 'm1', 'practical_mark') }}</td>
                <td class="highlight-blue">{{ $t2['total_max'] > 0 ? number_format($t2['total_obt'], 1) : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t2['total_max'] > 0 ? $t2['grade'] : '--' }}</td>
                <td class="{{ $t2['failed'] ? 'grade-f' : 'highlight-green' }}">{{ $t2['total_max'] > 0 ? $t2['gpa'] : '--' }}</td>
                <td class="highlight-blue">{{ number_format($finalAvgObt, 1) }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}">{{ $finalGrade }}</td>
                <td class="{{ $finalFailed ? 'grade-f' : 'highlight-green' }}">{{ $finalGpa }}</td>
            </tr>
          @endif
        @endforeach
      @endif
      @php
          $grandTotal = $coreTotalObtained + $optionalBonusMarks;
          $cCount = count($coreGpas);
          if ($hasCoreFail || $cCount === 0) {
              $finalOverallGpa = '0.00';
              $finalOverallGrade = 'F';
          } else {
              $calcGpa = min(5.00, (array_sum($coreGpas) + $optionalBonusPoints) / $cCount);
              $finalOverallGpa = number_format($calcGpa, 2);
              if ($calcGpa >= 5.00) $finalOverallGrade = 'A+';
              elseif ($calcGpa >= 4.00) $finalOverallGrade = 'A';
              elseif ($calcGpa >= 3.50) $finalOverallGrade = 'A-';
              elseif ($calcGpa >= 3.00) $finalOverallGrade = 'B';
              elseif ($calcGpa >= 2.00) $finalOverallGrade = 'C';
              elseif ($calcGpa >= 1.00) $finalOverallGrade = 'D';
              else $finalOverallGrade = 'F';
          }
      @endphp
      <tr>
        <td colspan="19" style="text-align: right; padding-right: 10px; font-weight: bold; background: #e2e8f0;">FINAL GRAND TOTAL / GRADE</td>
        <td style="font-weight: bold; background: #e2e8f0;" class="highlight-blue">{{ number_format($grandTotal, 1) }}</td>
        <td style="font-weight: bold; background: #e2e8f0;" class="{{ $finalOverallGrade === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $finalOverallGrade }}</td>
        <td style="font-weight: bold; background: #e2e8f0;" class="{{ $finalOverallGrade === 'F' ? 'grade-f' : 'highlight-green' }}">{{ $finalOverallGpa }}</td>
      </tr>
    </tbody>
  </table>

  <div class="bottom">
    <div class="left-block">
      <table class="info-table">
        <tr><td class="label">Result Status</td><td class="{{ $hasCoreFail ? 'grade-f' : 'highlight-green' }}" style="font-weight:bold;">{{ $hasCoreFail ? 'FAILED' : 'PASSED' }}</td><td class="label">Failed Subject(s)</td><td class="{{ $hasCoreFail ? 'grade-f' : 'highlight-green' }}" style="font-weight:bold;">{{ $failedSubjectsCount }}</td></tr>
        <tr><td class="label">Publish Date</td><td style="font-weight:bold;">{{ date('d-m-Y') }}</td><td class="label">Schooling Day</td><td>240</td></tr>
        <tr><td class="label">Merit Position</td><td class="highlight-blue" style="font-weight:bold;">--</td><td class="label">Present days</td><td></td></tr>
      </table>
    </div>
    <div class="right-block">
      <table class="info-table">
        <tr><td class="label" rowspan="2" style="text-align:center;">Moral &amp; Behavior</td><td>Best</td><td>Better</td><td class="label" rowspan="2" style="text-align:center;">Co-Curricular Activities</td><td>Sports</td><td>Scout</td></tr>
        <tr><td>Good</td><td>Need Improvement</td><td>Cultural Function</td><td>Math Olympiad</td></tr>
      </table>
    </div>
  </div>

  <div class="comments">
    Comments / Remarks:
  </div>

  <div class="signatures">
    <div>Guardian's Signature</div>
    <div>Class Teacher's Signature</div>
    <div>Principal / Head Teacher</div>
  </div>
</div>

@if(!isset($is_batch))
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
@endif

</body>
</html>