<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Mark;
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

    public function mount(): void
    {
        $this->form->fill();
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
                            'md' => 5,
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
                            Select::make('section_id')
                                ->label('Section')
                                ->options(fn($get) => $get('school_class_id') ? Section::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $get('school_class_id')))->pluck('name', 'id') : [])
                                ->nullable(),
                            Select::make('study_group')
                                ->label('Study Group')
                                ->options([
                                    'Science' => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce' => 'Commerce',
                                    'General' => 'General',
                                ])->nullable(),
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
        $this->students = Enrollment::where('school_class_id', $classId)
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->when($inputs['section_id'], fn($q, $s) => $q->where('section_id', $s))
            ->when($groupName, fn($q, $g) => $q->where('study_group', $g))
            ->orderBy('roll_number', 'asc')
            ->get();
    }
}