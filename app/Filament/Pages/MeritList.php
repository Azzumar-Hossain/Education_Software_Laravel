<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\GradeScale;
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

                            Select::make('section_id')
                                ->label('Select Section')
                                ->options(fn($get) => $get('school_class_id') ? Section::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $get('school_class_id')))->pluck('name', 'id') : [])
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

    public function generateMeritList()
    {
        $this->validate();
        $inputs = $this->data;

        $query = Enrollment::where('school_class_id', $inputs['school_class_id'])
            ->where('academic_year_id', $inputs['academic_year_id']);

        if ($inputs['merit_scope'] === 'section') {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($inputs['merit_scope'] === 'group') {
            $query->where('study_group', $inputs['study_group']);
        }

        $enrollments = $query->get();
        $calculatedRankings = [];

        // 🌟 Pull fail grades defined in settings
        $failingGrades = GradeScale::where('is_fail_grade', true)
            ->pluck('letter_grade')
            ->toArray();

        if (empty($failingGrades)) {
            $failingGrades = ['F'];
        }

        foreach ($enrollments as $enrollment) {
            // Query marks directly associated with the student and academic context
            $studentMarks = Mark::where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->get();

            if ($studentMarks->isEmpty()) {
                continue; // No marks entered yet
            }

            // 🌟 Check if student failed in any subject
            $hasFailed = $studentMarks->contains(function ($mark) use ($failingGrades) {
                return in_array($mark->grade, $failingGrades);
            });

            // Exclude failed students completely from Merit Standings List
            if ($hasFailed) {
                continue;
            }

            // Calculate aggregate marks and exact GPA average across taken subjects
            $totalMarks   = $studentMarks->sum('marks_obtained');
            $subjectCount = $studentMarks->count();
            $gpaSum       = $studentMarks->sum('gpa');
            $avgGpa       = $subjectCount > 0 ? ($gpaSum / $subjectCount) : 0.00;

            $calculatedRankings[] = [
                'student_id'   => $enrollment->user->student_id ?? $enrollment->user->id,
                'student_name' => $enrollment->user->name,
                'roll_number'  => $enrollment->roll_number,
                'section_name' => $enrollment->section->name ?? 'N/A',
                'group_name'   => $enrollment->study_group ?? 'General',
                'total_marks'  => $totalMarks,
                'final_gpa'    => number_format($avgGpa, 2),
                'final_grade'  => $this->getGradeLetterFromGpa($avgGpa),
                'is_failed'    => false,
            ];
        }

        // 🌟 RANK PASSED STUDENTS BY GPA (DESC), THEN TOTAL MARKS (DESC)
        usort($calculatedRankings, function ($a, $b) {
            if ((float)$b['final_gpa'] !== (float)$a['final_gpa']) {
                return (float)$b['final_gpa'] <=> (float)$a['final_gpa'];
            }
            return (float)$b['total_marks'] <=> (float)$a['total_marks'];
        });

        $this->meritRecords = $calculatedRankings;
    }

    /**
     * Maps calculated GPA directly to official grade letters (A+, A, A-, B, C, D)
     */
    private function getGradeLetterFromGpa($gpa): string
    {
        $gpaFloat = (float) $gpa;

        if ($gpaFloat >= 5.00) return 'A+';
        if ($gpaFloat >= 4.00) return 'A';
        if ($gpaFloat >= 3.50) return 'A-';
        if ($gpaFloat >= 3.00) return 'B';
        if ($gpaFloat >= 2.00) return 'C';
        if ($gpaFloat >= 1.00) return 'D';

        return 'F';
    }
}