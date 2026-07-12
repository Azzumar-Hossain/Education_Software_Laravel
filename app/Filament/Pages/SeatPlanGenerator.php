<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Exam;
use App\Models\Enrollment;
use App\Models\SeatPlan;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class SeatPlanGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 13; 
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $title = 'Seat Plan Setup';
    protected static string $view = 'filament.pages.seat-plan-generator';

    public ?array $data = [];
    public $previewSeats = [];

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
                    $this->previewSeats = [];
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Seat Allocation & Bench Formation Structure')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 3, // 🌟 Adjusted display spread column layout count to match current fields count perfectly
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required(),

                            Select::make('exam_id')
                                ->label('Target Exam')
                                ->options(function () {
                                    return \App\Models\Exam::with('academicYear')
                                        ->get()
                                        ->mapWithKeys(function ($exam) {
                                            $yearName = $exam->academicYear->name ?? 'N/A';
                                            return [$exam->id => "{$exam->name} ({$yearName})"];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->required(),

                            Select::make('formation')
                                ->label('Bench Seating Formation')
                                ->options([
                                    2 => '2 Students per Bench (Double)',
                                    3 => '3 Students per Bench (Triple)',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('class_slot_1', null);
                                    $set('class_slot_2', null);
                                    $set('class_slot_3', null);
                                }),
                        ]),

                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])->schema([
                            Select::make('class_slot_1')
                                ->label('Class Slot 01')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required(),

                            Select::make('class_slot_2')
                                ->label('Class Slot 02')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required(),

                            Select::make('class_slot_3')
                                ->label('Class Slot 03')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->visible(fn($get) => (int) $get('formation') === 3), 
                        ]),
                    ]),
            ]);
    }

    public function processArrangement()
    {
        $this->validate();
        $inputs = $this->data;
        $formationCount = (int) ($inputs['formation'] ?? 1);
        
        // 🌟 SAFETY FALLBACK ENTRIES
        $roomNumber = $inputs['room_name'] ?? $inputs['room_number'] ?? 'Auto Allocated';
        $maxBenchesAllowed = (int) ($inputs['max_benches'] ?? 999); 

        // 1. Fetch student queues ordered by roll number sequentially
        $slots = [];
        $slots[] = Enrollment::with('user', 'schoolClass')
            ->where('school_class_id', $inputs['class_slot_1'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->orderBy('roll_number', 'asc')->get()->toArray();

        $slots[] = Enrollment::with('user', 'schoolClass')
            ->where('school_class_id', $inputs['class_slot_2'])
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->orderBy('roll_number', 'asc')->get()->toArray();

        if ($formationCount === 3) {
            $slots[] = Enrollment::with('user', 'schoolClass')
                ->where('school_class_id', $inputs['class_slot_3'])
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->orderBy('roll_number', 'asc')->get()->toArray();
        }

        // Find largest enrollment stack to determine outer loop length bound limits
        $maxCount = max(array_map('count', $slots));
        
        $benchIndex = 1;
        $generatedAllocation = [];
        $overflowDetected = false;

        // 🌟 FIXED: Clears using the fallback room variable instead of calling the missing array key directly
        SeatPlan::where('exam_id', $inputs['exam_id'])
            ->where('room_number', $roomNumber)
            ->delete();

        // 2. Weave students across positions sequentially 
        for ($i = 0; $i < $maxCount; $i++) {
            if ($benchIndex > $maxBenchesAllowed) {
                $overflowDetected = true;
                break;
            }

            $currentBenchCluster = [];
            $hasDataThisRow = false;

            for ($position = 1; $position <= $formationCount; $position++) {
                $slotData = $slots[$position - 1][$i] ?? null;

                if ($slotData) {
                    $hasDataThisRow = true;
                    
                    // 🌟 FIXED: Saves database structure mapped cleanly via local fallback variable
                    SeatPlan::create([
                        'academic_year_id' => $inputs['academic_year_id'],
                        'exam_id' => $inputs['exam_id'],
                        'room_number' => $roomNumber,
                        'bench_number' => $benchIndex,
                        'seat_position' => $position,
                        'student_id' => $slotData['user_id'],
                        'school_class_id' => $slotData['school_class_id'],
                        'roll_number' => $slotData['roll_number']
                    ]);

                    $currentBenchCluster[] = [
                        'bench' => $benchIndex,
                        'position' => $position,
                        'student_name' => $slotData['user']['name'] ?? 'Unknown',
                        'student_id' => $slotData['user']['student_id'] ?? '',
                        'class_name' => $slotData['school_class']['name'] ?? '',
                        'roll' => $slotData['roll_number']
                    ];
                }
            }

            if ($hasDataThisRow) {
                $generatedAllocation[$benchIndex] = $currentBenchCluster;
                $benchIndex++;
            }
        }

        $this->previewSeats = $generatedAllocation;
        
        $this->dispatch('refreshComponent');

        if ($overflowDetected) {
            \Filament\Notifications\Notification::make()
                ->title('Room filled to capacity limit!')
                ->body("Allocated first {$maxBenchesAllowed} benches cleanly. Remaining overflow students must be distributed into another classroom layout sheet.")
                ->warning()
                ->persistent()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Seat Layout Generated Successfully!')
                ->body('Desk slips and room allocation matrix saved cleanly.')
                ->success()
                ->send();
        }
    }
}