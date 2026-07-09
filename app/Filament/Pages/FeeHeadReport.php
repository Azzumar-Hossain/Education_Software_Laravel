<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\SchoolClass;
use App\Models\Enrollment;
use Carbon\Carbon;

class FeeHeadReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Collection by Head';
    protected static ?string $title = 'Fee Head Collection Report';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.fee-head-report';

    public ?array $filterData = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            'school_class_id' => null,
            'enrollment_id' => null, // Added default for student
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('Filter Report Data')
                    ->schema([
                        Components\DatePicker::make('start_date')
                            ->label('From Date')
                            ->required()
                            ->live(),
                            
                        Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->required()
                            ->live(),
                            
                        Components\Select::make('school_class_id')
                            ->label('Filter by Class')
                            ->options(SchoolClass::pluck('name', 'id'))
                            ->placeholder('All Classes (Default)')
                            ->live()
                            // Automatically clear the student selection if the class changes!
                            ->afterStateUpdated(fn (Set $set) => $set('enrollment_id', null)),
                            
                        // --- NEW: SMART STUDENT FILTER ---
                        Components\Select::make('enrollment_id')
                            ->label('Filter by Student')
                            ->options(function (Get $get) {
                                $classId = $get('school_class_id');
                                return Enrollment::with('user')
                                    ->when($classId, fn($q) => $q->where('school_class_id', $classId))
                                    ->get()
                                    ->mapWithKeys(fn ($record) => [
                                        $record->id => "{$record->user->name} (Roll: {$record->roll_number})"
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('All Students (Default)')
                            ->live(),
                            
                    ])->columns(4) // Fits perfectly in one row
            ])
            ->statePath('filterData');
    }

    public function getReportDataProperty(): array
    {
        $dates = $this->form->getState();
        $startDate = $dates['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $dates['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $classId = $dates['school_class_id'] ?? null;
        $enrollmentId = $dates['enrollment_id'] ?? null;

        $query = Payment::with('receipt.items.feeCategory')
            ->whereBetween('payment_date', [
                Carbon::parse($startDate)->startOfDay(), 
                Carbon::parse($endDate)->endOfDay()
            ]);

        // --- NEW: ADVANCED QUERY FILTERING ---
        if ($enrollmentId) {
            // If a specific student is selected, only get their payments
            $query->whereHas('receipt', function ($q) use ($enrollmentId) {
                $q->where('enrollment_id', $enrollmentId);
            });
        } elseif ($classId) {
            // Otherwise, if just a class is selected, get that class's payments
            $query->whereHas('receipt.enrollment', function ($q) use ($classId) {
                $q->where('school_class_id', $classId);
            });
        }

        $payments = $query->get();

        $categoryTotals = [];
        $totalCollectedCash = 0;

        foreach ($payments as $payment) {
            $receipt = $payment->receipt;
            if (!$receipt || $receipt->total_amount <= 0) continue;

            $totalCollectedCash += (float) $payment->amount_paid;
            $paymentRatio = (float) $payment->amount_paid / (float) $receipt->total_amount;

            foreach ($receipt->items as $item) {
                $catId = $item->fee_category_id;
                
                if (!isset($categoryTotals[$catId])) {
                    $categoryTotals[$catId] = [
                        'name' => $item->feeCategory->name ?? 'Unknown',
                        'name_bn' => $item->feeCategory->name_bn ?? '',
                        'amount' => 0.00,
                    ];
                }
                
                $categoryTotals[$catId]['amount'] += ((float) $item->amount * $paymentRatio);
            }
        }

        usort($categoryTotals, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'rows' => $categoryTotals,
            'grand_total' => $totalCollectedCash,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function () {
                    $reportData = $this->reportData; 
                    $dates = $this->form->getState();
                    
                    $startDate = Carbon::parse($dates['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d'));
                    $endDate = Carbon::parse($dates['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d'));
                    
                    $classId = $dates['school_class_id'] ?? null;
                    $className = $classId ? SchoolClass::find($classId)?->name : 'All Classes (সকল ক্লাস)';
                    
                    // --- Get Student Name for PDF ---
                    $enrollmentId = $dates['enrollment_id'] ?? null;
                    $studentName = null;
                    if ($enrollmentId) {
                        $enrollment = Enrollment::with('user')->find($enrollmentId);
                        if ($enrollment) {
                            $studentName = "{$enrollment->user->name} (Roll: {$enrollment->roll_number})";
                        }
                    }
                    
                    $settings = SiteSetting::first();

                    $pdf = Pdf::loadView('pdf.fee-head-report', [
                        'report' => $reportData,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'className' => $className,
                        'studentName' => $studentName, // Passed to PDF
                        'settings' => $settings,
                    ]);

                    return response()->streamDownload(
                        fn () => print($pdf->output()), 
                        "Fee-Collection-Report.pdf"
                    );
                }),
        ];
    }
}