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
                                    'Science'          => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce'         => 'Commerce',
                                    'General'          => 'General',
                                ])->nullable(),

                            // 🌟 DYNAMIC USER-SELECTABLE ROWS PER PAGE DROPDOWN 🌟
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

        // 1. Fetch available subjects mapped to this specific Class configuration
        $this->subjects = Subject::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
            ->where(function($query) use ($groupName) {
                $query->whereNull('study_group_id')
                      ->when($groupName, function($q) use ($groupName) {
                          $resolvedId = \App\Models\StudyGroup::where('name', $groupName)->first()?->id;
                          if($resolvedId) $q->orWhere('study_group_id', $resolvedId);
                      });
            })
            ->orderBy('code', 'asc')
            ->get();

        // 2. Load matching student roster rows
        $query = Enrollment::where('school_class_id', $classId)
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->when($groupName, fn($q, $g) => $q->where('study_group', $g));

        // 🌟 Scope section filter strictly for Class Teachers
        if (!empty($inputs['section_id'])) {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $assignedSectionIds);
            }
        }

        $this->students = $query
            ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
            ->get();
    }
}