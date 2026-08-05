<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Exam;
use App\Models\ExamRoutine;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class PrintExamRoutine extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $title = 'Print Exam Routine';
    protected static string $view = 'filament.pages.print-exam-routine';

    public ?array $data = [];
    public $routines = [];
    public ?string $schoolLogo = null;
    public ?string $schoolName = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->type === 'super_admin'
            || $user->type === 'admin'
            || $user->hasRole(['super_admin', 'admin', 'class_teacher'])
            || $user->can('page_PrintExamRoutine');
    }

    public function mount(): void
    {
        $this->form->fill();
        
        // Fetch dynamic school logo and name from settings model if available
        if (class_exists('\App\Models\Setting')) {
            $setting = \App\Models\Setting::first();
            $this->schoolLogo = $setting?->logo ? asset('storage/' . $setting->logo) : asset('images/logo.png');
            $this->schoolName = $setting?->site_name ?? 'Harimohan Govt. High School';
        } else {
            $this->schoolLogo = asset('images/logo.png');
            $this->schoolName = 'Harimohan Govt. High School';
        }
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
                    $this->routines = [];
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Generate Exam Routine')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required()
                                ->live(),

                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live(),

                            Select::make('exam_id')
                                ->label('Exam')
                                ->options(function ($get) {
                                    $yearId = $get('academic_year_id');
                                    $classId = $get('school_class_id');
                                    if (!$yearId || !$classId) return [];

                                    return Exam::where('academic_year_id', $yearId)
                                        ->where(function ($q) use ($classId) {
                                            $q->whereNull('school_class_id')
                                              ->orWhere('school_class_id', $classId);
                                        })
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->live(),
                        ]),
                    ]),
            ]);
    }

    public function generateRoutine()
    {
        $this->validate();
        $inputs = $this->data;

        $this->routines = ExamRoutine::with(['subject', 'schoolClass', 'exam', 'academicYear'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->where('school_class_id', $inputs['school_class_id'])
            ->where('exam_id', $inputs['exam_id'])
            ->orderBy('exam_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($this->routines->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No Routine Found')
                ->body('No exam routine schedule found for this selection.')
                ->warning()
                ->send();
        }
    }
}