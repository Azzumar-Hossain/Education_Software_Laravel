<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\StudyGroup;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class SubjectResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $model = Subject::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subject Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Subject Name (e.g., Bangla)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Subject Code (e.g., 101)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('linked_subject_id')
                            ->label('Combine With (Partner Subject)')
                            ->options(\App\Models\Subject::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Select partner (e.g., English 2nd Paper)')
                            ->helperText('Merging? Select the 2nd paper here.')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('study_group_id')
                            ->label('Study Group')
                            ->relationship('studyGroup', 'name')
                            ->nullable() 
                            ->searchable()
                            ->preload() 
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return \App\Models\StudyGroup::create($data)->id;
                            }),

                        Forms\Components\Select::make('type')
                            ->label('Subject Type')
                            ->options([
                                'Core / Compulsory' => 'Core / Compulsory',
                                'Elective / Optional' => 'Elective / Optional',
                                'Practical' => 'Practical',
                            ])
                            ->required(),

                    ])->columns(2),

                Forms\Components\Fieldset::make('Exam Mark Distribution (Default)')
                    ->schema([
                        Forms\Components\TextInput::make('written_total')->label('Written Total')->numeric()->default(100),
                        Forms\Components\TextInput::make('written_pass_mark')->label('Written Pass')->numeric()->default(33),

                        Forms\Components\TextInput::make('mcq_total')->label('MCQ Total (0 if none)')->numeric()->default(0),
                        Forms\Components\TextInput::make('mcq_pass_mark')->label('MCQ Pass (0 if none)')->numeric()->default(0),

                        Forms\Components\TextInput::make('practical_total')->label('Practical Total (0 if none)')->numeric()->default(0),
                        Forms\Components\TextInput::make('practical_pass_mark')->label('Practical Pass (0 if none)')->numeric()->default(0),

                        // 🌟 ADDED: OVERALL PASS RULE TOGGLE AND THRESHOLD INPUT 🌟
                        Forms\Components\Toggle::make('overall_pass_only')
                            ->label('Overall Pass Rule (Combined Total)')
                            ->helperText('If enabled, student passes as long as Total Marks >= Pass Mark (e.g., 33/100 or 17/50), ignoring separate Written/MCQ limits.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('overall_pass_mark')
                            ->label('Overall Total Pass Mark')
                            ->numeric()
                            ->default(33)
                            ->visible(fn (Forms\Get $get) => (bool) $get('overall_pass_only'))
                            ->columnSpanFull(),
                    ])->columns(2),

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),

                Tables\Columns\TextColumn::make('linkedSubject.name')
                    ->label('Partner Subject')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('studyGroup.name')
                    ->label('Study Group')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('written_total')->label('Written Max')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('mcq_total')->label('MCQ Max')->badge()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Filter by Class')
                    ->relationship('schoolClasses', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}