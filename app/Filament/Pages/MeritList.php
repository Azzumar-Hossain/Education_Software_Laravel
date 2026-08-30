<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Exam;
use App\Models\ClassTeacher;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class MeritList extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static string $view = 'filament.pages.merit-list';

    public ?array $data = [];
    public $meritRecords = [];

    // 🌟 1. DYNAMIC ACCESS AUTHORIZATION 🌟
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->type === 'super_admin'
            || $user->type === 'admin'
            || $user->hasRole(['super_admin', 'admin', 'class_teacher'])
            || $user->can('page_MeritList');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_filters')
                ->label('Reset Form')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->form->fill();
                    $this->meritRecords = [];

                    \Filament\Notifications\Notification::make()
                        ->title('Filters Cleared')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Generate Final Merit List Rankings')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 5,
                        ])->schema([
                            // 🌟 1. ACADEMIC YEAR 🌟
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('exam_id', null);
                                }),

                            // 🌟 2. CLASS 🌟
                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('exam_id', null);
                                    $set('section_id', null);
                                    $set('study_group', null);
                                }),

                            // 🌟 3. TARGET EXAM 🌟
                            Select::make('exam_id')
                                ->label('Target Exam')
                                ->options(function ($get) {
                                    $yearId = $get('academic_year_id');
                                    $classId = $get('school_class_id');

                                    if (!$yearId || !$classId) {
                                        return [];
                                    }

                                    $query = Exam::query()->where('academic_year_id', $yearId);

                                    if (\Schema::hasColumn('exams', 'school_class_id')) {
                                        $query->where(function ($q) use ($classId) {
                                            $q->whereNull('school_class_id')
                                              ->orWhere('school_class_id', $classId);
                                        });
                                    }

                                    return $query->with('academicYear')
                                        ->get()
                                        ->mapWithKeys(function ($exam) {
                                            $yearName = $exam->academicYear->name ?? 'N/A';
                                            return [$exam->id => "{$exam->name} ({$yearName})"];
                                        })
                                        ->toArray();
                                })
                                ->placeholder(fn ($get) => (!$get('academic_year_id') || !$get('school_class_id')) ? 'Select Year & Class First' : 'Select Target Exam')
                                ->disabled(fn ($get) => !$get('academic_year_id') || !$get('school_class_id'))
                                ->searchable()
                                ->required()
                                ->live(),

                            // 🌟 4. RANKING FILTER 🌟
                            Select::make('merit_scope')
                                ->label('Ranking Filter')
                                ->options(function ($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return ['class' => 'Class-wise (Full Rank)'];

                                    $className = SchoolClass::find($classId)?->name ?? '';

                                    if (str_contains($className, '9') || str_contains($className, '10')) {
                                        return [
                                            'class'   => 'Class-wise (Full Grade)',
                                            'group'   => 'Group-wise (Stream Focus)',
                                            'section' => 'Section-wise (Classroom Focus)'
                                        ];
                                    }

                                    return [
                                        'class'   => 'Class-wise (Full Grade)',
                                        'section' => 'Section-wise (Classroom Focus)'
                                    ];
                                })
                                ->required()
                                ->live(),

                            // 🌟 5. SELECT SECTION (FILTERED FOR CLASS TEACHERS) 🌟
                            Select::make('section_id')
                                ->label('Select Section')
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
                                            ->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))
                                            ->pluck('name', 'id');
                                    }

                                    return Section::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))
                                        ->pluck('name', 'id');
                                })
                                ->visible(fn ($get) => $get('merit_scope') === 'section')
                                ->required(),

                            Select::make('study_group')
                                ->label('Select Group')
                                ->options([
                                    'Science'         => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce'        => 'Commerce',
                                ])
                                ->visible(fn ($get) => $get('merit_scope') === 'group')
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function generateMeritList()
    {
        $this->validate();
        $inputs = $this->data;
        $targetExamId = $inputs['exam_id'];

        $query = Enrollment::with(['user', 'schoolClass', 'section'])
            ->where('school_class_id', $inputs['school_class_id'])
            ->where('academic_year_id', $inputs['academic_year_id']);

        if ($inputs['merit_scope'] === 'section') {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($inputs['merit_scope'] === 'group') {
            $query->where('study_group', $inputs['study_group']);
        }

        $enrollments = $query->get();
        $calculatedRankings = [];

        foreach ($enrollments as $enrollment) {
            $marks = Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->where('exam_id', $targetExamId)
                ->get();

            if ($marks->isEmpty()) {
                continue;
            }

            // 🌟 A. FILTER INCOMPATIBLE SUBJECTS (Safeguard) 🌟
            $groupName = strtolower($enrollment->study_group ?? '');
            
            $filteredMarks = $marks->filter(function($mark) use ($groupName) {
                if (!$mark->subject) return false;
                
                $subCode = (string) $mark->subject->code;
                $subName = strtolower($mark->subject->name);
                
                if ($groupName === 'science') {
                    if ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান')) {
                        return false;
                    }
                }
                
                if (in_array($groupName, ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
                    if (in_array($subCode, ['136', '137', '138'])) { // Physics, Chem, Bio
                        return false;
                    }
                }
                return true;
            })->values();

            // 🌟 B. COMBINED SUBJECTS GROUPING 🌟
            $groupedMarks = [];
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

                $r1 = $mark->subject->getMarksForExam($targetExamId);
                $max1 = ($r1 && isset($r1['full_marks']) && $r1['full_marks'] > 0) ? $r1['full_marks'] : 100;

                if ($partnerMark) {
                    $r2 = $partnerMark->subject->getMarksForExam($targetExamId);
                    $max2 = ($r2 && isset($r2['full_marks']) && $r2['full_marks'] > 0) ? $r2['full_marks'] : 100;

                    $combinedMax = $max1 + $max2;
                    $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;
                    $combinedPerc = $combinedMax > 0 ? ($combinedObt / $combinedMax) * 100 : 0;

                    $groupedMarks[] = [
                        'subject_model'  => $mark->subject,
                        'combined_obt'   => $combinedObt,
                        'combined_grade' => $this->getGradeFromPercentage($combinedPerc),
                        'gpa'            => $this->getGpFromPercentage($combinedPerc)
                    ];

                    $processedIds[] = $mark->id;
                    $processedIds[] = $partnerMark->id;
                } else {
                    $perc = $max1 > 0 ? ($mark->marks_obtained / $max1) * 100 : 0;

                    $groupedMarks[] = [
                        'subject_model'  => $mark->subject,
                        'combined_obt'   => $mark->marks_obtained,
                        'combined_grade' => $this->getGradeFromPercentage($perc),
                        'gpa'            => $this->getGpFromPercentage($perc)
                    ];

                    $processedIds[] = $mark->id;
                }
            }

            // 🌟 C. SEPARATE CORE AND OPTIONAL FOR 4TH SUBJECT RULES 🌟
            $coreGPAs = [];
            $hasCoreFail = false;
            $coreTotalMarks = 0.0;

            $optionalBonusMarks = 0.0;
            $optionalBonusPoints = 0.00;

            foreach ($groupedMarks as $gMark) {
                $subName = strtolower($gMark['subject_model']->name ?? '');
                $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');
                $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

                $obt   = (float) $gMark['combined_obt'];
                $gp    = (float) $gMark['gpa'];
                $grade = $gMark['combined_grade'];

                if ($isOptional) {
                    // Rule 1: Marks Bonus above 40
                    if ($obt > 40.0) {
                        $optionalBonusMarks = $obt - 40.0;
                    }
                    // Rule 2: GPA Bonus above 2.00
                    if ($gp > 2.00) {
                        $optionalBonusPoints = $gp - 2.00;
                    }
                } else {
                    $coreTotalMarks += $obt;
                    $coreGPAs[] = $gp;
                    
                    if ($grade === 'F') {
                        $hasCoreFail = true;
                    }
                }
            }

            // Grand Total (Core Sum + 4th Sub Bonus)
            $grandTotalMarks = $coreTotalMarks + $optionalBonusMarks;

            // Final GPA & Grade Calculation
            $coreCount = count($coreGPAs);
            if ($hasCoreFail || $coreCount === 0) {
                $finalGpaVal = 0.00;
                $finalGrade = 'F';
            } else {
                $rawGpaSum = array_sum($coreGPAs) + $optionalBonusPoints;
                $finalGpaVal = min(5.00, $rawGpaSum / $coreCount);
                $finalGrade = $this->calculateGradeFromGpa($finalGpaVal, false);
            }

            $calculatedRankings[] = [
                'student_id'   => $enrollment->user->student_id ?? 'N/A',
                'student_name' => $enrollment->user->name ?? 'N/A',
                'roll_number'  => $enrollment->roll_number,
                'section_name' => $enrollment->section->name ?? 'N/A',
                'group_name'   => $enrollment->study_group ?? 'General',
                'total_marks'  => $grandTotalMarks,
                'final_gpa'    => number_format($finalGpaVal, 2),
                'final_grade'  => $finalGrade,
                'is_failed'    => $hasCoreFail,
            ];
        }

        // 🌟 D. MERIT RANKING SORT 🌟
        usort($calculatedRankings, function ($a, $b) {
            if ($a['is_failed'] !== $b['is_failed']) {
                return $a['is_failed'] <=> $b['is_failed'];
            }
            if ((float)$b['final_gpa'] != (float)$a['final_gpa']) {
                return (float)$b['final_gpa'] <=> (float)$a['final_gpa'];
            }
            return (float)$b['total_marks'] <=> (float)$a['total_marks'];
        });

        $this->meritRecords = $calculatedRankings;
    }

    private function getGpFromPercentage($perc): float
    {
        if ($perc >= 80) return 5.00;
        if ($perc >= 70) return 4.00;
        if ($perc >= 60) return 3.50;
        if ($perc >= 50) return 3.00;
        if ($perc >= 40) return 2.00;
        if ($perc >= 33) return 1.00;
        return 0.00;
    }

    private function getGradeFromPercentage($perc): string
    {
        if ($perc >= 80) return 'A+';
        if ($perc >= 70) return 'A';
        if ($perc >= 60) return 'A-';
        if ($perc >= 50) return 'B';
        if ($perc >= 40) return 'C';
        if ($perc >= 33) return 'D';
        return 'F';
    }

    private function calculateGradeFromGpa($gpa, bool $hasFailed = false): string
    {
        if ($hasFailed || (float)$gpa < 1.00) return 'F';
        $g = (float)$gpa;
        if ($g >= 5.00) return 'A+';
        if ($g >= 4.00) return 'A';
        if ($g >= 3.50) return 'A-';
        if ($g >= 3.00) return 'B';
        if ($g >= 2.00) return 'C';
        if ($g >= 1.00) return 'D';
        return 'F';
    }

    // Add this right before the final closing brace of the MeritList class
    public function printMeritList()
    {
        if (empty($this->meritRecords)) {
            \Filament\Notifications\Notification::make()
                ->title('Generate List First')
                ->warning()
                ->send();
            return;
        }

        $inputs = $this->data;

        $url = route('print.merit.list', [
            'year'    => $inputs['academic_year_id'],
            'class'   => $inputs['school_class_id'],
            'exam'    => $inputs['exam_id'],
            'scope'   => $inputs['merit_scope'] ?? 'class',
            'section' => $inputs['section_id'] ?? null,
            'group'   => $inputs['study_group'] ?? null,
        ]);

        $this->js("window.open('{$url}', '_blank');");
    }
}