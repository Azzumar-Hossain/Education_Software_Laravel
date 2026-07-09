<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudyGroupResource\Pages;
use App\Filament\Resources\StudyGroupResource\RelationManagers;
use App\Models\StudyGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudyGroupResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 5;

    protected static ?string $model = StudyGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Study Group Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Science, Arts, General'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\StudyGroupResource\RelationManagers\SubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudyGroups::route('/'),
            'create' => Pages\CreateStudyGroup::route('/create'),
            'edit' => Pages\EditStudyGroup::route('/{record}/edit'),
        ];
    }

}
