<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamRoutineResource\Pages;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamRoutine;
use App\Models\SchoolClass;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamRoutineResource extends Resource
{
    protected static ?string $model = ExamRoutine::class;

    protected static ?string $navigationGroup = 'Exam';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 🌟 HEADER SELECTION (SELECTED ONCE)
                FormSection::make('Exam Details')
                    ->schema([
                        Forms\Components\Select::make('academic_year_id')
                            ->label('Academic Year')
                            ->options(AcademicYear::pluck('name', 'id'))
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('school_class_id')
                            ->label('Class')
                            ->options(SchoolClass::pluck('name', 'id'))
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('exam_id')
                            ->label('Exam')
                            ->options(function (Get $get) {
                                $yearId = $get('academic_year_id');
                                $classId = $get('school_class_id');
                                if (! $yearId || ! $classId) {
                                    return [];
                                }

                                return Exam::where('academic_year_id', $yearId)
                                    ->where(function ($q) use ($classId) {
                                        $q->whereNull('school_class_id')
                                            ->orWhere('school_class_id', $classId);
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->disabled(fn (Get $get) => ! $get('academic_year_id') || ! $get('school_class_id'))
                            ->live(),
                    ])->columns(3),

                // 🌟 REPEATER FOR MULTIPLE SUBJECT SCHEDULES
                FormSection::make('Schedule Subjects')
                    ->visibleOn('create')
                    ->schema([
                        Forms\Components\Repeater::make('schedules')
                            ->label('Exam Schedules')
                            ->addActionLabel('Add Subject Schedule')
                            ->schema([
                                Forms\Components\Select::make('subject_id')
                                    ->label('Subject')
                                    ->options(function (Get $get) {
                                        // $get('../../school_class_id') reaches up outside the repeater to the parent section
                                        $classId = $get('../../school_class_id');
                                        if (! $classId) {
                                            return [];
                                        }

                                        return Subject::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable(),

                                Forms\Components\DatePicker::make('exam_date')
                                    ->label('Exam Date')
                                    ->required()
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->required(),

                                Forms\Components\TextInput::make('room_number')
                                    ->label('Room No. (Optional)')
                                    ->placeholder('e.g. Room 102'),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->required(),
                    ]),

                // 🌟 SINGLE EDIT FIELDS (FOR EDITING AN EXISTING RECORD)
                FormSection::make('Subject Schedule Details')
                    ->visibleOn('edit')
                    ->schema([
                        Forms\Components\Select::make('subject_id')
                            ->label('Subject')
                            ->options(function (Get $get) {
                                $classId = $get('school_class_id');
                                if (! $classId) {
                                    return [];
                                }

                                return Subject::whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $classId))
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),

                        Forms\Components\DatePicker::make('exam_date')
                            ->label('Exam Date')
                            ->required(),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required(),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time')
                            ->required(),

                        Forms\Components\TextInput::make('room_number')
                            ->label('Room No. (Optional)'),
                    ])->columns(2),

                FormSection::make('Additional Information')
                    ->schema([
                        Textarea::make('note')
                            ->label('Special Note / বি:দ্র:')
                            ->placeholder('বি:দ্র: অনিবার্য কারণ বশত কোন পরীক্ষা স্থগিত হলে ঐ পরীক্ষার তারিখ ও সময় পরে জানানো হবে...')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('academicYear.name')->label('Year')->sortable(),
                Tables\Columns\TextColumn::make('schoolClass.name')->label('Class')->sortable(),
                Tables\Columns\TextColumn::make('exam.name')->label('Exam')->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->searchable(),
                Tables\Columns\TextColumn::make('exam_date')->label('Date')->date('d M, Y')->sortable(),
                Tables\Columns\TextColumn::make('start_time')->label('Start Time')->time('h:i A'),
                Tables\Columns\TextColumn::make('end_time')->label('End Time')->time('h:i A'),
                Tables\Columns\TextColumn::make('room_number')->label('Room')->placeholder('N/A'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('academic_year_id')->label('Year')->options(AcademicYear::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('school_class_id')->label('Class')->options(SchoolClass::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('exam_id')->label('Exam')->options(Exam::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExamRoutines::route('/'),
            'create' => Pages\CreateExamRoutine::route('/create'),
            'edit' => Pages\EditExamRoutine::route('/{record}/edit'),
        ];
    }
}
