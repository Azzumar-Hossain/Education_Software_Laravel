<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Models\AcademicYear; // 🌟 1. ADDED MISSING IMPORT
use App\Models\ClassTeacher;
use App\Models\Section;
use App\Models\SchoolClass;
use App\Filament\Resources\ClassTeacherResource\Pages;
use App\Filament\Resources\ClassTeacherResource\RelationManagers;
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
                // 🌟 1. ACADEMIC YEAR (REQUIRED) 🌟
                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->default(fn () => AcademicYear::latest()->first()?->id) // Pre-selects latest academic year
                    ->required()
                    ->live(),

                // 🌟 2. CLASS TEACHER 🌟
                Forms\Components\Select::make('teacher_id')
                    ->label('Class Teacher')
                    ->options(function () {
                        return User::query()
                            ->whereIn('type', ['teacher', 'class_teacher', 'subject_teacher'])
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                // 🌟 3. CLASS 🌟
                Forms\Components\Select::make('school_class_id')
                    ->label('Class')
                    ->options(SchoolClass::pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('section_id', null)), // Resets section when class changes

                // 🌟 4. DYNAMIC SECTION (FILTERED BY CLASS) 🌟
                Forms\Components\Select::make('section_id')
                    ->label('Section')
                    ->placeholder(fn (Forms\Get $get) => !$get('school_class_id') ? 'Select Class First' : 'Select Section')
                    ->options(function (Forms\Get $get) {
                        $classId = $get('school_class_id');

                        if (!$classId) {
                            return [];
                        }

                        // Fetches only sections belonging to the chosen class
                        return Section::whereHas('schoolClasses', function ($q) use ($classId) {
                            $q->where('school_classes.id', $classId);
                        })->pluck('name', 'id');
                    })
                    ->searchable()
                    ->disabled(fn (Forms\Get $get) => !$get('school_class_id'))
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

    // 🌟 2. UPDATED DYNAMIC ACCESS CONTROL 🌟
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Admins pass automatically, others rely on Shield permission
        return in_array($user->type, ['super_admin', 'admin']) 
            || $user->hasRole(['super_admin', 'admin'])
            || $user->can('view_any_class::teacher');
    }
}