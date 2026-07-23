<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarkResource\Pages;
use App\Models\Mark;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\TeacherAllocation;
use App\Models\GradeScale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarkResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 7;

    protected static ?string $model = Mark::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Marks Entry';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('written_mark')->numeric(),
                Forms\Components\TextInput::make('mcq_mark')->numeric(),
                Forms\Components\TextInput::make('practical_mark')->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.student_id')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Student Name')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('exam.name')
                    ->label('Exam')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Subject')
                    ->badge(),
                    
                Tables\Columns\TextInputColumn::make('written_mark')
                    ->label('Written')
                    ->rules(fn (Mark $record) => [
                        'numeric', 
                        'min:0', 
                        'max:' . ($record->subject->getMarksForExam($record->exam_id)['written_total'] ?? 100) 
                    ]),
                    
                Tables\Columns\TextInputColumn::make('mcq_mark')
                    ->label('MCQ')
                    ->rules(fn (Mark $record) => [
                        'numeric', 
                        'min:0', 
                        'max:' . ($record->subject->getMarksForExam($record->exam_id)['mcq_total'] ?? 100)
                    ]),
                    
                Tables\Columns\TextInputColumn::make('practical_mark')
                    ->label('Practical')
                    ->rules(fn (Mark $record) => [
                        'numeric', 
                        'min:0', 
                        'max:' . ($record->subject->getMarksForExam($record->exam_id)['practical_total'] ?? 100)
                    ]),
                    
                Tables\Columns\TextColumn::make('marks_obtained')
                    ->label('Total')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('grade')
                    ->label('Grade')
                    ->badge()
                    ->color(function (?string $state): string {
                        if (!$state) return 'gray';
                        
                        $scaleMatch = GradeScale::where('letter_grade', $state)->first();
                        
                        if ($scaleMatch?->is_fail_grade) {
                            return 'danger'; 
                        }
                        
                        return ($state === 'A+' || $scaleMatch?->grade_point >= 4.00) ? 'success' : 'warning';
                    }),
                    
                Tables\Columns\TextColumn::make('gpa')
                    ->label('GPA')
                    ->numeric(2),
            ])
            
            ->filters([
                Tables\Filters\Filter::make('gradebook_filters')
                    ->columnSpan('full')
                    ->form([
                        Forms\Components\Grid::make([
                            'default' => 1,
                            'md' => 3,
                            'xl' => 6,
                        ])
                        ->schema([
                            Forms\Components\Select::make('academic_year_id')
                                ->label('Year')
                                ->options(\App\Models\AcademicYear::pluck('name', 'id'))
                                ->live(),
                                
                            Forms\Components\Select::make('school_class_id')
                                ->label('Class')
                                ->options(\App\Models\SchoolClass::pluck('name', 'id'))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('exam_id', null);
                                    $set('section_id', null);
                                    $set('study_group', null);
                                    $set('subject_id', null);
                                }),

                            Forms\Components\Select::make('exam_id')
                                ->label('Exam')
                                ->options(function (Forms\Get $get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return [];
                                    return \App\Models\Exam::where('school_class_id', $classId)->pluck('name', 'id');
                                })
                                ->live(),

                            Forms\Components\Select::make('section_id')
                                ->label('Section')
                                ->options(function (Forms\Get $get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return [];
                                    $class = \App\Models\SchoolClass::with('sections')->find($classId);
                                    return $class ? $class->sections->pluck('name', 'id') : [];
                                })
                                ->live(),

                            Forms\Components\Select::make('study_group')
                                ->label('Study Group')
                                ->options(\App\Models\StudyGroup::pluck('name', 'name'))
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('subject_id', null)),

                            Forms\Components\Select::make('subject_id')
                                ->label('Subject')
                                ->searchable()
                                ->live()
                                ->options(function (Forms\Get $get) {
                                    $classId = $get('school_class_id');
                                    $groupName = $get('study_group');
                                    
                                    if (!$classId) return [];

                                    $studyGroup = \App\Models\StudyGroup::where('name', $groupName)->first();
                                    $studyGroupId = $studyGroup?->id;

                                    $query = \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                        $q->where('school_classes.id', $classId);
                                    });

                                    if (blank($groupName)) {
                                        $subjects = $query->whereNull('study_group_id')->get();
                                    } else {
                                        $subjects = $query->where(function($subQuery) use ($studyGroupId) {
                                            $subQuery->whereNull('study_group_id')
                                                     ->when($studyGroupId, fn($q) => $q->orWhere('study_group_id', $studyGroupId));
                                        })->get();
                                    }

                                    return $subjects->mapWithKeys(function ($subject) {
                                        return [$subject->id => "{$subject->name} (" . ($subject->code ?? 'N/A') . ")"];
                                    })->toArray();
                                }),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['academic_year_id'] ?? null, fn($q, $v) => $q->where('academic_year_id', $v))
                            ->when($data['school_class_id'] ?? null, fn($q, $v) => $q->where('school_class_id', $v))
                            ->when($data['exam_id'] ?? null, fn($q, $v) => $q->where('exam_id', $v))
                            ->when($data['section_id'] ?? null, fn($q, $v) => $q->where('section_id', $v))
                            
                            ->when($data['subject_id'] ?? null, function ($q, $subjectId) {
                                $subject = \App\Models\Subject::find($subjectId);
                                if (!$subject) return $q->where('subject_id', $subjectId);

                                return $q->where('subject_id', $subjectId)
                                    ->whereHas('student.enrollments', function ($enrollmentQuery) use ($subject) {
                                        $enrollmentQuery->whereColumn('enrollments.school_class_id', 'marks.school_class_id')
                                            ->whereColumn('enrollments.academic_year_id', 'marks.academic_year_id')
                                            ->where(function ($subFilter) use ($subject) {
                                                $isOptionalType = ($subject->subject_type === 'Optional' || $subject->type === 'Optional');
                                                
                                                if ($isOptionalType) {
                                                    $subFilter->where('enrollments.optional_subject_id', $subject->id);
                                                } else {
                                                    $subFilter->whereNotNull('enrollments.id');
                                                }
                                            })
                                            ->whereHas('user', function ($userQuery) use ($subject) {
                                                $subjectNameLower = strtolower($subject->name);
                                                
                                                if (str_contains($subjectNameLower, 'islam')) {
                                                    $userQuery->whereRaw('LOWER(religion) = ?', ['islam']);
                                                } elseif (str_contains($subjectNameLower, 'hindu')) {
                                                    $userQuery->whereRaw('LOWER(religion) LIKE ?', ['%hindu%']);
                                                } elseif (str_contains($subjectNameLower, 'christian')) {
                                                    $userQuery->whereRaw('LOWER(religion) LIKE ?', ['%christian%']);
                                                } elseif (str_contains($subjectNameLower, 'buddhi')) {
                                                    $userQuery->whereRaw('LOWER(religion) LIKE ?', ['%buddhi%']);
                                                }
                                            });
                                    });
                            })
                            
                            ->when($data['study_group'] ?? null, function ($q, $groupValue) use ($data) {
                                return $q->whereHas('student', function ($studentQuery) use ($groupValue, $data) {
                                    $studentQuery->whereHas('enrollments', function ($enrollmentQuery) use ($groupValue, $data) {
                                        $enrollmentQuery->where('study_group', $groupValue)
                                            ->when($data['academic_year_id'] ?? null, fn($eq, $y) => $eq->where('academic_year_id', $y))
                                            ->when($data['school_class_id'] ?? null, fn($eq, $c) => $eq->where('school_class_id', $c));
                                    });
                                });
                            });
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            
            ->headerActions([
                Tables\Actions\Action::make('generate_mark_sheet')
                    ->label('Generate Mark Sheet')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('academic_year_id')
                            ->label('Academic Year')
                            ->options(AcademicYear::pluck('name', 'id'))
                            ->required(),
                            
                        Forms\Components\Select::make('school_class_id')
                            ->label('Class')
                            ->options(SchoolClass::pluck('name', 'id'))
                            ->required()
                            ->live() 
                            ->afterStateUpdated(function (Forms\Set $set) {
                                $set('exam_id', null);
                                $set('section_id', null);
                                $set('study_group', null);
                                $set('subject_id', null);
                            }),

                        Forms\Components\Select::make('exam_id')
                            ->label('Exam')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];
                                return Exam::where('school_class_id', $classId)->pluck('name', 'id');
                            })
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('section_id')
                            ->label('Section')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];
                                $class = SchoolClass::with('sections')->find($classId);
                                return $class ? $class->sections->pluck('name', 'id') : [];
                            })
                            ->nullable()
                            ->live(),

                        Forms\Components\Select::make('study_group')
                            ->label('Study Group (Only for Class 9-10)')
                            ->options([
                                'Science' => 'Science',
                                'Arts/Humanities' => 'Arts / Humanities',
                                'Commerce' => 'Commerce',
                            ])
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('subject_id', null)),

                        Forms\Components\Select::make('subject_id')
                            ->label('Subject')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                $groupName = $get('study_group');
                                if (!$classId) return [];

                                $studyGroup = \App\Models\StudyGroup::where('name', $groupName)->first();
                                $studyGroupId = $studyGroup?->id;

                                $query = Subject::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId));

                                if (blank($groupName)) {
                                    $subjects = $query->whereNull('study_group_id')->get();
                                } else {
                                    $subjects = $query->where(function($subQuery) use ($studyGroupId) {
                                        $subQuery->whereNull('study_group_id')
                                                 ->when($studyGroupId, fn($q) => $q->orWhere('study_group_id', $studyGroupId));
                                    })->get();
                                }

                                return $subjects->mapWithKeys(fn ($subject) => [
                                    $subject->id => "{$subject->name} (" . ($subject->code ?? 'N/A') . ")"
                                ])->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                    ])
                    ->action(function (array $data) {
                        $targetSubject = Subject::find($data['subject_id']);
                        if (!$targetSubject) return;

                        $enrollments = Enrollment::where('school_class_id', $data['school_class_id'])
                            ->where('academic_year_id', $data['academic_year_id'])
                            ->when($data['study_group'], function ($q, $groupName) {
                                return $q->where('study_group', $groupName);
                            })
                            ->when(blank($data['study_group']), function ($q) {
                                return $q->where(fn($sub) => $sub->whereNull('study_group')->orWhere('study_group', 'like', '%General%'));
                            })
                            ->when($data['section_id'], function ($q, $sectionId) {
                                return $q->where('section_id', $sectionId);
                            })
                            ->get();

                        if ($enrollments->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Students Found!')
                                ->body('No students matched this criteria combination.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $savedCount = 0;
                        $subjectNameLower = strtolower($targetSubject->name);

                        foreach ($enrollments as $enrollment) {
                            $studentId = $enrollment->user_id; 
                            if (!$studentId) continue;

                            $studentProfile = $enrollment->user;
                            $studentReligion = $studentProfile ? strtolower(trim($studentProfile->religion)) : '';
                            $studentGroup = $enrollment->study_group ?? 'General'; 

                            // 🕋 Religion Alignment Check
                            if (str_contains($subjectNameLower, 'islam') || str_contains($subjectNameLower, 'hindu') || str_contains($subjectNameLower, 'christian') || str_contains($subjectNameLower, 'buddhi')) {
                                if (str_contains($subjectNameLower, 'islam') && $studentReligion !== 'islam') continue;
                                if (str_contains($subjectNameLower, 'hindu') && !str_contains($studentReligion, 'hindu')) continue;
                                if (str_contains($subjectNameLower, 'christian') && !str_contains($studentReligion, 'christian')) continue;
                                if (str_contains($subjectNameLower, 'buddhi') && !str_contains($studentReligion, 'buddhi')) continue;
                            }

                            // 🛑 STREAM GROUP SAFEGUARD PROTECTION
                            if ($studentGroup === 'Science') {
                                if (str_contains($subjectNameLower, 'general science') || str_contains($subjectNameLower, 'সাধারণ বিজ্ঞান')) {
                                    continue;
                                }
                            } else {
                                if (str_contains($subjectNameLower, 'physics') || str_contains($subjectNameLower, 'পদার্থবিজ্ঞান') ||
                                    str_contains($subjectNameLower, 'chemistry') || str_contains($subjectNameLower, 'রসায়ন') ||
                                    str_contains($subjectNameLower, 'biology') || str_contains($subjectNameLower, 'জীববিজ্ঞান')) {
                                    continue;
                                }
                            }

                            // 🛑 Optional / 4th Subject Check
                            $isOptionalType = ($targetSubject->subject_type === 'Optional' || $targetSubject->type === 'Optional');
                            if ($isOptionalType) {
                                if ((int) $enrollment->optional_subject_id !== (int) $targetSubject->id) {
                                    continue; 
                                }
                            }

                            $mark = Mark::firstOrNew([
                                'exam_id' => $data['exam_id'],
                                'subject_id' => $data['subject_id'],
                                'student_id' => $studentId, 
                            ]);
                            
                            $mark->academic_year_id = $data['academic_year_id'];
                            $mark->school_class_id = $data['school_class_id'];
                            $mark->section_id = $data['section_id'] ?: $enrollment->section_id; 
                            
                            if (!$mark->exists) {
                                $mark->written_mark = 0;
                                $mark->mcq_mark = 0;
                                $mark->practical_mark = 0;
                                $mark->marks_obtained = 0;
                                $mark->grade = 'F';
                                $mark->gpa = 0.00;
                            }
                            
                            $mark->save();
                            $savedCount++; 
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Generation Complete!')
                            ->body("Generated mark sheets for {$savedCount} students matching subject structure rules.")
                            ->success()
                            ->send();
                    }),

                // 🌟 NEW DETACH SUBJECT MARKS ACTION 🌟
                Tables\Actions\Action::make('detach_subject_marks')
                    ->label('Detach Subject Marks')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Detach & Purge Subject Marks')
                    ->modalDescription('This will permanently delete all generated mark entries for the selected subject across all students in this exam. Are you sure?')
                    ->modalSubmitActionLabel('Yes, Purge Subject Marks')
                    ->form([
                        Forms\Components\Select::make('academic_year_id')
                            ->label('Academic Year')
                            ->options(AcademicYear::pluck('name', 'id'))
                            ->required(),

                        Forms\Components\Select::make('school_class_id')
                            ->label('Class')
                            ->options(SchoolClass::pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set) {
                                $set('exam_id', null);
                                $set('subject_id', null);
                            }),

                        Forms\Components\Select::make('exam_id')
                            ->label('Exam')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];
                                return Exam::where('school_class_id', $classId)->pluck('name', 'id');
                            })
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('subject_id')
                            ->label('Select Subject to Detach')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return Subject::pluck('name', 'id');

                                return Subject::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $deletedCount = Mark::where('academic_year_id', $data['academic_year_id'])
                            ->where('school_class_id', $data['school_class_id'])
                            ->where('exam_id', $data['exam_id'])
                            ->where('subject_id', $data['subject_id'])
                            ->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Subject Marks Detached!')
                            ->body("Successfully purged mark entries for {$deletedCount} students.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('finish_grading')
                    ->label('Finish & Save All')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Finish Grading?')
                    ->modalDescription('Because marks save instantly as you type, you do not need to submit them. Clicking confirm will simply clear your screen so you can select the next class to grade.')
                    ->modalSubmitActionLabel('Yes, Clear Screen')
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $livewire->resetTableFiltersForm();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Grading Complete!')
                            ->body('All marks are securely saved in the database.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->type === 'teacher') {
            $allocatedClassIds = TeacherAllocation::where('user_id', $user->id)->pluck('school_class_id');
            return $query->whereIn('school_class_id', $allocatedClassIds);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarks::route('/'),
        ];
    }
}