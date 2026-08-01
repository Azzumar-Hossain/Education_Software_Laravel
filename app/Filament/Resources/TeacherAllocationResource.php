<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Filament\Resources\TeacherAllocationResource\Pages;
use App\Filament\Resources\TeacherAllocationResource\RelationManagers;
use App\Models\TeacherAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeacherAllocationResource extends Resource
{
    protected static ?string $navigationGroup = 'Teacher';
    protected static ?int $navigationSort = 2;
    protected static ?string $model = TeacherAllocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Teacher')
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
                    ->required()
                    ->live() // Listens for class changes immediately
                    ->afterStateUpdated(function (Forms\Set $set) {
                        $set('section_id', null);
                        $set('subject_id', null);
                    }),

                Forms\Components\Select::make('section_id')
                    ->label('Section')
                    ->options(function (Forms\Get $get) {
                        $classId = $get('school_class_id');
                        
                        if (!$classId) {
                            return [];
                        }

                        // Fetch the class with its attached sections via relationship
                        $schoolClass = SchoolClass::with('sections')->find($classId);

                        return $schoolClass ? $schoolClass->sections->pluck('name', 'id') : [];
                    })
                    ->live()
                    ->nullable(),

                Forms\Components\Select::make('subject_id')
                    ->label('Subject')
                    ->options(function (Forms\Get $get) {
                        $classId = $get('school_class_id');
                        
                        if (!$classId) {
                            return [];
                        }

                        // Fetch subjects connected to the selected class with code appended
                        return Subject::whereHas('schoolClasses', function ($query) use ($classId) {
                            $query->where('school_classes.id', $classId);
                        })
                        ->get()
                        ->mapWithKeys(function ($subject) {
                            $codeLabel = $subject->code ? " ({$subject->code})" : '';
                            return [$subject->id => "{$subject->name}{$codeLabel}"];
                        });
                    })
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Teacher Name')
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

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Subject')
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
            'index' => Pages\ListTeacherAllocations::route('/'),
            'create' => Pages\CreateTeacherAllocation::route('/create'),
            'edit' => Pages\EditTeacherAllocation::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Only Super Admins and Admins can see this menu item
        return $user->type === 'super_admin' || $user->type === 'admin';
    }
}