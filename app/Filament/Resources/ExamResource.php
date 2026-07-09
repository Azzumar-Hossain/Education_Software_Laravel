<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExamResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 6;

    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_year_id')
                    ->relationship('academicYear', 'name')
                    ->label('Academic Year')
                    ->required(),
                    
                // --- NEW CLASS DROPDOWN ---
                Forms\Components\Select::make('school_class_id')
                    ->relationship('schoolClass', 'name')
                    ->label('Class')
                    ->required(),
                    
                Forms\Components\TextInput::make('name')
                    ->label('Exam Name (e.g., Mid-Term Examination)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('parent_exam_id')
                    ->label('Add Marks To (Parent Exam)')
                    ->options(function (?\App\Models\Exam $record) {
                        // Load all exams with their related Class and Year
                        $query = \App\Models\Exam::with(['schoolClass', 'academicYear']);
                        
                        // Prevent an exam from being linked to itself!
                        if ($record) {
                            $query->where('id', '!=', $record->id);
                        }
                        
                        // Glue the data together for the dropdown label
                        return $query->get()->mapWithKeys(function ($exam) {
                            $className = $exam->schoolClass->name ?? 'No Class';
                            $yearName = $exam->academicYear->name ?? 'No Year';
                            
                            return [$exam->id => "{$exam->name} ({$className} - {$yearName})"];
                        });
                    })
                    ->searchable()
                    ->helperText('If this is a sub-exam (like 1st Mid), select the main exam (like Mid Term) it belongs to. Leave blank for main exams.')
                    ->nullable(),

                Forms\Components\TextInput::make('contribution_percentage')
                    ->label('Contribution Percentage (%)')
                    ->numeric()
                    ->helperText('Example: Type 20 if this exam contributes 20% to the parent exam.')
                    ->nullable(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Exam Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Academic Year')
                    ->sortable(),
                    
                // --- NEW CLASS COLUMN ---
                Tables\Columns\TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
            ])
            
            // --- EDIT AND DELETE BUTTONS ADDED HERE ---
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
            // ------------------------------------------
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
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}