<?php

namespace App\Filament\Resources\StudyGroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Subject Name'),
                Tables\Columns\TextColumn::make('code')->label('Code'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    // 1. Tell the Attach modal to format option records using our custom logic
                    ->recordTitle(fn ($record) => "{$record->name} (" . ($record->code ?? 'N/A') . ")")
                    // 2. Ensure both columns are explicitly selected from the query scope
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->select('subjects.id', 'subjects.name', 'subjects.code'))
                    // 3. Enable searching across both fields natively
                    ->recordSelectSearchColumns(['subjects.name', 'subjects.code']),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}