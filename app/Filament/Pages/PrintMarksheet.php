<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Exam;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\ClassTeacher;
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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->type === 'super_admin' || $user->type === 'admin' || $user->hasRole(['super_admin', 'admin', 'class_teacher']) || $user->can('page_PrintMarksheet');
    }

    public function mount(): void
    {
        $this->form->fill(['result_type' => 'term']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_filters')
                ->label('Reset Form')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->form->fill(['result_type' => 'term']);
                    $this->studentsList = [];
                    Notification::make()->title('Filters Cleared')->success()->send();
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
                        Grid::make(['default' => 1, 'md' => 5])->schema([
                            
                            Select::make('result_type')
                                ->label('Marksheet Type')
                                ->options([
                                    'term' => 'Term Exam Marksheet',
                                    'final' => 'Final Cumulative Result',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->studentsList = []),

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

                            Select::make('exam_id')
                                ->label('Target Exam')
                                ->visible(fn ($get) => $get('result_type') === 'term')
                                ->required(fn ($get) => $get('result_type') === 'term')
                                ->options(function ($get) {
                                    $yearId = $get('academic_year_id');
                                    $classId = $get('school_class_id');
                                    if (!$yearId || !$classId) return [];

                                    $query = Exam::query()->where('academic_year_id', $yearId);
                                    if (\Schema::hasColumn('exams', 'school_class_id')) {
                                        $query->where(function ($q) use ($classId) {
                                            $q->whereNull('school_class_id')->orWhere('school_class_id', $classId);
                                        });
                                    }
                                    if (\Schema::hasTable('marks')) {
                                        $examIdsWithMarks = Mark::where('academic_year_id', $yearId)->where('school_class_id', $classId)->pluck('exam_id')->unique();
                                        if ($examIdsWithMarks->isNotEmpty()) $query->whereIn('id', $examIdsWithMarks);
                                    }

                                    $exams = $query->with('academicYear')->get();
                                    $uniqueExams = [];
                                    foreach ($exams as $exam) {
                                        $label = "{$exam->name} ({$exam->academicYear->name})";
                                        if (!in_array($label, $uniqueExams)) $uniqueExams[$exam->id] = $label;
                                    }
                                    return $uniqueExams;
                                })
                                ->placeholder(fn ($get) => (!$get('academic_year_id') || !$get('school_class_id')) ? 'Select Year & Class First' : 'Select Target Exam')
                                ->searchable()
                                ->live(),

                            Select::make('section_id')
                                ->label('Select Section')
                                ->options(function ($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return [];
                                    $user = auth()->user();

                                    if ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
                                        $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
                                        return Section::whereIn('id', $assignedSectionIds)->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))->pluck('name', 'id');
                                    }
                                    return Section::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))->pluck('name', 'id');
                                })
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
        $user = auth()->user();
        $resultType = $inputs['result_type'] ?? 'term';

        $query = Enrollment::with(['user', 'section', 'schoolClass'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->where('school_class_id', $inputs['school_class_id']);

        if (!empty($inputs['section_id'])) {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) $query->whereIn('section_id', $assignedSectionIds);
        }

        $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();
        $validStudents = [];

        foreach ($enrollments as $e) {
            $hasMarksQuery = Mark::where('student_id', $e->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id']);

            if ($resultType === 'term') {
                $hasMarksQuery->where('exam_id', $inputs['exam_id']);
            }

            if ($hasMarksQuery->exists()) {
                $validStudents[] = [
                    'id'          => $e->id,
                    'user_id'     => $e->user_id,
                    'student_id'  => $e->user->student_id ?? 'N/A',
                    'name'        => $e->user->name ?? 'N/A',
                    'roll_number' => (int) $e->roll_number,
                    'section'     => $e->section->name ?? 'N/A',
                    'group'       => $e->study_group ?? 'General',
                ];
            }
        }

        if (empty($validStudents)) {
            $this->studentsList = [];
            Notification::make()->title('No Marks Found')->warning()->send();
            return;
        }

        $this->studentsList = $validStudents;
    }

    // This handles the correct routing redirect
    public function downloadBatchMarksheets()
    {
        $inputs = $this->data;

        if (empty($this->studentsList)) {
            Notification::make()->title('Generate List First')->warning()->send();
            return;
        }

        $resultType = $inputs['result_type'] ?? 'term';

        if ($resultType === 'final') {
            $url = route('print.batch.final.marksheet', [
                'year'    => $inputs['academic_year_id'],
                'class'   => $inputs['school_class_id'],
                'section' => $inputs['section_id'] ?? 'N/A',
            ]);
        } else {
            $url = route('print.batch.marksheet', [
                'year'    => $inputs['academic_year_id'],
                'class'   => $inputs['school_class_id'],
                'exam'    => $inputs['exam_id'],
                'section' => $inputs['section_id'] ?? 'N/A',
            ]);
        }

        $this->js("window.open('{$url}', '_blank');");
    }

    // Safety fallback in case your blade button is wired to 'printBatch'
    public function printBatch()
    {
        $this->downloadBatchMarksheets();
    }
}