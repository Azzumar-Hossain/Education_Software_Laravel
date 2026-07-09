<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptResource\Pages;
use App\Models\Receipt;
use App\Models\FeeCategory;
use App\Models\Enrollment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use App\Models\SchoolClass;
use Filament\Forms\Get;
use Filament\Forms\Components;
use App\Models\Payment;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Fee Collection';
    protected static ?string $navigationGroup = 'Finance';

    // --- SMART DISCOUNT DETECTOR ---
    public static function isDiscountCategory($categoryId): bool
    {
        if (!$categoryId) return false;
        $category = \App\Models\FeeCategory::find($categoryId);
        $name = strtolower($category?->name ?? '');
        $nameBn = strtolower($category?->name_bn ?? '');
        return str_contains($name, 'discount') || str_contains($name, 'scholarship') || str_contains($nameBn, 'ছাড়');
    }

    // --- THE NEW GLOBAL MATH ENGINE ---
    public static function triggerLiveMath(Forms\Get $get, Forms\Set $set, string $context = 'item'): void
    {
        // 1. Get all items. If inside a repeater item, we use '../../items' to look at the parent form!
        $items = $context === 'item' ? ($get('../../items') ?? []) : ($get('items') ?? []);
        
        $baseTotal = 0;
        
        // 2. Sum up all the normal fees first
        foreach ($items as $item) {
            if (!self::isDiscountCategory($item['fee_category_id'] ?? null)) {
                $baseTotal += (float) ($item['amount'] ?? 0);
            }
        }

        $discountTotal = 0;

        // 3. Process the discounts and UPDATE their visible amounts
        foreach ($items as $k => $item) {
            if (self::isDiscountCategory($item['fee_category_id'] ?? null)) {
                if (($item['discount_type'] ?? 'flat') === 'percentage') {
                    // Calculate the percentage based on the normal fees
                    $pct = (float) ($item['discount_percentage'] ?? 0);
                    $calculatedDeduction = round($baseTotal * ($pct / 100), 2);
                    
                    // Inject the calculated amount back into the UI box!
                    if ($context === 'item') {
                        $set("../../items.{$k}.amount", $calculatedDeduction);
                    } else {
                        $set("items.{$k}.amount", $calculatedDeduction);
                    }
                    $discountTotal += $calculatedDeduction;
                } else {
                    // Flat discount
                    $discountTotal += abs((float) ($item['amount'] ?? 0));
                }
            }
        }

        // 4. Update the Grand Total
        $grandTotal = max(0, $baseTotal - $discountTotal);
        if ($context === 'item') {
            $set('../../total_amount', $grandTotal);
        } else {
            $set('total_amount', $grandTotal);
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receipt Details')
                    ->schema([
                        Forms\Components\TextInput::make('receipt_number')->default(fn () => 'REC-' . strtoupper(substr(uniqid(), -6)))->readOnly()->required(),
                        Forms\Components\DatePicker::make('receipt_date')->default(now())->required(),
                        Forms\Components\Select::make('school_class_id')
                            ->label('Class Filter')
                            ->options(\App\Models\SchoolClass::pluck('name', 'id'))
                            ->live()->dehydrated(false) 
                            ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                if ($record && $record->enrollment_id) {
                                    $enrollment = \App\Models\Enrollment::find($record->enrollment_id);
                                    if ($enrollment) $component->state($enrollment->school_class_id);
                                }
                            })
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('enrollment_id', null)), 

                        Forms\Components\Select::make('enrollment_id')
                            ->label('Student')
                            ->options(function (Forms\Get $get) {
                                return \App\Models\Enrollment::with('user')
                                    ->when($get('school_class_id'), fn ($query, $classId) => $query->where('school_class_id', $classId))
                                    ->get()
                                    ->mapWithKeys(fn ($record) => [
                                        $record->id => "{$record->user->name} | Roll: {$record->roll_number}"
                                    ]);
                            })
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('paid_for_month')
                            ->label('Month')
                            ->options(['January' => 'January', 'February' => 'February', 'March' => 'March', 'April' => 'April', 'May' => 'May', 'June' => 'June', 'July' => 'July', 'August' => 'August', 'September' => 'September', 'October' => 'October', 'November' => 'November', 'December' => 'December']),

                        Forms\Components\Select::make('paid_for_year')->label('Year')->options(['2025' => '2025', '2026' => '2026', '2027' => '2027'])->default(date('Y')),
                    ])->columns(2),

                Forms\Components\Section::make('Fee Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('fee_category_id')
                                    ->label('Fee Category')
                                    ->relationship('feeCategory', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (FeeCategory $record) => "{$record->name} (" . ($record->name_bn ?? 'N/A') . ") — ৳{$record->default_amount}")
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()->searchable(['name', 'name_bn'])->preload()->required()->live()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required()->unique('fee_categories', 'name'),
                                        Forms\Components\TextInput::make('name_bn'),
                                        Forms\Components\TextInput::make('default_amount')->numeric()->prefix('৳')->default(0.00),
                                        Forms\Components\Hidden::make('is_active')->default(true),
                                    ])
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state) {
                                            $category = FeeCategory::find($state);
                                            if ($category) {
                                                $set('amount', abs($category->default_amount));
                                                $set('discount_type', 'flat'); 
                                                self::triggerLiveMath($get, $set, 'item');
                                            }
                                        }
                                    }),

                                Forms\Components\Select::make('related_month')
                                    ->label('Due Month')
                                    ->multiple()
                                    ->options(['January' => 'January', 'February' => 'February', 'March' => 'March', 'April' => 'April', 'May' => 'May', 'June' => 'June', 'July' => 'July', 'August' => 'August', 'September' => 'September', 'October' => 'October', 'November' => 'November', 'December' => 'December'])
                                    ->hidden(function (Get $get) {
                                        $categoryId = $get('fee_category_id');
                                        if (!$categoryId) return true; 
                                        $category = \App\Models\FeeCategory::find($categoryId);
                                        $name = strtolower($category?->name ?? '');
                                        return !str_contains($name, 'arrears') && !str_contains($name, 'previous dues');
                                    }),

                                Forms\Components\Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->options([
                                        'flat' => 'Flat Deduction (-)',
                                        'percentage' => 'Percentage (%)'
                                    ])
                                    ->default('flat')
                                    ->live()
                                    ->visible(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')))
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::triggerLiveMath($get, $set, 'item')),

                                Forms\Components\TextInput::make('discount_percentage')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->live(debounce: 500)
                                    ->visible(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) && $get('discount_type') === 'percentage')
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::triggerLiveMath($get, $set, 'item')),

                                Forms\Components\TextInput::make('amount')
                                    ->label(fn(Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) ? 'Deduction' : 'Amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('৳')
                                    ->live(debounce: 500)
                                    ->readOnly(fn (Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) && $get('discount_type') === 'percentage')
                                    ->formatStateUsing(fn ($state) => $state !== null ? abs((float) $state) : null)
                                    // Forces standard save to be negative for discounts
                                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => self::isDiscountCategory($get('fee_category_id')) ? -abs((float) $state) : abs((float) $state))
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::triggerLiveMath($get, $set, 'item')),
                            ])
                            ->columns(4) 
                            ->addActionLabel('Add Another Fee')->defaultItems(1)->live()
                            // Triggers global math when a row is deleted!
                            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->after(fn (Forms\Get $get, Forms\Set $set) => self::triggerLiveMath($get, $set, 'repeater'))),
                            
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Grand Total')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->formatStateUsing(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $baseTotal = 0;
                                $discountTotal = 0;
                                foreach ($items as $item) {
                                    if (self::isDiscountCategory($item['fee_category_id'] ?? null)) {
                                        $discountTotal += abs((float) ($item['amount'] ?? 0));
                                    } else {
                                        $baseTotal += (float) ($item['amount'] ?? 0);
                                    }
                                }
                                return max(0, $baseTotal - $discountTotal);
                            })
                            ->prefix('৳')
                            ->extraInputAttributes(['style' => 'font-weight: bold; font-size: 1.2rem; color: #16a34a;']),
                    ]),

                Forms\Components\Hidden::make('collected_by')->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('receipt_date')->date()->sortable(),
                
                // --- NEW: STUDENT ID COLUMN ---
                Tables\Columns\TextColumn::make('enrollment.user.student_id')
                    ->label('Student ID')
                    ->searchable() 
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('enrollment.user.name')->label('Student')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('enrollment.roll_number')->label('Roll')->badge()->color('gray')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('paid_for_month')->label('Month')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->label('Status')->badge()
                    ->getStateUsing(function (Receipt $record) {
                        if ($record->due_amount <= 0) return 'Paid';
                        if ($record->paid_amount > 0) return 'Partially Paid';
                        return 'Unpaid';
                    })
                    ->color(fn (string $state): string => match ($state) { 'Paid' => 'success', 'Partially Paid' => 'warning', 'Unpaid' => 'danger' }),
                Tables\Columns\TextColumn::make('collector.name')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('school_class')
                    ->label('Filter by Class')
                    ->options(\App\Models\SchoolClass::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('enrollment', fn (Builder $q) => $q->where('school_class_id', $data['value']));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('add_payment')
                    ->label('Collect Payment')
                    ->icon('heroicon-o-currency-bangladeshi')
                    ->color('success')
                    ->hidden(fn (Receipt $record) => $record->due_amount <= 0)
                    ->form(function (Receipt $record) {
                        $dueAmount = $record->due_amount;
                        $paid = $record->paid_amount;

                        $arrearsItems = $record->items->filter(function($item) {
                            $name = strtolower($item->feeCategory->name ?? '');
                            return !empty($item->related_month) || str_contains($name, 'arrears') || str_contains($name, 'previous dues');
                        });
                        
                        $arrearsTotal = $arrearsItems->sum('amount');
                        
                        $monthsArray = [];
                        foreach($arrearsItems as $item) {
                            if (!empty($item->related_month)) {
                                $m = $item->related_month;
                                if (is_string($m)) {
                                    $decoded = json_decode($m, true);
                                    $m = is_array($decoded) ? $decoded : [$m];
                                } elseif (!is_array($m)) {
                                    $m = [$m];
                                }
                                foreach($m as $monthStr) {
                                    if(strtolower($monthStr) !== 'previous dues') $monthsArray[] = $monthStr;
                                }
                            }
                        }
                        $monthsText = !empty($monthsArray) ? ' (' . implode(', ', array_unique($monthsArray)) . ')' : '';

                        $arrearsDue = max(0, $arrearsTotal - $paid);
                        $paidLeft = max(0, $paid - $arrearsTotal);
                        $regularDue = max(0, ($record->total_amount - $arrearsTotal) - $paidLeft);

                        return [
                            \Filament\Forms\Components\Grid::make(2)->schema([
                                \Filament\Forms\Components\Placeholder::make('current_month_due')
                                    ->label('Current Month Due')
                                    ->content('৳ ' . number_format($regularDue, 2))->extraAttributes(['class' => 'text-lg font-bold text-gray-700']),
                                \Filament\Forms\Components\Placeholder::make('arrears_due')
                                    ->label('Previous Dues' . $monthsText)
                                    ->content('৳ ' . number_format($arrearsDue, 2))->visible($arrearsTotal > 0)->extraAttributes(['class' => 'text-lg font-bold text-danger-600']),
                            ]),
                            \Filament\Forms\Components\Placeholder::make('due_info')->label('Total Amount Currently Due')->content('৳ ' . number_format($dueAmount, 2))->extraAttributes(['class' => 'text-xl font-bold text-danger-600']),
                            \Filament\Forms\Components\TextInput::make('amount_paid')->label('Payment Amount (Tk)')->numeric()->required()->default($dueAmount)->maxValue($dueAmount),
                            \Filament\Forms\Components\Select::make('payment_method')->options(['Cash' => 'Cash', 'Online' => 'Online'])->default('Cash')->live()->required(),
                            \Filament\Forms\Components\TextInput::make('transaction_id')->label('Transaction ID')->visible(fn (\Filament\Forms\Get $get) => $get('payment_method') === 'Online')->required(fn (\Filament\Forms\Get $get) => $get('payment_method') === 'Online'),
                        ];
                    })
                    ->action(function (array $data, Receipt $record) {
                        \App\Models\Payment::create([
                            'receipt_id'     => $record->id,
                            'amount_paid'    => $data['amount_paid'],
                            'payment_method' => $data['payment_method'],
                            'transaction_id' => $data['transaction_id'] ?? null,
                            'payment_date'   => now(),
                            'collected_by'   => auth()->id(),
                        ]);

                        $newPaidAmount = $record->paid_amount + $data['amount_paid'];
                        $newDueAmount = max(0, $record->total_amount - $newPaidAmount);

                        $record->update([
                            'paid_amount' => $newPaidAmount,
                            'due_amount' => $newDueAmount,
                        ]);

                        \Filament\Notifications\Notification::make()->title('Payment Collected Successfully!')->success()->send();
                    }),

                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (Receipt $record) {
                        $pdf = Pdf::loadView('pdf.receipt', ['receipt' => $record]);
                        return response()->streamDownload(fn () => print($pdf->output()), "receipt-{$record->receipt_number}.pdf");
                    }),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([ Tables\Actions\BulkActionGroup::make([ Tables\Actions\DeleteBulkAction::make() ]) ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array { return [ "index" => Pages\ListReceipts::route('/'), "create" => Pages\CreateReceipt::route('/create'), "edit" => Pages\EditReceipt::route('/{record}/edit') ]; }
}