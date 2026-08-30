<?php

use Illuminate\Support\Facades\Route;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Exam;
use Illuminate\Http\Request;

Route::redirect('/', '/admin/login');

// Route for Single Exam Marksheet
Route::get('/print/marksheet/{enrollment}/{exam}', function ($enrollmentId, $examId) {
    $enrollment = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])->findOrFail($enrollmentId);
    $exam = Exam::findOrFail($examId);
    
    $marks = Mark::with('subject')
        ->where('student_id', $enrollment->user_id)
        ->where('academic_year_id', $enrollment->academic_year_id)
        ->where('school_class_id', $enrollment->school_class_id)
        ->where('exam_id', $examId)
        ->get();

    return view('pdf.marksheet', compact('enrollment', 'marks', 'exam'));
})->name('print.marksheet');


// Route for Final Cumulative Marksheet
Route::get('/print/final-marksheet/{id}', function ($id) {
    $enrollment = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])->findOrFail($id);
    
    $allMarks = Mark::with(['subject', 'exam'])
        ->where('student_id', $enrollment->user_id)
        ->where('academic_year_id', $enrollment->academic_year_id)
        ->where('school_class_id', $enrollment->school_class_id)
        ->get();

    return view('pdf.final-marksheet', [
        'enrollment' => $enrollment,
        'allMarks'   => $allMarks,
    ]);
})->name('print.final.marksheet');


// 🌟 MISSING ROUTE ADDED: Term Exam Batch Marksheet 🌟
Route::get('/print/batch-marksheet', function (Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $examId = $request->query('exam');
    $sectionId = $request->query('section');

    $query = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId);

    if (!empty($sectionId) && $sectionId !== 'N/A' && $sectionId !== 'null') {
        $query->where('section_id', $sectionId);
    }
    $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

    if ($enrollments->isEmpty()) {
        return "No students found. Please check filters.";
    }

    $exam = Exam::with('academicYear')->findOrFail($examId);

    $allMarks = Mark::with('subject')
        ->whereIn('student_id', $enrollments->pluck('user_id'))
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId)
        ->where('exam_id', $examId)
        ->get();

    return view('pdf.batch-marksheet', compact('enrollments', 'exam', 'allMarks'));
})->name('print.batch.marksheet');


// Route for Final Cumulative Batch Marksheet
Route::get('/print/batch-final-marksheet', function (Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $sectionId = $request->query('section');

    $query = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId);

    if (!empty($sectionId) && $sectionId !== 'N/A' && $sectionId !== 'null') {
        $query->where('section_id', $sectionId);
    }
    $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

    if ($enrollments->isEmpty()) {
        return "No students found. Please check filters.";
    }

    // Pull ALL marks for the entire academic year for these students
    $allMarks = Mark::with(['subject', 'exam'])
        ->whereIn('student_id', $enrollments->pluck('user_id'))
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId)
        ->get();

    return view('pdf.batch-final-marksheet', compact('enrollments', 'allMarks'));
})->name('print.batch.final.marksheet');

// 🌟 Native Browser Print Route for Merit List
Route::get('/print/merit-list', function (\Illuminate\Http\Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $examId = $request->query('exam');
    $scope = $request->query('scope');
    $sectionId = $request->query('section');
    $studyGroup = $request->query('group');

    $exam = \App\Models\Exam::with('academicYear')->find($examId);
    $schoolClass = \App\Models\SchoolClass::find($classId);

    $query = \App\Models\Enrollment::with(['user', 'schoolClass', 'section'])
        ->where('school_class_id', $classId)
        ->where('academic_year_id', $yearId);

    if ($scope === 'section' && $sectionId) {
        $query->where('section_id', $sectionId);
    } elseif ($scope === 'group' && $studyGroup) {
        $query->where('study_group', $studyGroup);
    }

    $enrollments = $query->get();
    $calculatedRankings = [];

    // Helper functions for the route
    $getGp = fn($perc) => match(true) { $perc >= 80 => 5.00, $perc >= 70 => 4.00, $perc >= 60 => 3.50, $perc >= 50 => 3.00, $perc >= 40 => 2.00, $perc >= 33 => 1.00, default => 0.00 };
    $getGrade = fn($perc) => match(true) { $perc >= 80 => 'A+', $perc >= 70 => 'A', $perc >= 60 => 'A-', $perc >= 50 => 'B', $perc >= 40 => 'C', $perc >= 33 => 'D', default => 'F' };
    
    foreach ($enrollments as $enrollment) {
        $marks = \App\Models\Mark::with('subject')
            ->where('student_id', $enrollment->user_id)
            ->where('academic_year_id', $yearId)
            ->where('school_class_id', $classId)
            ->where('exam_id', $examId)
            ->get();

        if ($marks->isEmpty()) continue;

        $groupName = strtolower($enrollment->study_group ?? '');
        $filteredMarks = $marks->filter(function($mark) use ($groupName) {
            if (!$mark->subject) return false;
            $subCode = (string) $mark->subject->code;
            $subName = strtolower($mark->subject->name);
            if ($groupName === 'science' && ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান'))) return false;
            if (in_array($groupName, ['arts/humanities', 'arts', 'humanities', 'commerce']) && in_array($subCode, ['136', '137', '138'])) return false;
            return true;
        })->values();

        $groupedMarks = [];
        $processedIds = [];

        foreach ($filteredMarks as $mark) {
            if (in_array($mark->id, $processedIds)) continue;
            $partnerMark = $mark->subject->linked_subject_id 
                ? $filteredMarks->firstWhere('subject_id', $mark->subject->linked_subject_id) 
                : $filteredMarks->where('subject.linked_subject_id', $mark->subject_id)->first();

            if ($partnerMark && $mark->subject->linked_subject_id) {
                 // Already in correct order
            } elseif ($partnerMark) {
                $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
            }

            $r1 = $mark->subject->getMarksForExam($examId);
            $max1 = ($r1 && isset($r1['full_marks']) && $r1['full_marks'] > 0) ? $r1['full_marks'] : 100;

            if ($partnerMark) {
                $r2 = $partnerMark->subject->getMarksForExam($examId);
                $max2 = ($r2 && isset($r2['full_marks']) && $r2['full_marks'] > 0) ? $r2['full_marks'] : 100;
                $perc = (($mark->marks_obtained + $partnerMark->marks_obtained) / ($max1 + $max2)) * 100;
                
                $groupedMarks[] = ['subject' => $mark->subject, 'obt' => $mark->marks_obtained + $partnerMark->marks_obtained, 'gp' => $getGp($perc), 'grade' => $getGrade($perc)];
                array_push($processedIds, $mark->id, $partnerMark->id);
            } else {
                $perc = ($mark->marks_obtained / $max1) * 100;
                $groupedMarks[] = ['subject' => $mark->subject, 'obt' => $mark->marks_obtained, 'gp' => $getGp($perc), 'grade' => $getGrade($perc)];
                $processedIds[] = $mark->id;
            }
        }

        $coreGPAs = []; $hasCoreFail = false; $coreTotal = 0.0; $optBonusMarks = 0.0; $optBonusPts = 0.00;

        foreach ($groupedMarks as $gMark) {
            $subName = strtolower($gMark['subject']->name ?? '');
            $subType = strtolower($gMark['subject']->subject_type ?? $gMark['subject']->type ?? '');
            $isOpt = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

            if ($isOpt) {
                if ($gMark['obt'] > 40) $optBonusMarks = $gMark['obt'] - 40;
                if ($gMark['gp'] > 2.00) $optBonusPts = $gMark['gp'] - 2.00;
            } else {
                $coreTotal += $gMark['obt'];
                $coreGPAs[] = $gMark['gp'];
                if ($gMark['grade'] === 'F') $hasCoreFail = true;
            }
        }

        $coreCount = count($coreGPAs);
        if ($hasCoreFail || $coreCount === 0) {
            $finalGpa = 0.00;
            $finalGrade = 'F';
        } else {
            $finalGpa = min(5.00, (array_sum($coreGPAs) + $optBonusPts) / $coreCount);
            $finalGrade = match(true) { $finalGpa >= 5.00 => 'A+', $finalGpa >= 4.00 => 'A', $finalGpa >= 3.50 => 'A-', $finalGpa >= 3.00 => 'B', $finalGpa >= 2.00 => 'C', $finalGpa >= 1.00 => 'D', default => 'F' };
        }

        $calculatedRankings[] = [
            'student_id' => $enrollment->user->student_id ?? 'N/A',
            'name'       => $enrollment->user->name ?? 'N/A',
            'roll'       => $enrollment->roll_number,
            'section'    => $enrollment->section->name ?? 'N/A',
            'group'      => $enrollment->study_group ?? 'General',
            'total'      => $coreTotal + $optBonusMarks,
            'gpa'        => number_format($finalGpa, 2),
            'grade'      => $finalGrade,
            'failed'     => $hasCoreFail,
        ];
    }

    // Rank sorting
    usort($calculatedRankings, function ($a, $b) {
        if ($a['failed'] !== $b['failed']) return $a['failed'] <=> $b['failed'];
        if ((float)$b['gpa'] != (float)$a['gpa']) return (float)$b['gpa'] <=> (float)$a['gpa'];
        return (float)$b['total'] <=> (float)$a['total'];
    });

    return view('pdf.merit-list', compact('calculatedRankings', 'exam', 'schoolClass', 'scope'));
})->name('print.merit.list');