<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Mark;
use App\Models\ClassTeacher;
use App\Models\StudyGroup;
use App\Models\GradeScale;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class TabulationSheet extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $title = 'Tabulation Sheet';
    protected static string $view = 'filament.pages.tabulation-sheet';

    public ?array $data = [];
    public $students = [];
    public $subjects = [];

    // 🌟 1. DYNAMIC ACCESS CONTROL 🌟
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->type === 'super_admin'
            || $user->type === 'admin'
            || $user->hasRole(['super_admin', 'admin', 'class_teacher'])
            || $user->can('page_TabulationSheet');
    }

    public function mount(): void
    {
        $this->form->fill([
            'rows_per_page' => 7, // Set default fallback
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Filter Tabulation Sheet Criteria')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 6,
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required(),

                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live(),

                            Select::make('exam_id')
                                ->label('Exam')
                                ->options(fn($get) => $get('school_class_id') ? Exam::where('school_class_id', $get('school_class_id'))->pluck('name', 'id') : [])
                                ->required(),

                            // 🌟 2. SECTION SELECTOR (FILTERED FOR CLASS TEACHERS) 🌟
                            Select::make('section_id')
                                ->label('Section')
                                ->options(function ($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return [];

                                    $user = auth()->user();

                                    if ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
                                        $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)
                                            ->pluck('section_id')
                                            ->unique()
                                            ->filter();

                                        return Section::whereIn('id', $assignedSectionIds)
                                            ->whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                            ->pluck('name', 'id');
                                    }

                                    return Section::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                        ->pluck('name', 'id');
                                })
                                ->nullable(),

                            Select::make('study_group')
                                ->label('Study Group')
                                ->options([
                                    'Science'         => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce'        => 'Commerce',
                                    'General'         => 'General',
                                ])->nullable(),

                            Select::make('rows_per_page')
                                ->label('Rows / Page')
                                ->options([
                                    '5'  => '5 Rows / Page',
                                    '6'  => '6 Rows / Page',
                                    '7'  => '7 Rows / Page (Recommended)',
                                    '8'  => '8 Rows / Page',
                                    '10' => '10 Rows / Page',
                                    '12' => '12 Rows / Page',
                                ])
                                ->default(7)
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function submit()
    {
        $this->validate();
        $inputs = $this->data;

        $classId = $inputs['school_class_id'];
        $groupName = $inputs['study_group'];
        $user = auth()->user();

        // 🌟 3. FETCH & FILTER SUBJECTS STRICTLY BASED ON STUDY GROUP 🌟
        $boardSubjectOrder = [
            '101', '102', '107', '108', '109', '127', '150', '111', '112', '154', 
            '153', '140', '110', '126', '136', '137', '138', '134'
        ];

        $subjectQuery = Subject::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
            ->where(function($query) use ($groupName) {
                $query->whereNull('study_group_id')
                      ->when($groupName, function($q) use ($groupName) {
                          $resolvedId = StudyGroup::where('name', $groupName)->first()?->id;
                          if($resolvedId) $q->orWhere('study_group_id', $resolvedId);
                      });
            });

        // Exclude General Science (127) for Science Group
        if (strtolower($groupName) === 'science') {
            $subjectQuery->where('code', '!=', '127')
                         ->where('name', 'not like', '%General Science%')
                         ->where('name', 'not like', '%সাধারণ বিজ্ঞান%');
        }

        // Exclude Pure Science subjects for Arts/Commerce
        if (in_array(strtolower($groupName), ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
            $subjectQuery->whereNotIn('code', ['136', '137', '138']); // Physics, Chemistry, Biology
        }

        $allSubjects = $subjectQuery->get();

        // Sort subjects according to Bangladesh National Curriculum Sequence
        $this->subjects = $allSubjects->sortBy(function($sub) use ($boardSubjectOrder) {
            $code = (string) ($sub->code ?? '');
            $idx = array_search($code, $boardSubjectOrder);
            return $idx !== false ? $idx : 999;
        })->values();

        // 🌟 4. LOAD MATCHING STUDENT ROSTER ROWS 🌟
        $query = Enrollment::with(['user', 'schoolClass', 'section'])
            ->where('school_class_id', $classId)
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->when($groupName, fn($q, $g) => $q->where('study_group', $g));

        if (!empty($inputs['section_id'])) {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $assignedSectionIds);
            }
        }

        $enrollments = $query->get();

        // 🌟 5. CALCULATE ACCURATE GPA, MARKS, GRADE & FAIL COUNT PER STUDENT 🌟
        $studentResults = [];

        foreach ($enrollments as $enrollment) {
            $allMarks = Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $classId)
                ->where('exam_id', $inputs['exam_id'])
                ->get();

            // Filter out incompatible subjects for calculations
            $filteredMarks = $allMarks->filter(function($mark) use ($groupName) {
                if (!$mark->subject) return false;
                $subCode = (string) $mark->subject->code;
                $subName = strtolower($mark->subject->name);
                
                if (strtolower($groupName) === 'science') {
                    if ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান')) return false;
                }
                if (in_array(strtolower($groupName), ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
                    if (in_array($subCode, ['136', '137', '138'])) return false;
                }
                return true;
            })->values();

            $coreObtainedSum = 0.0;
            $coreGPAs = [];
            $failedSubjectsCount = 0;
            
            $optionalBonusMarks = 0.0;
            $optionalBonusPoints = 0.00;

            $processedIds = [];

            foreach ($filteredMarks as $mark) {
                if (in_array($mark->id, $processedIds)) continue;

                $partnerMark = null;
                if ($mark->subject->linked_subject_id) {
                    $partnerMark = $filteredMarks->firstWhere('subject_id', $mark->subject->linked_subject_id);
                } else {
                    $partnerMark = $filteredMarks->where('subject.linked_subject_id', $mark->subject_id)->first();
                    if ($partnerMark) {
                        $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
                    }
                }

                $subName = strtolower($mark->subject->name ?? '');
                $subType = strtolower($mark->subject->subject_type ?? $mark->subject->type ?? '');
                
                $isOptional = (
                    str_contains($subName, 'higher mathematics') || 
                    str_contains($subName, 'agriculture') || 
                    $subType === 'optional' ||
                    (int) $enrollment->optional_subject_id === (int) $mark->subject_id
                );

                if ($partnerMark) {
                    $rules1 = $mark->subject->getMarksForExam($inputs['exam_id']);
                    $rules2 = $partnerMark->subject->getMarksForExam($inputs['exam_id']);
                    
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

                    $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;
                    $combinedMax = ($rules1['full_marks'] ?? 100) + ($rules2['full_marks'] ?? 100);
                    $perc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;

                    if ($overallPassOnly) {
                        $isComponentFailed = ($combinedObt < $combinedOverallPassMark);
                    } else {
                        $mcqFail = ($combinedMcqPass > 0) && ($combinedMcqObt < $combinedMcqPass);
                        $isComponentFailed = ($combinedObt < $combinedRequiredPass) || $mcqFail;
                    }

                    $gp = GradeScale::getGradeForMark($perc, $isComponentFailed)['point'];

                    if ($isOptional) {
                        if ($combinedObt > 40.0) {
                            $optionalBonusMarks = $combinedObt - 40.0;
                        }
                        if ($gp > 2.00) {
                            $optionalBonusPoints = $gp - 2.00;
                        }
                    } else {
                        $coreObtainedSum += $combinedObt;
                        $coreGPAs[] = $gp;
                        if ($isComponentFailed) {
                            $failedSubjectsCount++;
                        }
                    }

                    $processedIds[] = $mark->id;
                    $processedIds[] = $partnerMark->id;
                } else {
                    $obt = (float) $mark->marks_obtained;
                    $gp  = (float) $mark->gpa;

                    if ($isOptional) {
                        if ($obt > 40.0) {
                            $optionalBonusMarks = $obt - 40.0;
                        }
                        if ($gp > 2.00) {
                            $optionalBonusPoints = $gp - 2.00;
                        }
                    } else {
                        $coreObtainedSum += $obt;
                        $coreGPAs[] = $gp;
                        if (trim($mark->grade) === 'F') {
                            $failedSubjectsCount++;
                        }
                    }

                    $processedIds[] = $mark->id;
                }
            }

            // Calculate Grand Total (Core Sum + 4th Sub Marks above 40)
            $grandTotalMarks = $coreObtainedSum + $optionalBonusMarks;

            // Calculate Final GPA & Grade
            $coreCount = count($coreGPAs);
            $hasCoreFail = ($failedSubjectsCount > 0);

            if ($hasCoreFail || $coreCount === 0) {
                $finalGPA = '0.00';
                $finalGrade = 'F';
            } else {
                $rawGpaSum = array_sum($coreGPAs) + $optionalBonusPoints;
                $calcGpa = min(5.00, $rawGpaSum / $coreCount);
                $finalGPA = number_format($calcGpa, 2);

                if ($calcGpa >= 5.00) $finalGrade = 'A+';
                elseif ($calcGpa >= 4.00) $finalGrade = 'A';
                elseif ($calcGpa >= 3.50) $finalGrade = 'A-';
                elseif ($calcGpa >= 3.00) $finalGrade = 'B';
                elseif ($calcGpa >= 2.00) $finalGrade = 'C';
                elseif ($calcGpa >= 1.00) $finalGrade = 'D';
                else $finalGrade = 'F';
            }

            $studentResults[] = [
                'enrollment'    => $enrollment,
                'marks'         => $allMarks->keyBy('subject_id'),
                'grand_total'   => $grandTotalMarks,
                'gpa'           => $finalGPA,
                'grade'         => $finalGrade,
                'has_core_fail' => $hasCoreFail,
                'fail_count'    => $failedSubjectsCount,
            ];
        }

        // 🌟 6. MULTI-PRIORITY RANKING SORT ALGORITHM 🌟
        usort($studentResults, function ($a, $b) {
            // Priority 1: Passed students always come before Failed students
            if ($a['has_core_fail'] !== $b['has_core_fail']) {
                return $a['has_core_fail'] <=> $b['has_core_fail']; // false (0 - Pass) comes before true (1 - Fail)
            }

            // If BOTH students PASSED: Rank by Total Marks (DESC), then GPA (DESC)
            if (!$a['has_core_fail']) {
                if ((float)$b['grand_total'] != (float)$a['grand_total']) {
                    return (float)$b['grand_total'] <=> (float)$a['grand_total'];
                }
                return (float)$b['gpa'] <=> (float)$a['gpa'];
            }

            // If BOTH students FAILED:
            // Priority 2: Fewer failed subjects comes first (ASC)
            if ($a['fail_count'] !== $b['fail_count']) {
                return $a['fail_count'] <=> $b['fail_count'];
            }

            // Priority 3: Higher total marks comes first (DESC)
            return (float)$b['grand_total'] <=> (float)$a['grand_total'];
        });

        $this->students = $studentResults;
    }

    // Add this right before the final closing brace of the TabulationSheet class
    public function printTabulationSheet()
    {
        if (empty($this->students)) {
            \Filament\Notifications\Notification::make()
                ->title('Generate Ledger First')
                ->warning()
                ->send();
            return;
        }

        $inputs = $this->data;

        $url = route('print.tabulation.sheet', [
            'year'          => $inputs['academic_year_id'],
            'class'         => $inputs['school_class_id'],
            'exam'          => $inputs['exam_id'],
            'section'       => $inputs['section_id'] ?? null,
            'group'         => $inputs['study_group'] ?? null,
            'rows_per_page' => $inputs['rows_per_page'] ?? 7,
        ]);

        $this->js("window.open('{$url}', '_blank');");
    }
}