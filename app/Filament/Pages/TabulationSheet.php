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

                                    // If Class Teacher, filter dropdown options to assigned sections only
                                    if ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
                                        $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)
                                            ->pluck('section_id')
                                            ->unique()
                                            ->filter();

                                        return Section::whereIn('id', $assignedSectionIds)
                                            ->whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                            ->pluck('name', 'id');
                                    }

                                    // Admin / Super Admin view all sections
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

        $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

        // 🌟 5. CALCULATE ACCURATE GPA, MARKS, AND GRADE PER STUDENT 🌟
        $studentResults = [];

        foreach ($enrollments as $enrollment) {
            $marks = Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $classId)
                ->where('exam_id', $inputs['exam_id'])
                ->get();

            $coreObtainedSum = 0.0;
            $coreGPAs = [];
            $hasCoreFail = false;
            
            $optionalBonusMarks = 0.0;
            $optionalBonusPoints = 0.00;

            foreach ($marks as $mark) {
                if (!$mark->subject) continue;

                $subName = strtolower($mark->subject->name ?? '');
                $subType = strtolower($mark->subject->subject_type ?? $mark->subject->type ?? '');
                $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

                $obt = (float) $mark->marks_obtained;
                $gp  = (float) $mark->gpa;

                if ($isOptional) {
                    // Rule 1: Add marks above 40 to Grand Total (Obtained - 40)
                    if ($obt > 40.0) {
                        $optionalBonusMarks = $obt - 40.0;
                    }

                    // Rule 2: Add GPA points above 2.00 to GPA accumulator (GP - 2.00)
                    if ($gp > 2.00) {
                        $optionalBonusPoints = $gp - 2.00;
                    }
                    // Optional subject fail DOES NOT set $hasCoreFail
                } else {
                    $coreObtainedSum += $obt;

                    if (trim($mark->grade) === 'F') {
                        $hasCoreFail = true;
                    }
                    $coreGPAs[] = $gp;
                }
            }

            // Calculate Grand Total (Core Sum + 4th Sub Marks above 40)
            $grandTotalMarks = $coreObtainedSum + $optionalBonusMarks;

            // Calculate Final GPA & Grade
            $coreCount = count($coreGPAs);
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
                'marks'         => $marks->keyBy('subject_id'),
                'grand_total'   => $grandTotalMarks,
                'gpa'           => $finalGPA,
                'grade'         => $finalGrade,
                'has_core_fail' => $hasCoreFail,
            ];
        }

        $this->students = $studentResults;
    }
}