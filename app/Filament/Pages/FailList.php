<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Subject;
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
    public $failedRecords = [];

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
                    $this->failedRecords = [];
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

    public function generateFailList()
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
        $records = [];

        // 🌟 Fetch all letter grades manually designated as failing filters from your database matrix
        $failingGrades = \App\Models\GradeScale::where('is_fail_grade', true)
            ->pluck('letter_grade')
            ->toArray();

        // Safe fallback array to avoid SQL syntax issues if the user config table is empty
        if (empty($failingGrades)) {
            $failingGrades = ['F'];
        }

        foreach ($enrollments as $enrollment) {
            // 🌟 FIXED Context Layer: Look directly into the mark entry records row for the grade property
            $failedMarks = Mark::where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->whereIn('grade', $failingGrades)
                ->get();

            // Only include students who have at least one failed subject
            if ($failedMarks->isNotEmpty()) {
                $allMarks = Mark::where('student_id', $enrollment->user_id)
                    ->where('academic_year_id', $inputs['academic_year_id'])
                    ->where('school_class_id', $inputs['school_class_id']);

                $totalMarks = $allMarks->sum('marks_obtained');

                // Generate comma-separated string of failed subjects
                $failedSubjectsList = $failedMarks->map(function ($mark) {
                    $subName = Subject::find($mark->subject_id)?->name ?? 'Unknown';
                    
                    // Shorten subject name representation
                    $cleanName = trim(preg_replace('/\(.*\)/u', '', $subName));
                    $words = explode(' ', $cleanName);
                    $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                    
                    return substr($prefix, 0, 4) . " ({$mark->grade})";
                })->implode(', ');

                $records[] = [
                    'student_id'   => $enrollment->user->student_id,
                    'student_name' => $enrollment->user->name,
                    'roll_number'  => $enrollment->roll_number,
                    'section_name' => $enrollment->section->name ?? 'N/A',
                    'group_name'   => $enrollment->study_group ?? 'General',
                    'total_marks'  => $totalMarks,
                    'failed_count' => $failedMarks->count(),
                    'failed_list'  => $failedSubjectsList,
                ];
            }
        }

        // Sort by number of failed subjects descending, then by roll number ascending
        usort($records, function ($a, $b) {
            if ($b['failed_count'] !== $a['failed_count']) {
                return $b['failed_count'] <=> $a['failed_count'];
            }
            return $a['roll_number'] <=> $b['roll_number'];
        });

        $this->failedRecords = $records;
    }
}