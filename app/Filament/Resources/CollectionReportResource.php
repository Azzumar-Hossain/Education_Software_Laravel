<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectionReportResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use Carbon\Carbon;

class CollectionReportResource extends Resource
{
    // Point this directly to your Payment model!
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Collection Report';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $slug = 'finance/collection-reports';
    protected static ?int $navigationSort = 3; // Places it right below the Due Report

    // Make it Read-Only
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receipt.receipt_number')
                    ->label('Receipt No.')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                // Smart Searchable Student ID
                Tables\Columns\TextColumn::make('receipt.enrollment.user.student_id')
                    ->label('Student ID')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('receipt.enrollment.user', function ($q) use ($search) {
                            $q->where('student_id', 'like', "%{$search}%");
                        });
                    }),

                // Smart Searchable Student Name
                Tables\Columns\TextColumn::make('receipt.enrollment.user.name')
                    ->label('Student Name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('receipt.enrollment.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'cash' => 'success',
                        'bkash', 'nagad', 'online' => 'info',
                        'bank' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Trx ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // --- THIS IS THE MAGIC: The Summarizer instantly calculates the totals based on filters! ---
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Collected')
                    ->formatStateUsing(fn ($state) => '৳ ' . number_format($state, 2))
                    ->weight('bold')
                    ->color('success')
                    ->sortable()
                    ->summarize([
                        Sum::make()
                            ->label('Total Collected')
                            ->formatStateUsing(fn ($state) => '৳ ' . number_format($state, 2)),
                    ]),
            ])
            ->filters([
                // --- CUSTOM DATE RANGE FILTER FOR DAILY/MONTHLY REPORTS ---
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
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

                // Filter by Cash, Bkash, etc.
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'Bkash' => 'Bkash',
                        'Nagad' => 'Nagad',
                        'Bank Transfer' => 'Bank Transfer',
                    ]),
            ])
            ->defaultSort('payment_date', 'desc'); 
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectionReports::route('/'),
        ];
    }
}