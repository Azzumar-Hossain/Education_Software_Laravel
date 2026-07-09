<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Exam;
use App\Models\Enrollment;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class AdmitCardGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 13; // Neatly positioned below the Notice Board sheet
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $title = 'Admit Card';
    protected static string $view = 'filament.pages.admit-card-generator';

    public ?array $data = [];
    public $enrollments = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    // 🌟 RESET BUTTON SYSTEM
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_filters')
                ->label('Reset Form')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->form->fill();
                    $this->enrollments = [];
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Filters Reset')
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
                FormSection::make('Generate Student Admit Cards')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 4,
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
                                    $set('exam_id', null);
                                }),

                            Select::make('exam_id')
                                ->label('Select Exam')
                                ->options(fn($get) => $get('school_class_id') ? Exam::where('school_class_id', $get('school_class_id'))->pluck('name', 'id') : [])
                                ->required()
                                ->live(),

                            Select::make('section_id')
                                ->label('Section (Optional)')
                                ->options(fn($get) => $get('school_class_id') ? Section::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $get('school_class_id')))->pluck('name', 'id') : [])
                                ->nullable(),
                        ]),
                    ]),
            ]);
    }

    public function generateAdmitCards()
    {
        $this->validate();
        $inputs = $this->data;

        // Fetch targeted student enrollments matching criteria grid
        $this->enrollments = Enrollment::with(['user', 'schoolClass', 'section'])
            ->where('school_class_id', $inputs['school_class_id'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->when($inputs['section_id'], fn($q, $sectionId) => $q->where('section_id', $sectionId))
            ->get();

        if ($this->enrollments->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No Enrolled Students Found')
                ->danger()
                ->send();
        }
    }
}