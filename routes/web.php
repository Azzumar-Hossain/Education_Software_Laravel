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

// 🌟 Native Browser Print Route for Fail List
Route::get('/print/fail-list', function (\Illuminate\Http\Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $scope = $request->query('scope');
    $sectionId = $request->query('section');
    $studyGroup = $request->query('group');

    $user = auth()->user();
    $schoolClass = \App\Models\SchoolClass::find($classId);
    $academicYear = \App\Models\AcademicYear::find($yearId);

    $query = \App\Models\Enrollment::with(['user', 'schoolClass', 'section'])
        ->where('school_class_id', $classId)
        ->where('academic_year_id', $yearId);

    if ($scope === 'section' && $sectionId) {
        $query->where('section_id', $sectionId);
    } elseif ($scope === 'group' && $studyGroup) {
        $query->where('study_group', $studyGroup);
    } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
        $assignedSectionIds = \App\Models\ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
        if ($assignedSectionIds->isNotEmpty()) {
            $query->whereIn('section_id', $assignedSectionIds);
        }
    }

    $enrollments = $query->get();
    $calculatedFailRecords = [];

    foreach ($enrollments as $enrollment) {
        $allMarks = \App\Models\Mark::with('subject')
            ->where('student_id', $enrollment->user_id)
            ->where('academic_year_id', $yearId)
            ->where('school_class_id', $classId)
            ->get();

        if ($allMarks->isEmpty()) continue;

        $groupName = strtolower($enrollment->study_group ?? '');
        $filteredMarks = $allMarks->filter(function($mark) use ($groupName) {
            if (!$mark->subject) return false;
            $subCode = (string) $mark->subject->code;
            $subName = strtolower($mark->subject->name);
            
            if ($groupName === 'science' && ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান'))) return false;
            if (in_array($groupName, ['arts/humanities', 'arts', 'humanities', 'commerce']) && in_array($subCode, ['136', '137', '138'])) return false;
            return true;
        })->values();

        $coreTotalObtained = 0.0;
        $optionalBonusMarks = 0.0;
        $failedSubjectNames = [];
        $processedIds = [];

        foreach ($filteredMarks as $mark) {
            if (in_array($mark->id, $processedIds)) continue;

            $partnerMark = $mark->subject->linked_subject_id 
                ? $filteredMarks->firstWhere('subject_id', $mark->subject->linked_subject_id) 
                : $filteredMarks->where('subject.linked_subject_id', $mark->subject_id)->first();

            if ($partnerMark && !$mark->subject->linked_subject_id) {
                $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
            }

            $subName = strtolower($mark->subject->name ?? '');
            $subType = strtolower($mark->subject->subject_type ?? $mark->subject->type ?? '');
            $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional' || (int) $enrollment->optional_subject_id === (int) $mark->subject_id);

            if ($partnerMark) {
                $rules1 = $mark->subject->getMarksForExam($mark->exam_id);
                $rules2 = $partnerMark->subject->getMarksForExam($mark->exam_id);
                
                $overallPassOnly = ($rules1['overall_pass_only'] ?? $mark->subject->overall_pass_only ?? false) || ($rules2['overall_pass_only'] ?? $partnerMark->subject->overall_pass_only ?? false);

                $opm1 = $rules1['overall_pass_mark'] ?? $mark->subject->overall_pass_mark ?? 33;
                $opm2 = $rules2['overall_pass_mark'] ?? $partnerMark->subject->overall_pass_mark ?? 33;
                $combinedOverallPassMark = $opm1 + $opm2;

                $pass1 = $rules1['written_pass_mark'] ?? $mark->subject->written_pass_mark ?? 33;
                $pass2 = $rules2['written_pass_mark'] ?? $partnerMark->subject->written_pass_mark ?? 33;
                $combinedRequiredPass = $pass1 + $pass2;

                $mcq1Pass = $rules1['mcq_pass_mark'] ?? $mark->subject->mcq_pass_mark ?? 0;
                $mcq2Pass = $rules2['mcq_pass_mark'] ?? $partnerMark->subject->mcq_pass_mark ?? 0;
                $combinedMcqObt = ($mark->mcq_mark ?? 0) + ($partnerMark->mcq_mark ?? 0);
                $combinedMcqPass = $mcq1Pass + $mcq2Pass;

                $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;

                if ($overallPassOnly) {
                    $isComponentFailed = ($combinedObt < $combinedOverallPassMark);
                } else {
                    $mcqFail = ($combinedMcqPass > 0) && ($combinedMcqObt < $combinedMcqPass);
                    $isComponentFailed = ($combinedObt < $combinedRequiredPass) || $mcqFail;
                }

                if ($isOptional) {
                    if ($combinedObt > 40.0) $optionalBonusMarks = $combinedObt - 40.0;
                } else {
                    $coreTotalObtained += $combinedObt;
                    if ($isComponentFailed) {
                        if (str_contains($subName, 'bangla')) $failedSubjectNames['bangla'] = "Bang (F)";
                        elseif (str_contains($subName, 'english')) $failedSubjectNames['english'] = "Engl (F)";
                        else {
                            $cleanName = trim(preg_replace('/\(.*\)/u', '', $mark->subject->name));
                            $words = explode(' ', $cleanName);
                            $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                            $failedSubjectNames[$mark->subject_id] = substr($prefix, 0, 4) . " (F)";
                        }
                    }
                }

                array_push($processedIds, $mark->id, $partnerMark->id);
            } else {
                $obtVal = (float) $mark->marks_obtained;
                if ($isOptional) {
                    if ($obtVal > 40.0) $optionalBonusMarks = $obtVal - 40.0;
                } else {
                    $coreTotalObtained += $obtVal;
                    if (trim($mark->grade) === 'F') {
                        $cleanName = trim(preg_replace('/\(.*\)/u', '', $mark->subject->name));
                        $words = explode(' ', $cleanName);
                        $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                        $failedSubjectNames[$mark->subject_id] = substr($prefix, 0, 4) . " (F)";
                    }
                }
                $processedIds[] = $mark->id;
            }
        }

        $grandTotalObtained = $coreTotalObtained + $optionalBonusMarks;

        if (!empty($failedSubjectNames)) {
            $calculatedFailRecords[] = [
                'student_id'              => $enrollment->user->student_id ?? 'N/A',
                'student_name'            => $enrollment->user->name ?? 'N/A',
                'roll_number'             => (int)($enrollment->roll_number ?? 0),
                'section_name'            => $enrollment->section->name ?? 'N/A',
                'group_name'              => $enrollment->study_group ?? 'General',
                'total_marks'             => (float)$grandTotalObtained,
                'fail_count'              => count($failedSubjectNames), 
                'failed_subjects_summary' => implode(', ', $failedSubjectNames),
            ];
        }
    }

    if (!empty($calculatedFailRecords)) {
        usort($calculatedFailRecords, function ($a, $b) {
            if ($a['fail_count'] !== $b['fail_count']) return $a['fail_count'] <=> $b['fail_count'];
            if ($b['total_marks'] !== $a['total_marks']) return $b['total_marks'] <=> $a['total_marks'];
            return $a['roll_number'] <=> $b['roll_number'];
        });
    }

    return view('pdf.fail-list', compact('calculatedFailRecords', 'schoolClass', 'academicYear', 'scope'));
})->name('print.fail.list');

// 🌟 Native Browser Print Route for Exam Routine
Route::get('/print/exam-routine', function (\Illuminate\Http\Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $examId = $request->query('exam');

    $routines = \App\Models\ExamRoutine::with(['subject', 'schoolClass', 'exam', 'academicYear'])
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId)
        ->where('exam_id', $examId)
        ->orderBy('exam_date', 'asc')
        ->orderBy('start_time', 'asc')
        ->get();

    if ($routines->isEmpty()) {
        return "No exam routine schedule found for this selection.";
    }

    $schoolClass = \App\Models\SchoolClass::find($classId);
    $exam = \App\Models\Exam::find($examId);
    $academicYear = \App\Models\AcademicYear::find($yearId);

    // Fetch dynamic school logo and name from settings just like your component does
    $schoolLogo = asset('images/logo.png');
    $schoolName = 'Krisnagobindapur High School'; // Defaulting to your UI name

    if (class_exists('\App\Models\Setting')) {
        $setting = \App\Models\Setting::first();
        if ($setting) {
            $schoolLogo = $setting->logo ? asset('storage/' . $setting->logo) : $schoolLogo;
            $schoolName = $setting->site_name ?? $schoolName;
        }
    }

    return view('pdf.exam-routine', compact('routines', 'schoolClass', 'exam', 'academicYear', 'schoolLogo', 'schoolName'));
})->name('print.exam.routine');