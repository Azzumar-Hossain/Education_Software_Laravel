<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Exam;
use App\Models\Enrollment;
use App\Models\Mark;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class PrintMarksheet extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static string $view = 'filament.pages.print-marksheet';

    public ?array $data = [];
    public array $studentsList = [];

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
                    $this->studentsList = [];
                    
                    Notification::make()
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
                FormSection::make('Generate & Download Batch Marksheets')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 4,
                        ])->schema([
                            // 1. ACADEMIC YEAR
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(fn () => AcademicYear::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('school_class_id', null);
                                    $set('exam_id', null);
                                    $set('section_id', null);
                                    $this->studentsList = [];
                                }),

                            // 2. CLASS
                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(fn () => SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('exam_id', null);
                                    $set('section_id', null);
                                    $this->studentsList = [];
                                }),

                            // 3. TARGET EXAM (Deduplicated, showing "Exam Name (Year)")
                            Select::make('exam_id')
                                ->label('Target Exam')
                                ->options(function ($get) {
                                    $yearId = $get('academic_year_id');
                                    $classId = $get('school_class_id');

                                    if (!$yearId || !$classId) {
                                        return [];
                                    }

                                    $query = Exam::query()->where('academic_year_id', $yearId);

                                    // Filter by school_class_id column if present in exams table
                                    if (\Schema::hasColumn('exams', 'school_class_id')) {
                                        $query->where(function ($q) use ($classId) {
                                            $q->whereNull('school_class_id')
                                              ->orWhere('school_class_id', $classId);
                                        });
                                    }

                                    // Filter by exams that have marks recorded for this class
                                    if (\Schema::hasTable('marks')) {
                                        $examIdsWithMarks = Mark::where('academic_year_id', $yearId)
                                            ->where('school_class_id', $classId)
                                            ->pluck('exam_id')
                                            ->unique();

                                        if ($examIdsWithMarks->isNotEmpty()) {
                                            $query->whereIn('id', $examIdsWithMarks);
                                        }
                                    }

                                    $exams = $query->with('academicYear')->get();

                                    // Deduplicate by Exam Name so each exam title appears once
                                    $uniqueExams = [];
                                    foreach ($exams as $exam) {
                                        $yearName = $exam->academicYear->name ?? '';
                                        $label = "{$exam->name} ({$yearName})";

                                        if (!in_array($label, $uniqueExams)) {
                                            $uniqueExams[$exam->id] = $label;
                                        }
                                    }

                                    return $uniqueExams;
                                })
                                ->placeholder(fn ($get) => (!$get('academic_year_id') || !$get('school_class_id')) 
                                    ? 'Select Year & Class First' 
                                    : 'Select Target Exam'
                                )
                                ->disabled(fn ($get) => !$get('academic_year_id') || !$get('school_class_id'))
                                ->searchable()
                                ->required()
                                ->live(),

                            // 4. SECTION
                            Select::make('section_id')
                                ->label('Select Section')
                                ->options(fn ($get) => $get('school_class_id') 
                                    ? Section::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $get('school_class_id')))->pluck('name', 'id') 
                                    : []
                                )
                                ->placeholder('All Sections')
                                ->live(),
                        ]),
                    ]),
            ]);
    }

    public function generateStudentList()
    {
        $this->validate();
        $inputs = $this->data;

        $query = Enrollment::with(['user', 'section', 'schoolClass'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->where('school_class_id', $inputs['school_class_id']);

        if (!empty($inputs['section_id'])) {
            $query->where('section_id', $inputs['section_id']);
        }

        // 🌟 Sort numerically by roll number (handles string/integer casting properly)
        $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

        $validStudents = [];
        foreach ($enrollments as $e) {
            $hasMarks = Mark::where('student_id', $e->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->where('exam_id', $inputs['exam_id'])
                ->exists();

            if ($hasMarks) {
                $validStudents[] = [
                    'id' => $e->id,
                    'user_id' => $e->user_id,
                    'student_id' => $e->user->student_id ?? 'N/A',
                    'name' => $e->user->name ?? 'N/A',
                    'roll_number' => (int) $e->roll_number, // Cast as integer
                    'section' => $e->section->name ?? 'N/A',
                    'group' => $e->study_group ?? 'General',
                ];
            }
        }

        if (empty($validStudents)) {
            $this->studentsList = [];
            Notification::make()
                ->title('No Marks Found')
                ->body('No student marks entered for this exam under the selected class/section.')
                ->warning()
                ->send();
            return;
        }

        $this->studentsList = $validStudents;
    }

    public function downloadBatchMarksheets()
    {
        $inputs = $this->data;

        if (empty($this->studentsList)) {
            Notification::make()
                ->title('Generate List First')
                ->body('Please select filters and click "Generate Marksheet List" first.')
                ->warning()
                ->send();
            return;
        }

        $studentIds = array_column($this->studentsList, 'id');

        // 🌟 Enforce exact numerical roll order in PDF compilation
        $enrollments = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])
            ->whereIn('id', $studentIds)
            ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
            ->get();

        if ($enrollments->isEmpty()) {
            Notification::make()
                ->title('No Students Found')
                ->warning()
                ->send();
            return;
        }

        $exam = Exam::with('academicYear')->findOrFail($inputs['exam_id']);
        $className = SchoolClass::find($inputs['school_class_id'])?->name ?? 'Class';
        $sectionName = !empty($inputs['section_id']) ? Section::find($inputs['section_id'])?->name : 'All_Sections';

        $pdf = \PDF::loadView('pdf.batch-marksheet', [
            'enrollments' => $enrollments,
            'exam'        => $exam,
        ], [], [
            'mode'              => 'utf-8',
            'format'            => 'A4-P',
            'margin_left'       => 5,
            'margin_right'      => 5,
            'margin_top'        => 5,
            'margin_bottom'     => 5,
            'autoScriptToLang'  => true,
            'autoLangToFont'    => true,
        ]);

        $fileName = "Batch_Marksheets_{$className}_{$sectionName}_" . date('Ymd') . ".pdf";

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $fileName
        );
    }
}