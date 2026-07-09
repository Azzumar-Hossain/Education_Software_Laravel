<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5; // Puts it right below Expenses

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Category Type')
                    ->options([
                        'Income' => 'Income (Grants, Donations, etc.)',
                        'Expense' => 'Expense (Salaries, Utilities, etc.)',
                    ])
                    ->required()
                    ->default('Expense'),

                Forms\Components\TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                    // --- ADD THE BANGLA FIELD HERE TOO ---
                Forms\Components\TextInput::make('name_bn')
                    ->label('Name (Bangla)')
                    ->nullable()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                ->badge(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name_bn')
                    ->label('Name (Bangla)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
        ];
    }
}