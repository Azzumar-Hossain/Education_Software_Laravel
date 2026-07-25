<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
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

    // 🌟 ADDED RESET ACTIONS TO THE TOP RIGHT OF THE CORNER PANEL
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

        // 🌟 Pull the custom letter grades flagged as failing items from the database matrix
        $failingGrades = \App\Models\GradeScale::where('is_fail_grade', true)
            ->pluck('letter_grade')
            ->toArray();

        // Safe fallback default if the settings matrix table is empty
        if (empty($failingGrades)) {
            $failingGrades = ['F'];
        }

        foreach ($enrollments as $enrollment) {
            $marksQuery = Mark::where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id']);

            $totalMarks = $marksQuery->sum('marks_obtained');
            $avgGpa     = $marksQuery->avg('gpa') ?? 0.00;
            
            // 🌟 FIXED: Evaluates student failure based on your custom database rules array
            $hasFailed  = $marksQuery->whereIn('grade', $failingGrades)->exists();

            $calculatedRankings[] = [
                'student_id'   => $enrollment->user->student_id,
                'student_name' => $enrollment->user->name,
                'roll_number'  => $enrollment->roll_number,
                'section_name' => $enrollment->section->name ?? 'N/A',
                'group_name'   => $enrollment->study_group ?? 'General',
                'total_marks'  => $totalMarks,
                'final_gpa'    => $hasFailed ? 0.00 : number_format($avgGpa, 2),
                'final_grade'  => $hasFailed ? ($failingGrades[0] ?? 'F') : $this->calculateGradeFromGpa($avgGpa),
                'is_failed'    => $hasFailed,
            ];
        }

        usort($calculatedRankings, function ($a, $b) {
            if ($a['is_failed'] !== $b['is_failed']) {
                return $a['is_failed'] <=> $b['is_failed'];
            }
            if ($b['final_gpa'] != $a['final_gpa']) {
                return $b['final_gpa'] <=> $a['final_gpa'];
            }
            return $b['total_marks'] <=> $a['total_marks'];
        });

        $this->meritRecords = $calculatedRankings;
    }

    // REPLACE THIS AT THE BOTTOM OF app/Filament/Pages/MeritList.php
    private function calculateGradeFromGpa($gpa): string
    {
        // Convert GPA back to a percentage base to compare against your Grade Scales
        $percentageScalar = $gpa * 20;

        $scaleMatch = \App\Models\GradeScale::where('min_mark', '<=', $percentageScalar)
            ->where('max_mark', '>=', $percentageScalar)
            ->first();

        return $scaleMatch ? $scaleMatch->letter_grade : 'F';
    }
}