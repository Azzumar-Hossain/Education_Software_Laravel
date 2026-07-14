<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Subject Name (e.g., Bangla)')
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->label('Subject Code (e.g., 101)'),

                // 🌟 NEW MULTI-SELECT DROPDOWN TO SHARE ACROSS CLASSES 🌟
                Forms\Components\Select::make('schoolClasses')
                    ->relationship('schoolClasses', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Also assign to these classes (Optional)')
                    ->helperText('This subject will automatically be added to the current class. Select others (like Class 10) to share it.')
                    ->columnSpanFull(),

                // --- UPDATED COMBINE DROPDOWN WITH CODES ---
                Forms\Components\Select::make('linked_subject_id')
                    ->label('Combine With (Partner Subject)')
                    ->options(function (?\App\Models\Subject $record) {
                        $query = \App\Models\Subject::query();
                        
                        // If we are editing an existing subject, hide it from its own dropdown!
                        if ($record) {
                            $query->where('id', '!=', $record->id);
                        }
                        
                        // Glue the name and code together: "Subject Name (Code)"
                        return $query->get()->mapWithKeys(function ($subject) {
                            return [$subject->id => "{$subject->name} ({$subject->code})"];
                        });
                    })
                    ->searchable()
                    ->placeholder('Select partner (e.g., English 2nd Paper)')
                    ->helperText('Merging? Select the 2nd paper here. They will act as one for grade calculations.')
                    ->columnSpanFull(),
                    
                // --- FIXED STUDY GROUP FIELD: CHANGED FROM required() TO nullable() ---
                Forms\Components\Select::make('study_group_id')
                    ->label('Study Group')
                    ->relationship('studyGroup', 'name')
                    ->nullable() // <--- Allow blank for multi-group / global subjects
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return \App\Models\StudyGroup::create($data)->id;
                    }),
                    
                Forms\Components\Select::make('subject_type')
                    ->label('Subject Type')
                    ->options([
                        'Core' => 'Core / Compulsory',
                        'Group' => 'Group / Main Subject',
                        'Optional' => '4th / Optional Subject',
                    ])
                    ->default('Core')
                    ->required(),

                Forms\Components\Fieldset::make('Exam Mark Distribution (Default)')
                    ->schema([
                        Forms\Components\TextInput::make('written_total')->label('Written Total')->numeric()->default(100),
                        Forms\Components\TextInput::make('written_pass_mark')->label('Written Pass')->numeric()->default(33),
                        
                        Forms\Components\TextInput::make('mcq_total')->label('MCQ Total (0 if none)')->numeric()->default(0),
                        Forms\Components\TextInput::make('mcq_pass_mark')->label('MCQ Pass (0 if none)')->numeric()->default(0),
                        
                        Forms\Components\TextInput::make('practical_total')->label('Practical Total (0 if none)')->numeric()->default(0),
                        Forms\Components\TextInput::make('practical_pass_mark')->label('Practical Pass (0 if none)')->numeric()->default(0),
                    ])->columns(2),

                // --- NEW OVERRIDE REPEATER (FOR EXAMS LIKE 1ST MID) ---
                Forms\Components\Repeater::make('exam_overrides')
                    ->label('Custom Exam Rules (Overrides)')
                    ->helperText('Does a specific exam have different total marks (like a 50-mark 1st Mid)? Add it here. Otherwise, it uses the default distribution above.')
                    ->schema([
                        Forms\Components\Select::make('exam_id')
                            ->label('Select Exam')
                            ->options(function () {
                                return \App\Models\Exam::with(['schoolClass', 'academicYear'])->get()->mapWithKeys(function ($exam) {
                                    $className = $exam->schoolClass->name ?? 'No Class';
                                    $yearName = $exam->academicYear->name ?? 'No Year';
                                    return [$exam->id => "{$exam->name} ({$className} - {$yearName})"];
                                });
                            })
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\TextInput::make('written_total')
                            ->label('Written Total')
                            ->numeric()
                            ->required(),
                            
                        Forms\Components\TextInput::make('mcq_total')
                            ->label('MCQ Total')
                            ->numeric()
                            ->default(0)
                            ->required(),
                            
                        Forms\Components\TextInput::make('practical_total')
                            ->label('Practical Total')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Subject Name'),
                Tables\Columns\TextColumn::make('code')->label('Subject Code'),
                
                // --- SHOW THE PARTNER IN THE TABLE ---
                Tables\Columns\TextColumn::make('linkedSubject.name')
                    ->label('Partner Subject')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('written_total')
                    ->label('Written Max')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('mcq_total')
                    ->label('MCQ Max')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('practical_total')
                ->label('Practical Max')
                ->badge()
                ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                //Tables\Actions\AttachAction::make()->preloadRecordSelect(), 
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}