<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Exam;
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

                            // 🌟 3. TARGET EXAM (Filtered dynamically by Academic Year & Class) 🌟
                            Select::make('exam_id')
                                ->label('Target Exam')
                                ->options(function ($get) {
                                    $yearId = $get('academic_year_id');
                                    $classId = $get('school_class_id');

                                    if (!$yearId || !$classId) {
                                        return [];
                                    }

                                    $query = Exam::query()->where('academic_year_id', $yearId);

                                    // Filter by class if school_class_id column exists on Exam model,
                                    // or fetch exams where marks exist for this class & year.
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
                                            'class' => 'Class-wise (Full Grade)',
                                            'group' => 'Group-wise (Stream Focus)',
                                            'section' => 'Section-wise (Classroom Focus)'
                                        ];
                                    }

                                    return [
                                        'class' => 'Class-wise (Full Grade)',
                                        'section' => 'Section-wise (Classroom Focus)'
                                    ];
                                })
                                ->required()
                                ->live(),

                            // 🌟 5. SELECT SECTION / GROUP 🌟
                            Select::make('section_id')
                                ->label('Select Section')
                                ->options(fn ($get) => $get('school_class_id') ? Section::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $get('school_class_id')))->pluck('name', 'id') : [])
                                ->visible(fn ($get) => $get('merit_scope') === 'section')
                                ->required(),

                            Select::make('study_group')
                                ->label('Select Group')
                                ->options([
                                    'Science' => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce' => 'Commerce',
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

        $className = strtolower(SchoolClass::find($inputs['school_class_id'])?->name ?? '');
        $has4thSubjectColumn = str_contains($className, '9') || str_contains($className, '10');

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

            // 1. COMBINED SUBJECTS & SINGLE PAPER GROUPING WITH EXAM-SPECIFIC FULL MARKS
            $groupedMarks = [];
            $processedIds = [];

            foreach ($marks as $mark) {
                if (in_array($mark->id, $processedIds)) continue;

                $partnerMark = null;
                if ($mark->subject->linked_subject_id) {
                    $partnerMark = $marks->firstWhere('subject_id', $mark->subject->linked_subject_id);
                } else {
                    $partnerMark = $marks->where('subject.linked_subject_id', $mark->subject_id)->first();
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

            // 2. SEPARATE CORE AND OPTIONAL SUBJECTS FOR ACCURATE GPA
            $coreGPAs = [];
            $hasCoreFail = false;
            $totalMarksObtained = 0;

            foreach ($groupedMarks as $gMark) {
                $totalMarksObtained += $gMark['combined_obt'];
                $subName = strtolower($gMark['subject_model']->name ?? '');
                $subType = strtolower($gMark['subject_model']->subject_type ?? $gMark['subject_model']->type ?? '');

                if (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional') {
                    // Optional subject handling
                } else {
                    if ($gMark['combined_grade'] === 'F') {
                        $hasCoreFail = true;
                    }
                    $coreGPAs[] = (float) $gMark['gpa'];
                }
            }

            $coreCount = count($coreGPAs);
            $gpaWithout4th = ($hasCoreFail || $coreCount === 0) ? 0.00 : array_sum($coreGPAs) / $coreCount;

            $finalGpaVal = 0.00;
            if (!$hasCoreFail && count($groupedMarks) > 0) {
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
                $finalGpaVal = min(5.00, $rawGpaSum / ($coreCount > 0 ? $coreCount : 1));
            }

            $calculatedGpa = $has4thSubjectColumn ? $finalGpaVal : $gpaWithout4th;
            $finalGpaFormatted = $hasCoreFail ? '0.00' : number_format($calculatedGpa, 2);
            $finalGrade = $this->calculateGradeFromGpa($calculatedGpa, $hasCoreFail);

            $calculatedRankings[] = [
                'student_id'   => $enrollment->user->student_id ?? 'N/A',
                'student_name' => $enrollment->user->name ?? 'N/A',
                'roll_number'  => $enrollment->roll_number,
                'section_name' => $enrollment->section->name ?? 'N/A',
                'group_name'   => $enrollment->study_group ?? 'General',
                'total_marks'  => $totalMarksObtained,
                'final_gpa'    => $finalGpaFormatted,
                'final_grade'  => $finalGrade,
                'is_failed'    => $hasCoreFail,
            ];
        }

        // 3. MERIT RANKING SORT
        usort($calculatedRankings, function ($a, $b) {
            if ($a['is_failed'] !== $b['is_failed']) {
                return $a['is_failed'] <=> $b['is_failed'];
            }
            if ((float)$b['final_gpa'] != (float)$a['final_gpa']) {
                return (float)$b['final_gpa'] <=> (float)$a['final_gpa'];
            }
            return $b['total_marks'] <=> $a['total_marks'];
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
}
