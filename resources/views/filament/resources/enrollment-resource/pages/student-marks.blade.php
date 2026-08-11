<x-filament-panels::page>
    @php
        $allMarks = \App\Models\Mark::with(['exam', 'subject'])
            ->where('student_id', $record->user_id)
            ->where('academic_year_id', $record->academic_year_id)
            ->where('school_class_id', $record->school_class_id)
            ->get()
            ->sortBy(fn($m) => (int) ($m->subject->code ?? 999));
            
        $marksGrouped = $allMarks->groupBy('exam_id');
        
        $getGradeData = function($percentage, $isComponentFailed = false) {
            return \App\Models\GradeScale::getGradeForMark($percentage, $isComponentFailed);
        };

        $mainExamIds = \App\Models\Exam::whereNull('parent_exam_id')->pluck('id')->toArray();
    @endphp

    @if($marksGrouped->isEmpty())
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm text-center border border-gray-200 dark:border-gray-700">
            <p class="text-gray-500 dark:text-gray-400">No marks have been recorded for this student yet.</p>
        </div>
    @else
        <div class="space-y-8">
            
            @foreach($marksGrouped as $examId => $marks)
                @php 
                    $examName = $marks->first()->exam->name ?? 'Unknown Exam'; 

                    $getHighest = function($subjectId, $column) use ($marks) {
                        if ($marks->isEmpty()) return '--';
                        $sampleMark = $marks->first();
                        
                        $highest = $sampleMark->newQuery()
                            ->where('exam_id', $sampleMark->exam_id)
                            ->where('subject_id', $subjectId)
                            ->max($column);
                            
                        return ($highest !== null && $highest > 0) ? number_format($highest, 1) : '--';
                    };

                    // --- CUMULATIVE WEIGHTAGE LOGIC ---
                    $childExams = \App\Models\Exam::where('parent_exam_id', $examId)->get();
                    
                    if ($childExams->count() > 0) {
                        $childrenTotalWeight = $childExams->sum('contribution_percentage');
                        $mainExamWeight = 100 - $childrenTotalWeight; 

                        foreach($marks as $mark) {
                            $cumulativeObtained = 0;
                            $mainRules = $mark->subject->getMarksForExam($examId);
                            $mainMax = $mainRules['full_marks'] > 0 ? $mainRules['full_marks'] : 100;
                            
                            $mainWeighted = $mainMax > 0 ? ($mark->marks_obtained / $mainMax) * ($mainMax * ($mainExamWeight / 100)) : 0;
                            $cumulativeObtained += $mainWeighted;
                            
                            foreach($childExams as $childExam) {
                                $childMark = \App\Models\Mark::where('exam_id', $childExam->id)
                                    ->where('student_id', $mark->student_id)
                                    ->where('subject_id', $mark->subject_id)
                                    ->first();
                                    
                                if ($childMark) {
                                    $childRules = $childMark->subject->getMarksForExam($childExam->id);
                                    $childMax = $childRules['full_marks'] > 0 ? $childRules['full_marks'] : 100;
                                    
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

                    // --- COMBINED SUBJECTS LOGIC ---
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

                        $rules1 = $mark->subject->getMarksForExam($examId);
                        $max1 = $rules1['full_marks'] > 0 ? $rules1['full_marks'] : 100;

                        if ($partnerMark) {
                            $rules2 = $partnerMark->subject->getMarksForExam($examId);
                            $max2 = $rules2['full_marks'] > 0 ? $rules2['full_marks'] : 100;

                            $combinedMax = $max1 + $max2;
                            $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;
                            $combinedPerc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;
                            
                            // 🌟 OVERALL PASS RULE INTEGRATION 🌟
                            $overallPassOnly = ($rules1['overall_pass_only'] ?? $mark->subject->overall_pass_only ?? false) || 
                                               ($rules2['overall_pass_only'] ?? $partnerMark->subject->overall_pass_only ?? false);

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

                            // Apply logic depending on the configuration
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

                    // --- CALCULATE TERM-LEVEL GPA & GRADE WITH 4TH SUBJECT RULES ---
                    $termCoreGPAs = [];
                    $hasTermCoreFail = false;
                    $optionalBonusPoints = 0.00;

                    foreach ($groupedMarks as $gMark) {
                        $subName = strtolower($gMark['subject_model']->name ?? '');
                        $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');
                        $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

                        if ($isOptional) {
                            $points = (float) $gMark['gpa'];
                            if ($points > 2.00) {
                                $optionalBonusPoints = $points - 2.00;
                            }
                        } else {
                            if ($gMark['combined_grade'] === 'F') {
                                $hasTermCoreFail = true;
                            }
                            $termCoreGPAs[] = (float) $gMark['gpa'];
                        }
                    }

                    $termCoreCount = count($termCoreGPAs);
                    if ($hasTermCoreFail || $termCoreCount === 0) {
                        $termGPAWithout4th = '0.00';
                        $termGradeWithout4th = 'F';
                    } else {
                        $rawGpaSum = array_sum($termCoreGPAs) + $optionalBonusPoints;
                        $calcGpa = min(5.00, $rawGpaSum / $termCoreCount);
                        $termGPAWithout4th = number_format($calcGpa, 2);
                        
                        if ($calcGpa >= 5.00) $termGradeWithout4th = 'A+';
                        elseif ($calcGpa >= 4.00) $termGradeWithout4th = 'A';
                        elseif ($calcGpa >= 3.50) $termGradeWithout4th = 'A-';
                        elseif ($calcGpa >= 3.00) $termGradeWithout4th = 'B';
                        elseif ($calcGpa >= 2.00) $termGradeWithout4th = 'C';
                        elseif ($calcGpa >= 1.00) $termGradeWithout4th = 'D';
                        else $termGradeWithout4th = 'F';
                    }
                @endphp
                
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 flex justify-between items-center border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $examName }}</h2>
                            <p class="text-xs font-semibold text-gray-500 mt-0.5">
                                Core GPA: <span class="text-blue-600 dark:text-blue-400 font-bold font-mono">{{ $termGPAWithout4th }}</span> 
                                | Core Grade: <span class="text-blue-600 dark:text-blue-400 font-extrabold">{{ $termGradeWithout4th }}</span>
                            </p>
                        </div>
                        <x-filament::button wire:click="printPdf({{ $examId }})" color="success" icon="heroicon-o-printer">
                            Print Term
                        </x-filament::button>
                    </div>
                    
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-xs text-left border-collapse border border-gray-300 dark:border-gray-600">
                            <thead class="uppercase font-bold">
                                <tr>
                                    <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100">Subject</th>
                                    <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-2 text-center bg-blue-100 dark:bg-blue-900/60 text-blue-900 dark:text-blue-100">Written</th>
                                    <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-2 text-center bg-blue-100 dark:bg-blue-900/60 text-blue-900 dark:text-blue-100">MCQ / Oral</th>
                                    <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-2 text-center bg-emerald-100 dark:bg-emerald-900/60 text-emerald-900 dark:text-emerald-100">Total</th>
                                    <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-2 text-center bg-indigo-100 dark:bg-indigo-900/60 text-indigo-900 dark:text-indigo-100">Combined</th>
                                    <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100">Grade</th>
                                    <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100">GPA</th>
                                </tr>
                                <tr class="text-[10px] bg-gray-200 dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center text-blue-700 dark:text-blue-300 font-extrabold">High</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center">Obt</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center text-blue-700 dark:text-blue-300 font-extrabold">High</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center">Obt</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center">Max</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center text-emerald-700 dark:text-emerald-300 font-extrabold">Obt</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center">Max</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-1 py-1 text-center text-indigo-700 dark:text-indigo-300 font-extrabold">Obt</th>
                                </tr>
                            </thead>
                            
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100">
                                @foreach($groupedMarks as $group)
                                    @if($group['is_combined'])
                                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">{{ $group['paper1']->subject->name ?? 'N/A' }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper1']->subject_id, 'written_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper1']->written_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper1']->subject_id, 'mcq_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper1']->mcq_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-gray-600 dark:text-gray-300">{{ number_format($group['max1'], 0) }}</td>
                                            <td class="px-2 py-2 text-center font-bold text-gray-900 dark:text-white">{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                                            <td rowspan="2" class="px-2 py-2 text-center border-l border-gray-300 dark:border-gray-600 align-middle text-gray-600 dark:text-gray-300">{{ number_format($group['combined_max'], 0) }}</td>
                                            <td rowspan="2" class="px-2 py-2 text-center border-r border-gray-300 dark:border-gray-600 align-middle font-bold text-gray-900 dark:text-white">{{ number_format($group['combined_obt'], 1) }}</td>
                                            <td rowspan="2" class="px-3 py-2 text-center align-middle">
                                                <span class="px-2 py-1 rounded-md text-xs font-bold shadow-sm {{ $group['combined_grade'] === 'F' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-100' : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-100' }}">{{ $group['combined_grade'] }}</span>
                                            </td>
                                            <td rowspan="2" class="px-3 py-2 text-center align-middle font-black text-gray-900 dark:text-white">{{ $group['gpa'] }}</td>
                                        </tr>
                                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">{{ $group['paper2']->subject->name ?? 'N/A' }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper2']->subject_id, 'written_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper2']->written_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper2']->subject_id, 'mcq_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper2']->mcq_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-gray-600 dark:text-gray-300">{{ number_format($group['max2'], 0) }}</td>
                                            <td class="px-2 py-2 text-center font-bold text-gray-900 dark:text-white">{{ number_format($group['paper2']->marks_obtained, 1) }}</td>
                                        </tr>
                                    @else
                                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">{{ $group['paper1']->subject->name ?? 'N/A' }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper1']->subject_id, 'written_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper1']->written_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-300 font-bold">{{ $getHighest($group['paper1']->subject_id, 'mcq_mark') }}</td>
                                            <td class="px-2 py-2 text-center font-semibold">{{ number_format($group['paper1']->mcq_mark, 1) }}</td>
                                            <td class="px-2 py-2 text-center text-gray-600 dark:text-gray-300">{{ number_format($group['max1'], 0) }}</td>
                                            <td class="px-2 py-2 text-center font-bold text-gray-900 dark:text-white">{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                                            <td class="px-2 py-2 text-center border-l border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">{{ number_format($group['max1'], 0) }}</td>
                                            <td class="px-2 py-2 text-center border-r border-gray-300 dark:border-gray-600 font-bold text-gray-900 dark:text-white">{{ number_format($group['paper1']->marks_obtained, 1) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-2 py-1 rounded-md text-xs font-bold shadow-sm {{ $group['combined_grade'] === 'F' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-100' : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-100' }}">{{ $group['combined_grade'] }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center font-black text-gray-900 dark:text-white">{{ $group['gpa'] }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-md border-2 border-indigo-200 dark:border-indigo-800 overflow-hidden mt-8">
                <div class="bg-indigo-100 dark:bg-indigo-900/60 px-6 py-5 flex justify-between items-center border-b border-indigo-200 dark:border-indigo-800">
                    <div>
                        <h2 class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">Final Cumulative Result</h2>
                        <p class="text-indigo-700 dark:text-indigo-300 text-sm mt-1">Combined average of all main exams for the academic year</p>
                    </div>
                    <x-filament::button wire:click="printFinalPdf()" color="primary" size="lg" icon="heroicon-o-trophy">
                        Print Final Marksheet
                    </x-filament::button>
                </div>
                <div class="overflow-x-auto p-4">
                    <table class="w-full text-sm text-left border border-indigo-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-lg overflow-hidden">
                        <thead class="bg-indigo-50 dark:bg-gray-700 text-indigo-900 dark:text-indigo-100 uppercase font-bold text-xs">
                            <tr>
                                <th class="px-6 py-3">Subject</th>
                                <th class="px-6 py-3 text-center">Combined Max</th>
                                <th class="px-6 py-3 text-center">Combined Obtained</th>
                                <th class="px-6 py-3 text-center">Final Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-100 dark:divide-gray-700">
                            
                            @php 
                                $grandTotalMax = 0; 
                                $coreObtainedSum = 0.0; 
                                $optionalBonusMarksFinal = 0.0;
                                
                                $validAllMarks = $allMarks->filter(fn($m) => in_array($m->exam_id, $mainExamIds));
                                $finalGroupedMarks = [];
                                $processedFinalIds = [];

                                // Cumulative Final Result Grouping
                                foreach($validAllMarks as $mark) {
                                    if(in_array($mark->id, $processedFinalIds)) continue;

                                    $partnerMark = null;
                                    if ($mark->subject->linked_subject_id) {
                                        $partnerMark = $validAllMarks->firstWhere('subject_id', $mark->subject->linked_subject_id);
                                    } else {
                                        $partnerMark = $validAllMarks->where('subject.linked_subject_id', $mark->subject_id)->first();
                                        if ($partnerMark) {
                                            $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
                                        }
                                    }

                                    $rules1 = $mark->subject->getMarksForExam($mark->exam_id);
                                    $max1 = $rules1['full_marks'] > 0 ? $rules1['full_marks'] : 100;

                                    if ($partnerMark) {
                                        $rules2 = $partnerMark->subject->getMarksForExam($mark->exam_id);
                                        $max2 = $rules2['full_marks'] > 0 ? $rules2['full_marks'] : 100;
                                        
                                        $combinedMax = $max1 + $max2;
                                        $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;
                                        
                                        $overallPassOnly = ($rules1['overall_pass_only'] ?? $mark->subject->overall_pass_only ?? false) || 
                                                           ($rules2['overall_pass_only'] ?? $partnerMark->subject->overall_pass_only ?? false);

                                        $opm1 = $rules1['overall_pass_mark'] ?? $mark->subject->overall_pass_mark ?? 33;
                                        $opm2 = $rules2['overall_pass_mark'] ?? $partnerMark->subject->overall_pass_mark ?? 33;
                                        $combinedOverallPassMark = $opm1 + $opm2;

                                        if ($overallPassOnly) {
                                            $isComponentFailed = ($combinedObt < $combinedOverallPassMark);
                                        } else {
                                            $pass1 = $rules1['written_pass_mark'] ?? $mark->subject->written_pass_mark ?? 33;
                                            $pass2 = $rules2['written_pass_mark'] ?? $partnerMark->subject->written_pass_mark ?? 33;
                                            $mcq1Pass = $rules1['mcq_pass_mark'] ?? $mark->subject->mcq_pass_mark ?? 0;
                                            $mcq2Pass = $rules2['mcq_pass_mark'] ?? $partnerMark->subject->mcq_pass_mark ?? 0;
                                            $combinedMcqObt = ($mark->mcq_mark ?? 0) + ($partnerMark->mcq_mark ?? 0);
                                            
                                            $mcqFail = (($mcq1Pass + $mcq2Pass) > 0) && ($combinedMcqObt < ($mcq1Pass + $mcq2Pass));
                                            $isComponentFailed = ($combinedObt < ($pass1 + $pass2)) || $mcqFail;
                                        }

                                        $cGrade = $isComponentFailed ? 'F' : $getGradeData($combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0, false)['grade'];

                                        $cleanName = trim(preg_replace('/1st.*|2nd.*/i', '', $mark->subject->name));

                                        $finalGroupedMarks[] = [
                                            'subject_model' => $mark->subject,
                                            'name' => $cleanName,
                                            'max' => $combinedMax,
                                            'obt' => $combinedObt,
                                            'grade' => $cGrade,
                                        ];
                                        
                                        $processedFinalIds[] = $mark->id;
                                        $processedFinalIds[] = $partnerMark->id;
                                    } else {
                                        $finalGroupedMarks[] = [
                                            'subject_model' => $mark->subject,
                                            'name' => $mark->subject->name,
                                            'max' => $max1,
                                            'obt' => $mark->marks_obtained,
                                            'grade' => trim($mark->grade),
                                        ];
                                        $processedFinalIds[] = $mark->id;
                                    }
                                }

                                $cumulativeCorePercentages = [];
                                $hasCumulativeCoreFail = false;
                                $finalOptionalBonusPoints = 0.00;

                                foreach($finalGroupedMarks as $row) {
                                    $subName = strtolower($row['subject_model']->name ?? '');
                                    $subType = strtolower($row['subject_model']->subject_type ?? $row['subject_model']->type ?? '');
                                    $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

                                    if ($isOptional) {
                                        if ($row['obt'] > 40.0) {
                                            $optionalBonusMarksFinal = $row['obt'] - 40.0;
                                        }

                                        $optPerc = $row['max'] > 0 ? ($row['obt'] / $row['max']) * 100 : 0;
                                        $optGradeInfo = $getGradeData($optPerc, false);
                                        if ($optGradeInfo['point'] > 2.00) {
                                            $finalOptionalBonusPoints = $optGradeInfo['point'] - 2.00;
                                        }
                                    } else {
                                        $coreObtainedSum += $row['obt'];

                                        if ($row['grade'] === 'F') {
                                            $hasCumulativeCoreFail = true;
                                        }
                                        $cumulativeCorePercentages[] = $row['max'] > 0 ? ($row['obt'] / $row['max']) * 100 : 0;
                                    }
                                }

                                $grandTotalObtained = $coreObtainedSum + $optionalBonusMarksFinal;

                                $finalCoreCount = count($cumulativeCorePercentages);
                                if ($hasCumulativeCoreFail || $finalCoreCount === 0) {
                                    $finalGradeWithout4th = 'F';
                                    $finalGPAWithout4th = '0.00';
                                } else {
                                    $finalAvgPercentage = array_sum($cumulativeCorePercentages) / $finalCoreCount;
                                    $baseCoreGpa = $getGradeData($finalAvgPercentage)['point'];
                                    $calcFinalGpa = min(5.00, $baseCoreGpa + ($finalOptionalBonusPoints / $finalCoreCount));
                                    
                                    $finalGPAWithout4th = number_format($calcFinalGpa, 2);
                                    if ($calcFinalGpa >= 5.00) $finalGradeWithout4th = 'A+';
                                    elseif ($calcFinalGpa >= 4.00) $finalGradeWithout4th = 'A';
                                    elseif ($calcFinalGpa >= 3.50) $finalGradeWithout4th = 'A-';
                                    elseif ($calcFinalGpa >= 3.00) $finalGradeWithout4th = 'B';
                                    elseif ($calcFinalGpa >= 2.00) $finalGradeWithout4th = 'C';
                                    elseif ($calcFinalGpa >= 1.00) $finalGradeWithout4th = 'D';
                                    else $finalGradeWithout4th = 'F';
                                }
                            @endphp
                            
                            @foreach($finalGroupedMarks as $row)
                                @php
                                    $grandTotalMax += $row['max'];
                                @endphp
                                <tr class="hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors">
                                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                    <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-300">{{ number_format($row['max'], 0) }}</td>
                                    <td class="px-6 py-4 text-center font-black text-indigo-600 dark:text-indigo-400 text-lg">{{ number_format($row['obt'], 1) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-sm font-bold shadow-sm {{ $row['grade'] === 'F' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-100' : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-100' }}">
                                            {{ $row['grade'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            
                            <tr class="bg-indigo-600 dark:bg-indigo-800 text-white font-bold text-sm">
                                <td class="px-6 py-4 text-right">GRAND TOTALS (WITH 4TH SUB) :</td>
                                <td class="px-6 py-4 text-center text-indigo-100">{{ number_format($grandTotalMax, 0) }}</td>
                                <td class="px-6 py-4 text-center text-white font-black">{{ number_format($grandTotalObtained, 1) }}</td>
                                <td class="px-6 py-4 text-center text-base font-black">
                                    {{ $hasCumulativeCoreFail ? 'F' : $getGradeData($grandTotalMax > 0 ? ($grandTotalObtained / $grandTotalMax) * 100 : 0)['grade'] }}
                                </td>
                            </tr>

                            <tr class="bg-blue-700 dark:bg-slate-800 text-white font-bold text-sm border-t border-blue-400">
                                <td class="px-6 py-3 text-right text-yellow-300">CORE RESULT (WITHOUT 4TH SUB) :</td>
                                <td class="px-6 py-3 text-center text-blue-100 font-mono">GPA: {{ $finalGPAWithout4th }}</td>
                                <td class="px-6 py-3 text-center text-white" colspan="2">
                                    FINAL CORE GRADE: <span class="bg-yellow-400 text-blue-900 px-3 py-0.5 rounded-md font-black text-base ml-2 shadow-sm">{{ $finalGradeWithout4th }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    @endif
</x-filament-panels::page>