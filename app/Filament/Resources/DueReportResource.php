<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DueReportResource\Pages;
use App\Models\Receipt;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DueReportResource extends Resource
{
    // We are looking at the existing Receipt model!
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?string $navigationLabel = 'Students Due Report';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $slug = 'finance/due-reports';
    protected static ?int $navigationSort = 2; // Places it right below Fee Collection

    // Disable the ability to "Create" a due report (it's auto-generated)
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            // --- THE REAL FIX: Use a subquery to sum the payments dynamically! ---
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->whereRaw('total_amount > (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payments.receipt_id = receipts.id)')
            )
            ->columns([
                Tables\Columns\TextColumn::make('enrollment.user.student_id')
                    ->label('Student ID')
                    // --- FIXED: Explicitly tell Filament how to search the deep relationship ---
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('enrollment.user', function ($q) use ($search) {
                            $q->where('student_id', 'like', "%{$search}%");
                        });
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('enrollment.user.name')
                    ->label('Student Name')
                    // --- FIXED: Explicitly tell Filament how to search the name too ---
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('enrollment.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('enrollment.schoolClass.name')
                    ->label('Class')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('enrollment.roll_number')
                    ->label('Roll No.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_for_month')
                    ->label('Due Month')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Fee')
                    ->formatStateUsing(fn ($state) => '৳ ' . number_format($state, 2))
                    ->color('gray'),

                // --- Calculate the due amount using the accessor ---
                Tables\Columns\TextColumn::make('due_amount')
                    ->label('Amount Due')
                    ->getStateUsing(fn (Receipt $record) => max(0, $record->total_amount - $record->paid_amount))
                    ->formatStateUsing(fn ($state) => '৳ ' . number_format($state, 2))
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(query: fn (Builder $query, string $direction) => 
                        $query->orderByRaw('(total_amount - (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payments.receipt_id = receipts.id)) ' . $direction)
                    ),
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
                    
                Tables\Filters\SelectFilter::make('paid_for_month')
                    ->label('Filter by Month')
                    ->options([
                        'January' => 'January', 'February' => 'February', 'March' => 'March', 
                        'April' => 'April', 'May' => 'May', 'June' => 'June', 
                        'July' => 'July', 'August' => 'August', 'September' => 'September', 
                        'October' => 'October', 'November' => 'November', 'December' => 'December'
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('collect_payment')
                    ->label('Collect Payment')
                    ->icon('heroicon-o-currency-bangladeshi')
                    ->color('success')
                    ->url(fn (Receipt $record): string => ReceiptResource::getUrl('edit', ['record' => $record->id])),
            ])
            ->defaultSort('created_at', 'desc'); 
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDueReports::route('/'),
        ];
    }
}