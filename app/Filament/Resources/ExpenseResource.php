<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use Carbon\Carbon;

class ExpenseResource extends Resource
{
    
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Income & Expenses'; // Renamed to be more accurate!
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Transaction Type')
                            ->options([
                                'Income' => 'Money Coming IN (Income)',
                                'Expense' => 'Money Going OUT (Expense)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('category', null)),

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(function (\Filament\Forms\Get $get) {
                                $type = $get('type');
                                if (!$type) return [];
                                
                                return \App\Models\ExpenseCategory::where('type', $type)
                                    ->get()
                                    ->mapWithKeys(function ($category) {
                                        $displayName = $category->name_bn ? "{$category->name} ({$category->name_bn})" : $category->name;
                                        return [$category->name => $displayName];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\Hidden::make('type')->default(fn (\Filament\Forms\Get $get) => $get('type') ?? 'Expense'),
                                Forms\Components\TextInput::make('name')->label('Name (English)')->required(),
                                Forms\Components\TextInput::make('name_bn')->label('Name (Bangla)')->nullable(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\ExpenseCategory::create($data)->name;
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (৳)')
                            ->numeric()
                            ->required()
                            ->prefix('৳'),

                        // --- FIXED: Brought back the missing description and attachment fields! ---
                        Forms\Components\Textarea::make('description')
                            ->label('Description / Notes')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('attachment')
                            ->label('Upload Bill / Voucher')
                            ->directory('expense-attachments')
                            ->image()
                            ->columnSpanFull(),
                            
                    ])->columns(2), // --- FIXED: Properly closed the Section schema ---
            ]); // --- FIXED: Properly closed the Form schema ---
    }
    

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                // --- NEW: Shows if it is Income or Expense in the table! ---
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Income' => 'success',
                        'Expense' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '৳ ' . number_format($state, 2))
                    ->weight('bold')
                    ->sortable()
                    ->summarize([
                        // 1. Total Income Only (Removed the word 'Builder')
                        Tables\Columns\Summarizers\Sum::make('income')
                            ->label('Total Income')
                            ->query(fn ($query) => $query->where('type', 'Income'))
                            ->formatStateUsing(fn ($state) => '৳ ' . number_format($state ?: 0, 2)),

                        // 2. Total Expense Only (Removed the word 'Builder')
                        Tables\Columns\Summarizers\Sum::make('expense')
                            ->label('Total Expense')
                            ->query(fn ($query) => $query->where('type', 'Expense'))
                            ->formatStateUsing(fn ($state) => '৳ ' . number_format($state ?: 0, 2)),

                        // 3. True Profit / Loss (Dynamic Labeling!)
                        Tables\Columns\Summarizers\Summarizer::make('net')
                            ->label('') // Hide the static label
                            ->using(fn ($query) => 
                                $query->clone()->where('type', 'Income')->sum('amount') - 
                                $query->clone()->where('type', 'Expense')->sum('amount')
                            )
                            ->formatStateUsing(function ($state) {
                                $amount = $state ?: 0;
                                
                                // If amount is 0 or positive, show Profit
                                if ($amount >= 0) {
                                    return 'Net Profit: ৳ ' . number_format($amount, 2);
                                } 
                                
                                // If amount is negative, show Loss (using abs() to remove the minus sign)
                                return 'Net Loss: ৳ ' . number_format(abs($amount), 2);
                            }),
                    ]),
            ])
            ->filters([
                // Filter by Type
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Expense' => 'Expense',
                    ]),

                // Filter by Category
                Tables\Filters\SelectFilter::make('category')
                    ->options(\App\Models\ExpenseCategory::pluck('name', 'name')),

                // Filter by Date Range
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('From: ' . Carbon::parse($data['from_date'])->format('d M, Y'))
                                ->removeField('from_date');
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('To: ' . Carbon::parse($data['to_date'])->format('d M, Y'))
                                ->removeField('to_date');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}