<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeeCategoryResource\Pages;
use App\Filament\Resources\FeeCategoryResource\RelationManagers;
use App\Models\FeeCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FeeCategoryResource extends Resource
{
    protected static ?string $model = FeeCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Category Name (English)')
                    ->required()
                    ->unique(ignoreRecord: true),
                    
                Forms\Components\TextInput::make('name_bn')
                    ->label('Category Name (Bengali)'),
                    
                Forms\Components\TextInput::make('default_amount')
                    ->label('Default Amount')
                    ->numeric()
                    ->prefix('৳')
                    ->default(0.00),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name_bn')
                    ->label('Bengali')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('default_amount')
                    ->money('BDT')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeeCategories::route('/'),
            'create' => Pages\CreateFeeCategory::route('/create'),
            'edit' => Pages\EditFeeCategory::route('/{record}/edit'),
        ];
    }
}
