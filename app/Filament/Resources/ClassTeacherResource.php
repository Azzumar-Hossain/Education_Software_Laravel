<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Filament\Resources\ClassTeacherResource\Pages;
use App\Filament\Resources\ClassTeacherResource\RelationManagers;
use App\Models\ClassTeacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClassTeacherResource extends Resource
{
    protected static ?string $navigationGroup = 'Teacher';
    protected static ?int $navigationSort = 3;

    protected static ?string $model = ClassTeacher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('teacher_id')
                    ->label('Class Teacher')
                    ->options(User::where('type', 'teacher')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('academic_year_id')
                    ->relationship('academicYear', 'name')
                    ->label('Academic Year')
                    ->required(),

                Forms\Components\Select::make('school_class_id')
                    ->relationship('schoolClass', 'name')
                    ->label('Class')
                    ->required(),

                Forms\Components\Select::make('section_id')
                    ->relationship('section', 'name')
                    ->label('Section')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Class Teacher')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Year')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->sortable(),

                Tables\Columns\TextColumn::make('section.name')
                    ->label('Section')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListClassTeachers::route('/'),
            'create' => Pages\CreateClassTeacher::route('/create'),
            'edit' => Pages\EditClassTeacher::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Only Super Admins and Admins can see this menu item
        return $user->type === 'super_admin' || $user->type === 'admin';
    }
}
