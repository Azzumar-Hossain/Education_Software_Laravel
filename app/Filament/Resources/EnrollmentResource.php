<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnrollmentResource\Pages;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 9;

    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Student')
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
                    ->nullable(),

                Forms\Components\TextInput::make('roll_number')
                    ->label('Roll Number')
                    ->numeric(),

                Forms\Components\Select::make('study_group')
                    ->label('Study Group')
                    ->options([
                        'Science' => 'Science',
                        'Arts/Humanities' => 'Arts/Humanities',
                        'Commerce' => 'Commerce',
                        'General' => 'General',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('optional_subject_id', null)),

                Forms\Components\Select::make('optional_subject_id')
                    ->label('4th / Optional Subject')
                    ->options(function (Get $get) {
                        $classId = $get('school_class_id') ?? $get('class_id'); 
                        
                        if (! $classId) {
                            return [];
                        }

                        return \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                $q->where('school_classes.id', $classId);
                            })
                            ->where(function($q) {
                                $q->where('subject_type', 'Optional')
                                ->orWhere('type', 'like', '%Optional%')
                                ->orWhere('type', 'like', '%4th%');
                            })
                            ->get()
                            ->mapWithKeys(function ($subject) {
                                return [$subject->id => "{$subject->name} ({$subject->code})"];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Select the 4th subject matching this student group stream.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('roll_number', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('user.student_id')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student Name')
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
                    ->default('N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('study_group')
                    ->label('Group')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roll_number')
                    ->label('Roll Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active', 'Passed' => 'success',
                        'Pending' => 'warning',
                        'Inactive', 'Failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('exam_status')
                    ->label('Exam Status')
                    ->getStateUsing(function ($record) {
                        $marks = Mark::where('student_id', $record->user_id)
                            ->where('academic_year_id', $record->academic_year_id)
                            ->where('school_class_id', $record->school_class_id)
                            ->get();

                        if ($marks->isEmpty()) {
                            return 'Pending Marks';
                        }

                        $hasFailed = $marks->where('grade', 'F')->isNotEmpty();

                        $requiredSubjectsCount = Subject::whereHas('studyGroups', function($q) use ($record) {
                                $q->where('study_groups.name', $record->study_group);
                            })
                            ->get()
                            ->filter(function($subject) {
                                $type = $subject->type ?? $subject->subject_type ?? '';
                                return !in_array($type, ['Optional', '4th / Optional Subject', 'Elective / Optional', 'Practical']);
                            })
                            ->count();

                        $subjectsTaken = $marks->pluck('subject_id')->unique()->count();
                        $isIncomplete = $subjectsTaken < $requiredSubjectsCount;

                        return ($hasFailed || $isIncomplete) ? 'Failed' : 'Passed';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Passed' => 'success',
                        'Failed' => 'danger',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordAction(null)
            
            ->filters([
                // 1. STANDARD NATIVE RELATIONSHIP SELECT FILTER
                Tables\Filters\SelectFilter::make('school_class_id')
                    ->relationship('schoolClass', 'name')
                    ->label('Filter by Class')
                    ->preload()
                    ->searchable(), // Native SelectFilter provides structural isolation safely

                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->relationship('academicYear', 'name')
                    ->label('Filter by Year')
                    ->searchable()
                    ->preload(),

                // 2. 🌟 STRUCTURALLY CLEAN QUERY-BASED STUDY GROUP FILTER BLOCK 🌟
                Tables\Filters\SelectFilter::make('study_group')
                    ->label('Filter by Group')
                    ->options([
                        'Science' => 'Science',
                        'Arts/Humanities' => 'Arts / Humanities',
                        'Commerce' => 'Commerce',
                    ])
                    ->query(function (Builder $query, array $data) {
                        // Apply the study group filtering query only if a group value has been picked
                        return $query->when($data['value'] ?? null, fn($q, $group) => $q->where('study_group', $group));
                    })
                    ->indicateUsing(function (array $data): array {
                        if (blank($data['value'] ?? null)) return [];

                        // 🛑 THE SECURITY VALVE: Fetch the active class context selected on the table
                        // This allows the badge to display safely without using the form-level $get() state pipeline
                        $requestFilters = request()->query('tableFilters', []);
                        $classId = $requestFilters['school_class_id']['value'] ?? null;

                        if (!$classId) return []; // Hide indicator if no class is selected yet

                        $className = \App\Models\SchoolClass::find($classId)?->name;
                        
                        // Only show the filter indicator badge for senior streams (Class 9 or 10)
                        if ($className && (str_contains($className, '9') || str_contains($className, '10'))) {
                            return ["Group: {$data['value']}"];
                        }

                        return []; // Silently ignore if it's a junior grade (Classes 6-8)
                    }),

                Tables\Filters\SelectFilter::make('exam_status')
                    ->label('Filter by Exam Status')
                    ->options([
                        'passed' => 'Passed Students',
                        'failed' => 'Failed Students',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'failed') {
                            $query->whereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('marks')
                                    ->whereColumn('marks.student_id', 'enrollments.user_id')
                                    ->whereColumn('marks.academic_year_id', 'enrollments.academic_year_id')
                                    ->whereColumn('marks.school_class_id', 'enrollments.school_class_id')
                                    ->where('marks.grade', 'F');
                            });
                        } elseif ($data['value'] === 'passed') {
                            $query->whereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('marks')
                                    ->whereColumn('marks.student_id', 'enrollments.user_id')
                                    ->whereColumn('marks.academic_year_id', 'enrollments.academic_year_id')
                                    ->whereColumn('marks.school_class_id', 'enrollments.school_class_id');
                            })->whereNotExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('marks')
                                    ->whereColumn('marks.student_id', 'enrollments.user_id')
                                    ->whereColumn('marks.academic_year_id', 'enrollments.academic_year_id')
                                    ->whereColumn('marks.school_class_id', 'enrollments.school_class_id')
                                    ->where('marks.grade', 'F');
                            });
                        }
                    }),
            ])

            ->headerActions([
                Tables\Actions\Action::make('promote_students')
                    ->label('Mass Promote Class')
                    ->icon('heroicon-o-academic-cap')
                    ->color('warning')
                    ->modalHeading('Promote Entire Class')
                    ->modalDescription('Students will be ranked by Total Marks first, then GPA. FAILED or INCOMPLETE students will NOT be promoted.')
                    ->form([
                        Forms\Components\Section::make('Step 1: Promote FROM (Current Class)')
                            ->schema([
                                Forms\Components\Select::make('from_academic_year_id')
                                    ->label('Current Academic Year')
                                    ->options(AcademicYear::pluck('name', 'id'))
                                    ->required(),
                                Forms\Components\Select::make('from_school_class_id')
                                    ->label('Current Class')
                                    ->options(SchoolClass::pluck('name', 'id'))
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('from_section_id')
                                    ->label('Current Section (Optional)')
                                    ->options(function (Get $get) {
                                        $class = SchoolClass::with('sections')->find($get('from_school_class_id'));
                                        return $class ? $class->sections->pluck('name', 'id') : [];
                                    })
                                    ->live(),
                            ])->columns(3),

                        Forms\Components\Section::make('Step 2: Promote TO (Destination Class)')
                            ->schema([
                                Forms\Components\Select::make('to_academic_year_id')
                                    ->label('Next Academic Year')
                                    ->options(AcademicYear::pluck('name', 'id'))
                                    ->required(),
                                Forms\Components\Select::make('to_school_class_id')
                                    ->label('Next Class')
                                    ->options(SchoolClass::pluck('name', 'id'))
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('to_section_id')
                                    ->label('Next Section (Optional)')
                                    ->options(function (Get $get) {
                                        $class = SchoolClass::with('sections')->find($get('to_school_class_id'));
                                        return $class ? $class->sections->pluck('name', 'id') : [];
                                    })
                                    ->live(),
                            ])->columns(3),
                    ])
                    ->action(function (array $data) {
                        $query = Enrollment::where('academic_year_id', $data['from_academic_year_id'])
                            ->where('school_class_id', $data['from_school_class_id']);

                        if (! empty($data['from_section_id'])) {
                            $query->where('section_id', $data['from_section_id']);
                        } else {
                            $query->whereNull('section_id');
                        }

                        $currentEnrollments = $query->get();

                        if ($currentEnrollments->isEmpty()) {
                            Notification::make()
                                ->title('No Students Found!')
                                ->body('There are no students in the "FROM" class to promote.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $studentScores = [];
                        $failedCount = 0; 

                        foreach ($currentEnrollments as $enrollment) {
                            $marks = Mark::where('student_id', $enrollment->user_id)
                                ->where('academic_year_id', $data['from_academic_year_id'])
                                ->where('school_class_id', $data['from_school_class_id'])
                                ->get();

                            $hasFailed = $marks->where('grade', 'F')->isNotEmpty();
                            
                            $requiredSubjectsCount = Subject::whereHas('studyGroups', function($q) use ($enrollment) {
                                    $q->where('study_groups.name', $enrollment->study_group);
                                })
                                ->get()
                                ->filter(function($subject) {
                                    $type = $subject->type ?? $subject->subject_type ?? '';
                                    return !in_array($type, ['Optional', '4th / Optional Subject', 'Elective / Optional', 'Practical']);
                                })
                                ->count();
                            
                            $subjectsTaken = $marks->pluck('subject_id')->unique()->count();
                            $isIncomplete = $subjectsTaken < $requiredSubjectsCount;

                            if ($hasFailed || $isIncomplete) {
                                $failedCount++;
                                continue; 
                            }

                            $totalMarks = $marks->sum('marks_obtained');
                            $finalGpa = $marks->count() > 0 ? $marks->avg('gpa') : 0;

                            $studentScores[] = [
                                'enrollment' => $enrollment,
                                'total_marks' => $totalMarks,
                                'final_gpa' => $finalGpa,
                            ];
                        }

                        usort($studentScores, function ($a, $b) {
                            if ($a['total_marks'] != $b['total_marks']) {
                                return $b['total_marks'] <=> $a['total_marks'];
                            }
                            return $b['final_gpa'] <=> $a['final_gpa'];
                        });

                        $maxExistingRoll = Enrollment::where('academic_year_id', $data['to_academic_year_id'])
                            ->where('school_class_id', $data['to_school_class_id'])
                            ->when($data['to_section_id'] ?? null, fn($q, $sec) => $q->where('section_id', $sec))
                            ->max('roll_number') ?? 0;
                            
                        $newRollNumber = $maxExistingRoll + 1;

                        $promotedCount = 0;
                        $skippedCount = 0;

                        foreach ($studentScores as $scoreData) {
                            $enrollment = $scoreData['enrollment'];

                            $alreadyEnrolled = Enrollment::where('user_id', $enrollment->user_id)
                                ->where('academic_year_id', $data['to_academic_year_id'])
                                ->where('school_class_id', $data['to_school_class_id'])
                                ->exists();

                            if ($alreadyEnrolled) {
                                $skippedCount++;
                                continue;
                            }

                            $newEnrollment = $enrollment->replicate();
                            $newEnrollment->academic_year_id = $data['to_academic_year_id'];
                            $newEnrollment->school_class_id = $data['to_school_class_id'];
                            $newEnrollment->section_id = $data['to_section_id'] ?? null;
                            $newEnrollment->status = 'Passed'; 
                            $newEnrollment->roll_number = $newRollNumber++;
                            $newEnrollment->save();

                            $promotedCount++;
                        }

                        $message = "Promoted {$promotedCount} students by rank.";
                        if ($failedCount > 0) {
                            $message .= " ({$failedCount} held back).";
                        }
                        if ($skippedCount > 0) {
                            $message .= " ({$skippedCount} skipped: already enrolled).";
                        }

                        Notification::make()
                            ->title('Promotion Complete')
                            ->body($message)
                            ->success()
                            ->send();

                        $filters = [
                            'academic_year_id' => ['value' => $data['to_academic_year_id']],
                            'school_class_id' => ['value' => $data['to_school_class_id']],
                        ];
                        if (!empty($data['to_section_id'])) {
                            $filters['section_id'] = ['value' => $data['to_section_id']];
                        }

                        return redirect(EnrollmentResource::getUrl('index', [
                            'tableFilters' => $filters
                        ]));
                    }),
            ])

            ->actions([
                Tables\Actions\Action::make('view_profile')
                    ->label('Profile')
                    ->icon('heroicon-o-user')
                    ->color('success')
                    ->modalContent(function (Enrollment $record) {
                        $student = $record->user;
                        $classId = $record->school_class_id;
                        $studyGroupName = $record->study_group; 
                        $optionalSubjectId = $record->optional_subject_id;

                        $resolvedGroupId = \App\Models\StudyGroup::where('name', $studyGroupName)->first()?->id;

                        $subjects = \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                $q->where('school_class_id', $classId);
                            })
                            ->where(function ($query) use ($resolvedGroupId, $optionalSubjectId) {
                                $query->whereNull('study_group_id')
                                      ->where(fn($q) => $q->where('subject_type', 'Core')->orWhere('type', 'Core'))
                                      
                                      ->orWhere(function ($q) use ($optionalSubjectId) {
                                          $q->whereNull('study_group_id')
                                            ->where(fn($e) => $e->where('subject_type', 'Optional')->orWhere('type', 'Optional'))
                                            ->where('id', $optionalSubjectId);
                                      })
                                      
                                      ->orWhere(function ($q) use ($resolvedGroupId, $optionalSubjectId) {
                                          $q->where('study_group_id', $resolvedGroupId)
                                            ->where(function ($subQ) use ($optionalSubjectId) {
                                                $subQ->whereIn('subject_type', ['Group', 'Core'])
                                                     ->orWhereIn('type', ['Group', 'Core'])
                                                     ->orWhere('id', $optionalSubjectId);
                                            });
                                      });
                            })
                            ->orderBy('code')
                            ->get();

                        $subjectsHtml = $subjects->isEmpty() 
                            ? "<span class='text-gray-400'>No curriculum subjects mapped matching this stream's criteria.</span>"
                            : $subjects->map(function ($subject) use ($optionalSubjectId) {
                                $is4thChoice = ($subject->id == $optionalSubjectId && ($subject->subject_type === 'Optional' || $subject->type === 'Optional' || str_contains(strtolower($subject->type), 'option')));
                                
                                $badgeText = 'Compulsory';
                                $badgeColor = 'text-gray-500 dark:text-gray-400';
                                
                                if ($is4thChoice) {
                                    $badgeText = '4th Subject Choice';
                                    $badgeColor = 'text-primary-600 dark:text-primary-400 font-semibold';
                                } elseif ($subject->subject_type === 'Optional' || $subject->type === 'Optional') {
                                    $badgeText = 'Optional';
                                    $badgeColor = 'text-warning-600 dark:text-warning-400';
                                } elseif ($subject->subject_type === 'Group' || $subject->type === 'Group') {
                                    $badgeText = 'Group Main';
                                    $badgeColor = 'text-info-600 dark:text-info-400';
                                }

                                return "
                                    <div class='flex items-center justify-between p-3 mb-2 border border-gray-100 dark:border-gray-800 rounded-lg bg-gray-50/50 dark:bg-gray-900/50'>
                                        <div>
                                            <div class='font-bold text-gray-900 dark:text-white'>{$subject->name}</div>
                                            <div class='text-xs {$badgeColor}'>{$badgeText}</div>
                                        </div>
                                        <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 font-mono'>
                                            {$subject->code}
                                        </span>
                                    </div>
                                ";
                            })->implode('');

                        $optionalSubjectName = \App\Models\Subject::find($optionalSubjectId)?->name ?? 'N/A';

                        return view('filament.components.student-profile-modal', [
                            'enrollment' => $record,
                            'student' => $student,
                            'subjectsHtml' => $subjectsHtml,
                            'optionalSubjectName' => $optionalSubjectName,
                        ]);
                    })
                    ->modalSubmitAction(false) 
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge),

                Tables\Actions\Action::make('view_marksheet')
                    ->label('Marksheet')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Enrollment $record): string => EnrollmentResource::getUrl('marks', ['record' => $record])),

                Tables\Actions\EditAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('bulk_assign_group')
                        ->label('Change Study Group for Selected')
                        ->icon('heroicon-o-squares-plus')
                        ->color('warning')
                        ->modalHeading('Mass Assign Study Group')
                        ->modalDescription('This will update the Study Group for all checked student enrollments instantly.')
                        ->form([
                            Forms\Components\Select::make('study_group')
                                ->label('Target Study Group')
                                ->options([
                                    'Science' => 'Science',
                                    'Arts/Humanities' => 'Arts/Humanities',
                                    'Commerce' => 'Commerce',
                                    'General' => 'General',
                                ])
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            foreach ($records as $enrollment) {
                                $enrollment->update([
                                    'study_group' => $data['study_group'],
                                    'optional_subject_id' => null, 
                                ]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Group Assignment Successful')
                                ->body("Successfully shifted " . $records->count() . " students to the " . $data['study_group'] . " stream track.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_assign_optional_subject')
                        ->label('Assign 4th / Optional Subject')
                        ->icon('heroicon-o-book-open')
                        ->color('success')
                        ->modalHeading('Mass Assign 4th / Optional Subject')
                        ->modalDescription('Select the elective paper to apply to all selected student profiles.')
                        ->form(function (\Illuminate\Support\Collection $records) {
                            $firstRecord = $records->first();
                            $classId = $firstRecord ? $firstRecord->school_class_id : null;

                            if (!$classId) {
                                return [
                                    Forms\Components\Placeholder::make('error_msg')
                                        ->content('Please ensure all selected students belong to the same Class.')
                                    ];
                            }

                            $options = \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                    $q->where('school_classes.id', $classId);
                                })
                                ->where(function($q) {
                                    $q->where('subject_type', 'Optional')
                                    ->orWhere('type', 'like', '%Optional%')
                                    ->orWhere('type', 'like', '%4th%');
                                })
                                ->get()
                                ->mapWithKeys(fn($subject) => [$subject->id => "{$subject->name} ({$subject->code})"])
                                ->toArray();

                            return [
                                Forms\Components\Select::make('optional_subject_id')
                                    ->label('Choose 4th / Optional Subject')
                                    ->options($options)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('This option list displays subjects marked as Optional for this class level.'),
                            ];
                        })
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            $subjectName = \App\Models\Subject::find($data['optional_subject_id'])?->name ?? 'Subject';

                            foreach ($records as $enrollment) {
                                $enrollment->update([
                                    'optional_subject_id' => $data['optional_subject_id'],
                                ]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Elective Assignment Complete')
                                ->body("Successfully assigned '{$subjectName}' as the 4th subject for " . $records->count() . " students.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'create' => Pages\CreateEnrollment::route('/create'),
            'edit' => Pages\EditEnrollment::route('/{record}/edit'),
            'marks' => Pages\StudentMarks::route('/{record}/marks'), 
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->type === 'teacher') {
            $allocatedClassIds = TeacherAllocation::where('user_id', $user->id)
                ->pluck('school_class_id');

            return $query->whereIn('school_class_id', $allocatedClassIds);
        }

        return $query;
    }
}