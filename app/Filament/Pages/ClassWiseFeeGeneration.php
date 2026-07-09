<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\Enrollment;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\FeeCategory;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ClassWiseFeeGeneration extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Class Wise Fees';
    protected static ?string $title = 'Manage Class Wise Fees';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.class-wise-fee-generation';

    public ?array $data = [];
    public ?string $activeTab = 'all';

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    // --- DISCOUNT DETECTOR ---
    public static function isDiscountCategory($categoryId): bool
    {
        if (!$categoryId) return false;
        $category = \App\Models\FeeCategory::find($categoryId);
        $name = strtolower($category?->name ?? '');
        $nameBn = strtolower($category?->name_bn ?? '');
        return str_contains($name, 'discount') || str_contains($name, 'scholarship') || str_contains($nameBn, 'ছাড়');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('Generate New Fees')->schema([
                    Components\Select::make('academic_year_id')
                        ->label('Academic Year')
                        ->options(AcademicYear::pluck('name', 'id'))
                        ->required(),
                        
                    Components\Select::make('school_class_id')
                        ->label('Class')
                        ->options(SchoolClass::pluck('name', 'id'))
                        ->required()
                        ->live(),
                        
                    Components\Select::make('month')
                        ->label('Month')
                        ->options([
                            'January' => 'January', 'February' => 'February', 'March' => 'March', 
                            'April' => 'April', 'May' => 'May', 'June' => 'June', 
                            'July' => 'July', 'August' => 'August', 'September' => 'September', 
                            'October' => 'October', 'November' => 'November', 'December' => 'December'
                        ])
                        ->required(),
                        
                    Components\Repeater::make('fee_items')
                        ->label('Fee Line Items')
                        ->addActionLabel('Add Fee Head')
                        ->schema([
                            Components\Select::make('fee_category_id')
                                ->label('Fee Category')
                                ->options(function () {
                                    return FeeCategory::all()->mapWithKeys(function ($category) {
                                        $bangla = $category->name_bn ? " ({$category->name_bn})" : '';
                                        return [$category->id => $category->name . $bangla];
                                    });
                                })
                                ->searchable()
                                ->required()
                                ->live(),

                            // --- NEW DISCOUNT OPTIONS FOR BULK GENERATION ---
                            Components\Select::make('discount_type')
                                ->label('Discount Type')
                                ->options(['flat' => 'Flat Deduction (-)', 'percentage' => 'Percentage (%)'])
                                ->default('flat')
                                ->live()
                                ->visible(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id'))),

                            Components\TextInput::make('discount_percentage')
                                ->label('Discount %')
                                ->numeric()
                                ->live(debounce: 500)
                                ->visible(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) && $get('discount_type') === 'percentage'),
                                
                            Components\TextInput::make('amount')
                                ->label(fn(Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) ? 'Deduction' : 'Amount (Tk)')
                                ->numeric()
                                ->required()
                                ->live()
                                ->readOnly(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) && $get('discount_type') === 'percentage'),
                        ])
                        ->columns(4) // Fits the new discount UI
                        ->required()
                        ->minItems(1)
                        ->columnSpanFull(),
                ])->columns(3),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Generate Fees For Class')
                ->color('success')
                ->submit('submit'),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $enrollments = Enrollment::where('academic_year_id', $data['academic_year_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->get();

        if ($enrollments->isEmpty()) {
            Notification::make()->title('No Students Found')->warning()->send();
            return;
        }

        // --- CALCULATE TRUE BULK MATH ---
        $baseTotal = 0;
        foreach ($data['fee_items'] as $item) {
            if (!self::isDiscountCategory($item['fee_category_id'])) {
                $baseTotal += (float) $item['amount'];
            }
        }

        $finalItems = [];
        $grandTotal = $baseTotal;

        foreach ($data['fee_items'] as $item) {
            if (self::isDiscountCategory($item['fee_category_id'])) {
                $type = $item['discount_type'] ?? 'flat';
                if ($type === 'percentage') {
                    $pct = (float) ($item['discount_percentage'] ?? 0);
                    $amt = -abs($baseTotal * ($pct / 100)); // Force negative deduction
                } else {
                    $amt = -abs((float) $item['amount']); // Force negative deduction
                }
                $finalItems[] = ['fee_category_id' => $item['fee_category_id'], 'amount' => $amt];
                $grandTotal += $amt;
            } else {
                $finalItems[] = ['fee_category_id' => $item['fee_category_id'], 'amount' => (float) $item['amount']];
            }
        }
        $grandTotal = max(0, $grandTotal); // Never let total go below 0
        $generatedCount = 0;

        foreach ($enrollments as $enrollment) {
            $receipt = Receipt::create([
                'receipt_number' => 'REC-' . strtoupper(Str::random(6)),
                'receipt_date'   => now(),
                'enrollment_id'  => $enrollment->id,
                'paid_for_month' => $data['month'],
                'paid_for_year'  => $data['academic_year_id'],
                'total_amount'   => $grandTotal,
                'paid_amount'    => 0,
                'due_amount'     => $grandTotal,
                'collected_by'   => auth()->id(),
            ]);

            foreach ($finalItems as $item) {
                ReceiptItem::create([
                    'receipt_id'      => $receipt->id,
                    'fee_category_id' => $item['fee_category_id'],
                    'amount'          => $item['amount'],
                ]);
            }
            $generatedCount++;
        }

        Notification::make()->title('Success!')->body("Successfully generated fees for {$generatedCount} students.")->success()->send();
        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Receipt::query()
                    ->select(
                        'receipts.paid_for_month', 'receipts.paid_for_year', 'enrollments.school_class_id',
                        'school_classes.name as class_name', 'academic_years.name as year_name',
                        DB::raw('MAX(receipts.id) as id'), DB::raw('COUNT(receipts.id) as total_students'), DB::raw('SUM(receipts.total_amount) as total_amount')
                    )
                    ->join('enrollments', 'receipts.enrollment_id', '=', 'enrollments.id')
                    ->join('school_classes', 'enrollments.school_class_id', '=', 'school_classes.id')
                    ->join('academic_years', 'receipts.paid_for_year', '=', 'academic_years.id')
                    ->when($this->activeTab && $this->activeTab !== 'all', function ($query) {
                        return $query->where('enrollments.school_class_id', str_replace('class_', '', $this->activeTab));
                    })
                    ->groupBy('receipts.paid_for_month', 'receipts.paid_for_year', 'enrollments.school_class_id', 'school_classes.name', 'academic_years.name')
                    ->orderBy('receipts.id', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('class_name')->label('Class')->badge()->color('info'),
                Tables\Columns\TextColumn::make('paid_for_month')->label('Month'),
                Tables\Columns\TextColumn::make('year_name')->label('Year'),
                Tables\Columns\TextColumn::make('total_students')->label('Students Generated'),
                Tables\Columns\TextColumn::make('total_amount')->label('Total Expected')->money('BDT')->color('success')->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_fees')
                    ->label('View & Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->mountUsing(function (Form $form, Model $record) {
                        $sampleReceipt = Receipt::where('paid_for_month', $record->paid_for_month)
                            ->where('paid_for_year', $record->paid_for_year)
                            ->whereHas('enrollment', fn($q) => $q->where('school_class_id', $record->school_class_id))->first();

                        if ($sampleReceipt) {
                            $form->fill(['fee_items' => $sampleReceipt->items->map(fn($item) => ['fee_category_id' => $item->fee_category_id, 'amount' => abs($item->amount)])->toArray()]);
                        }
                    })
                    ->form([
                        Components\Repeater::make('fee_items')
                            ->schema([
                                Components\Select::make('fee_category_id')->options(FeeCategory::pluck('name', 'id'))->required(),
                                Components\TextInput::make('amount')->numeric()->required(),
                            ])->columns(2)->required(),
                    ])
                    ->action(function (array $data, Model $record) {
                        $receipts = Receipt::where('paid_for_month', $record->paid_for_month)
                            ->where('paid_for_year', $record->paid_for_year)
                            ->whereHas('enrollment', fn($q) => $q->where('school_class_id', $record->school_class_id))->get();

                        // Basic rebuild for simple edits. Advanced discounts should be done via Receipt Resource.
                        $newTotal = collect($data['fee_items'])->sum('amount');
                        foreach ($receipts as $receipt) {
                            $receipt->items()->delete(); 
                            foreach ($data['fee_items'] as $item) { 
                                $receipt->items()->create(['fee_category_id' => $item['fee_category_id'], 'amount' => $item['amount']]);
                            }
                            $receipt->update(['total_amount' => $newTotal, 'due_amount' => max(0, $newTotal - $receipt->paid_amount)]);
                        }
                        Notification::make()->title('Fees Updated!')->success()->send();
                    }),

                Tables\Actions\Action::make('copy_to_new_month')
                    ->label('Copy to Month')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->form([
                        Components\Select::make('new_month')->options(['January'=>'January', 'February'=>'February', 'March'=>'March', 'April'=>'April', 'May'=>'May', 'June'=>'June', 'July'=>'July', 'August'=>'August', 'September'=>'September', 'October'=>'October', 'November'=>'November', 'December'=>'December'])->required(),
                    ])
                    ->action(function (array $data, Model $record) {
                        $enrollments = Enrollment::where('academic_year_id', $record->paid_for_year)->where('school_class_id', $record->school_class_id)->get();
                        if ($enrollments->isEmpty()) return;

                        $sampleReceipt = Receipt::where('paid_for_month', $record->paid_for_month)->where('paid_for_year', $record->paid_for_year)->whereHas('enrollment', fn($q) => $q->where('school_class_id', $record->school_class_id))->first();
                        if (!$sampleReceipt) return;

                        $feeItems = $sampleReceipt->items;
                        $grandTotal = $sampleReceipt->total_amount;
                        $generatedCount = 0;

                        foreach ($enrollments as $enrollment) {
                            if (Receipt::where('enrollment_id', $enrollment->id)->where('paid_for_month', $data['new_month'])->where('paid_for_year', $record->paid_for_year)->exists()) continue;

                            $newReceipt = Receipt::create([
                                'receipt_number' => 'REC-' . strtoupper(Str::random(6)), 'receipt_date' => now(),
                                'enrollment_id'  => $enrollment->id, 'paid_for_month' => $data['new_month'], 'paid_for_year'  => $record->paid_for_year,
                                'total_amount'   => $grandTotal, 'paid_amount' => 0, 'due_amount' => $grandTotal, 'collected_by'   => auth()->id(),
                            ]);

                            foreach ($feeItems as $item) {
                                ReceiptItem::create(['receipt_id' => $newReceipt->id, 'fee_category_id' => $item->fee_category_id, 'amount' => $item->amount]); // Copies the exact negative amount!
                            }
                            $generatedCount++;
                        }
                        Notification::make()->title('Success!')->body("Generated {$data['new_month']} fees for {$generatedCount} students.")->success()->send();
                    }),

                Tables\Actions\Action::make('add_due')
                    ->label('Add Previous Dues')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->form([
                        Components\Select::make('arrears_category_id')
                            ->label('Select "Arrears" Category')
                            ->options(FeeCategory::pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, Model $record) {
                        $receipts = Receipt::with('items.feeCategory')
                            ->where('paid_for_month', $record->paid_for_month)
                            ->where('paid_for_year', $record->paid_for_year)
                            ->whereHas('enrollment', fn($q) => $q->where('school_class_id', $record->school_class_id))->get();

                        $updatedCount = 0;

                        foreach ($receipts as $receipt) {
                            $previousReceipts = Receipt::with('items.feeCategory')
                                ->where('enrollment_id', $receipt->enrollment_id)
                                ->where('id', '<', $receipt->id) 
                                ->get();

                            $totalBaseCharges = 0;
                            $totalPaid = 0;
                            $dueMonths = [];

                            foreach ($previousReceipts as $pr) {
                                $actualPaid = \App\Models\Payment::where('receipt_id', $pr->id)->sum('amount_paid');
                                $totalPaid += $actualPaid;
                                
                                $receiptBaseCharge = 0;
                                foreach ($pr->items as $item) {
                                    $name = strtolower($item->feeCategory->name ?? '');
                                    if (!str_contains($name, 'arrears') && !str_contains($name, 'previous dues')) {
                                        $receiptBaseCharge += (float) $item->amount; // Safe, discounts are negative so they reduce base cost!
                                    }
                                }
                                $totalBaseCharges += $receiptBaseCharge;

                                if ($receiptBaseCharge > $actualPaid) {
                                    $dueMonths[] = $pr->paid_for_month;
                                }
                            }

                            $trueHistoricalDue = max(0, $totalBaseCharges - $totalPaid);

                            if ($trueHistoricalDue > 0) {
                                $arrearsItem = $receipt->items()->where('fee_category_id', $data['arrears_category_id'])->first();
                                
                                if (!$arrearsItem) {
                                    $receipt->items()->create([
                                        'fee_category_id' => $data['arrears_category_id'],
                                        'amount' => $trueHistoricalDue,
                                        'related_month' => array_values(array_unique($dueMonths)), 
                                    ]);

                                    $newTotal = $receipt->total_amount + $trueHistoricalDue;
                                } else {
                                    $oldArrearsAmount = $arrearsItem->amount;
                                    $arrearsItem->update([
                                        'amount' => $trueHistoricalDue,
                                        'related_month' => array_values(array_unique($dueMonths)),
                                    ]);
                                    
                                    $newTotal = ($receipt->total_amount - $oldArrearsAmount) + $trueHistoricalDue;
                                }

                                $currentPaid = \App\Models\Payment::where('receipt_id', $receipt->id)->sum('amount_paid');

                                $receipt->update([
                                    'total_amount' => $newTotal,
                                    'paid_amount' => $currentPaid,
                                    'due_amount' => max(0, $newTotal - $currentPaid)
                                ]);
                                $updatedCount++;
                            }
                        }
                        Notification::make()->title('Arrears Synced!')->body("Perfectly calculated arrears for {$updatedCount} students based on actual payment history.")->success()->send();
                    }),
            ]);
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All Classes')];
        foreach (SchoolClass::all() as $sc) {
            $tabs['class_' . $sc->id] = Tab::make($sc->name)->modifyQueryUsing(fn (Builder $q) => $q->where('enrollments.school_class_id', $sc->id));
        }
        return $tabs;
    }
}