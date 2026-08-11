<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Subject;
use App\Models\ClassTeacher;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class FailList extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationIcon = 'heroicon-o-x-circle';
    protected static string $view = 'filament.pages.fail-list';

    public ?array $data = [];
    public array $failRecords = []; // 🌟 Public property initialized as array for Blade

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
            || $user->can('page_FailList');
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
                    $this->failRecords = [];
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Generate Failed Students List')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 5,
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required(),

                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('section_id', null);
                                    $set('study_group', null);
                                }),

                            Select::make('merit_scope')
                                ->label('Ranking Filter')
                                ->options(function($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return ['class' => 'Class-wise (Full Grade)'];
                                    
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

                            // 🌟 2. SECTION SELECTOR (FILTERED FOR CLASS TEACHERS) 🌟
                            Select::make('section_id')
                                ->label('Select Section')
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
                                ->visible(fn($get) => $get('merit_scope') === 'section')
                                ->required(),

                            Select::make('study_group')
                                ->label('Select Group')
                                ->options([
                                    'Science' => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce' => 'Commerce',
                                ])
                                ->visible(fn($get) => $get('merit_scope') === 'group')
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function generateFailList()
    {
        $this->validate();
        $inputs = $this->data;

        $user = auth()->user();
        $query = Enrollment::where('school_class_id', $inputs['school_class_id'])
            ->where('academic_year_id', $inputs['academic_year_id']);

        if (($inputs['merit_scope'] ?? null) === 'section') {
            $query->where('section_id', $inputs['section_id'] ?? null);
        } elseif (($inputs['merit_scope'] ?? null) === 'group') {
            $query->where('study_group', $inputs['study_group'] ?? null);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            // Extra safety: if merit scope is class-wise, enforce class teacher's section
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $assignedSectionIds);
            }
        }

        $enrollments = $query->get();
        $calculatedFailRecords = [];

        // Fetch failing grades
        $failingGrades = \App\Models\GradeScale::where('is_fail_grade', true)
            ->pluck('letter_grade')
            ->toArray();

        if (empty($failingGrades)) {
            $failingGrades = ['F'];
        }

        foreach ($enrollments as $enrollment) {
            $failedMarks = Mark::where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->whereIn('grade', $failingGrades)
                ->get();

            if ($failedMarks->isNotEmpty()) {
                $allMarks = Mark::where('student_id', $enrollment->user_id)
                    ->where('academic_year_id', $inputs['academic_year_id'])
                    ->where('school_class_id', $inputs['school_class_id']);

                $totalMarks = $allMarks->sum('marks_obtained');

                // 🌟 GROUPING LOGIC FOR COMBINED SUBJECTS 🌟
                $failedSubjectNames = [];

                foreach ($failedMarks as $mark) {
                    $subName = Subject::find($mark->subject_id)?->name ?? 'Unknown';
                    $subNameLower = strtolower($subName);
                    
                    if (str_contains($subNameLower, 'bangla')) {
                        // Merge Bangla 1st & 2nd into a single key
                        $failedSubjectNames['bangla'] = "Bang ({$mark->grade})";
                    } elseif (str_contains($subNameLower, 'english')) {
                        // Merge English 1st & 2nd into a single key
                        $failedSubjectNames['english'] = "Engl ({$mark->grade})";
                    } else {
                        // Treat all other subjects normally
                        $cleanName = trim(preg_replace('/\(.*\)/u', '', $subName));
                        $words = explode(' ', $cleanName);
                        $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                        
                        $failedSubjectNames[$mark->subject_id] = substr($prefix, 0, 4) . " ({$mark->grade})";
                    }
                }

                $groupedFailCount = count($failedSubjectNames);
                $failedSubjectsList = implode(', ', $failedSubjectNames);

                $calculatedFailRecords[] = [
                    'student_id'               => $enrollment->user->student_id ?? 'N/A',
                    'student_name'             => $enrollment->user->name ?? 'N/A',
                    'roll_number'              => (int)($enrollment->roll_number ?? 0),
                    'section_name'             => $enrollment->section->name ?? 'N/A',
                    'group_name'                => $enrollment->study_group ?? 'General',
                    'total_marks'              => (float)$totalMarks,
                    'fail_count'               => $groupedFailCount, // 🌟 Updated to use grouped count
                    'failed_subjects_summary'  => $failedSubjectsList,
                ];
            }
        }

        // 🌟 3-PRIORITY SORTING ALGORITHM
        if (!empty($calculatedFailRecords)) {
            usort($calculatedFailRecords, function ($a, $b) {
                // 1st Priority: Minimum failed subjects count comes first (ASC)
                if ($a['fail_count'] !== $b['fail_count']) {
                    return $a['fail_count'] <=> $b['fail_count'];
                }

                // 2nd Priority: Highest total marks gained comes first (DESC)
                if ($b['total_marks'] !== $a['total_marks']) {
                    return $b['total_marks'] <=> $a['total_marks'];
                }

                // 3rd Priority: Lower Roll Number comes first (ASC)
                return $a['roll_number'] <=> $b['roll_number'];
            });

            $this->failRecords = $calculatedFailRecords;
        } else {
            $this->failRecords = [];

            \Filament\Notifications\Notification::make()
                ->title('No Failed Students Found')
                ->body('All students passed or no mark records were found for this selection.')
                ->success()
                ->send();
        }
    }
}