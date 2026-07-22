<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\ViewField;

class StudentResource extends Resource
{
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 3;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Student List';
    protected static ?string $modelLabel = 'Student';
    protected static ?string $slug = 'students';

    // ONLY show students in this list
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'student');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Student Details')
                    ->tabs([
                        // TAB 1: STUDENT INFO
                        Forms\Components\Tabs\Tab::make('Student Info')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Forms\Components\Section::make('System Account')
                                    ->schema([
                                        Forms\Components\ViewField::make('avatar')
                                            //->image()
                                            //->avatar() 
                                            //->directory('student-avatars')
                                            ->label('Student Photo')
                                            ->view('filament.forms.components.custom-student-photo-uploader')
                                            ->columnSpanFull(), 
                                            //->alignCenter(),

                                        Forms\Components\TextInput::make('name')->required()->label('Full Name (English)'),
                                        Forms\Components\TextInput::make('name_bn')->label('Full Name (Bangla)'),
                                        
                                        Forms\Components\TextInput::make('student_id')
                                            ->label('Student ID No.')
                                            ->readOnly()
                                            ->helperText('This ID is auto-generated upon creation.')
                                            ->hidden(fn (string $operation): bool => $operation === 'create'),
                                            
                                        Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->label('Login Email'),
                                        Forms\Components\TextInput::make('password')
                                            ->password()
                                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->revealable(),
                                        Forms\Components\Hidden::make('type')->default('student'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Personal Details')
                                    ->schema([
                                        Forms\Components\DatePicker::make('dob')->label('Date of Birth')->displayFormat('d/m/Y'),
                                        Forms\Components\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']),
                                        Forms\Components\Select::make('religion')->options(['Islam' => 'Islam', 'Hinduism' => 'Hinduism', 'Christianity' => 'Christianity', 'Buddhism' => 'Buddhism', 'Other' => 'Other']),
                                        Forms\Components\Select::make('blood_group')->options(['A+' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'O+' => 'O+', 'O-' => 'O-', 'AB+' => 'AB+', 'AB-' => 'AB-']),
                                        Forms\Components\TextInput::make('nationality')->default('Bangladeshi'),
                                        Forms\Components\TextInput::make('birth_reg_no')->label('Birth Reg. No'),
                                        Forms\Components\TextInput::make('student_mobile_no')->label('Student Mobile No')->tel(),
                                        Forms\Components\TextInput::make('quota')->label('Quota (If any)'),
                                    ])->columns(4),
                            ]),

                        // TAB 2: PARENTS INFO
                        Forms\Components\Tabs\Tab::make('Parents Info')
                            ->icon('heroicon-m-users')
                            ->schema([
                                Forms\Components\Section::make("Father's Information")
                                    ->schema([
                                        Forms\Components\TextInput::make('father_name')->label('Father\'s Name (English)'),
                                        Forms\Components\TextInput::make('father_name_bn')->label('Father\'s Name (Bangla)'),
                                        Forms\Components\TextInput::make('father_mobile')->label('Mobile No')->tel(),
                                        Forms\Components\TextInput::make('father_email')->label('Email')->email(),
                                        Forms\Components\TextInput::make('father_occupation')->label('Occupation'),
                                        Forms\Components\TextInput::make('father_nid')->label('National ID'),
                                    ])->columns(2),

                                Forms\Components\Section::make("Mother's Information")
                                    ->schema([
                                        Forms\Components\TextInput::make('mother_name')->label('Mother\'s Name (English)'),
                                        Forms\Components\TextInput::make('mother_name_bn')->label('Mother\'s Name (Bangla)'),
                                        Forms\Components\TextInput::make('mother_mobile')->label('Mobile No')->tel(),
                                        Forms\Components\TextInput::make('mother_email')->label('Email')->email(),
                                        Forms\Components\TextInput::make('mother_occupation')->label('Occupation'),
                                        Forms\Components\TextInput::make('mother_nid')->label('National ID'),
                                    ])->columns(2),
                            ]),

                        // TAB 3: ADDRESS & GUARDIAN
                        Forms\Components\Tabs\Tab::make('Guardian & Address')
                            ->icon('heroicon-m-home')
                            ->schema([
                                Forms\Components\Section::make('Addresses')
                                    ->schema([
                                        Forms\Components\Textarea::make('present_address')->label('Present Address (English)')->rows(3),
                                        Forms\Components\Textarea::make('present_address_bn')->label('Present Address (Bangla)')->rows(3),
                                        Forms\Components\Textarea::make('permanent_address')->label('Permanent Address (English)')->rows(3),
                                        Forms\Components\Textarea::make('permanent_address_bn')->label('Permanent Address (Bangla)')->rows(3),
                                    ])->columns(2),

                                Forms\Components\Section::make('Local Guardian (If Needed)')
                                    ->schema([
                                        Forms\Components\Select::make('current_guardian')->options(['Father' => 'Father', 'Mother' => 'Mother', 'Local Guardian' => 'Local Guardian'])->label('Current Guardian Type'),
                                        Forms\Components\TextInput::make('local_guardian_name')->label('Full Name'),
                                        Forms\Components\TextInput::make('local_guardian_mobile')->label('Mobile No')->tel(),
                                        Forms\Components\TextInput::make('local_guardian_email')->label('Email')->email(),
                                        Forms\Components\TextInput::make('local_guardian_occupation')->label('Occupation'),
                                        Forms\Components\TextInput::make('local_guardian_relation')->label('Relation'),
                                    ])->columns(3),
                            ]),

                        // 🌟 TAB 4: ACADEMIC HISTORY & NEW PLACEMENT
                        Forms\Components\Tabs\Tab::make('Academic Placement')
                            ->icon('heroicon-m-academic-cap')
                            ->schema([
                                Forms\Components\Section::make('Current Class Placement (Required for New Schools / Admissions)')
                                    ->description('Assign the student directly to a class so they show up in tabs and reports instantly.')
                                    ->schema([
                                        Forms\Components\Select::make('academic_year_id')
                                            ->label('Academic Year')
                                            ->options(\App\Models\AcademicYear::pluck('name', 'id'))
                                            ->default(fn () => \App\Models\AcademicYear::latest()->value('id'))
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->dehydrated(false),
                                            
                                        Forms\Components\Select::make('school_class_id')
                                            ->label('Class')
                                            ->options(\App\Models\SchoolClass::pluck('name', 'id'))
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->live()
                                            ->dehydrated(false),
                                            
                                        Forms\Components\Select::make('section_id')
                                            ->label('Section')
                                            ->options(function (Forms\Get $get) {
                                                $classId = $get('school_class_id');
                                                if (!$classId) return [];
                                                // Safely querying by class_id
                                                return \App\Models\Section::where('class_id', $classId)->pluck('name', 'id');
                                            })
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->dehydrated(false),
                                            
                                        Forms\Components\TextInput::make('roll_number')
                                            ->label('Roll Number')
                                            ->numeric()
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->dehydrated(false),

                                        // 🌟 ADDED: STUDY GROUP FIELD (Visible for senior classes)
                                        Forms\Components\Select::make('study_group')
                                            ->label('Study Group')
                                            ->options([
                                                'General' => 'General (Class 6-8)',
                                                'Science' => 'Science',
                                                'Arts/Humanities' => 'Arts/Humanities',
                                                'Commerce' => 'Commerce',
                                            ])
                                            ->default('General')
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->live()
                                            ->dehydrated(false),

                                        // 🌟 ADDED: OPTIONAL 4th SUBJECT FIELD (Loads dynamically based on chosen class)
                                        Forms\Components\Select::make('optional_subject_id')
                                            ->label('4th / Optional Subject')
                                            ->options(function (Forms\Get $get) {
                                                $classId = $get('school_class_id');
                                                if (!$classId) return [];

                                                return \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                                        $q->where('school_classes.id', $classId);
                                                    })
                                                    ->where(fn($q) => $q->where('subject_type', 'Optional')->orWhere('type', 'like', '%Optional%'))
                                                    ->get()
                                                    ->mapWithKeys(fn($sub) => [$sub->id => "{$sub->name} ({$sub->code})"]);
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->dehydrated(false),
                                    ])
                                    ->columns(3) // Fits fields cleanly
                                    ->visible(fn (string $operation): bool => $operation === 'create'),

                                Forms\Components\Section::make('Previous Academic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('previous_exam_name')->label('Exam Name'),
                                        Forms\Components\TextInput::make('previous_passing_year')->label('Passing Year'),
                                        Forms\Components\TextInput::make('previous_institution')->label('Institution'),
                                        Forms\Components\TextInput::make('previous_gpa')->label('GPA/Marks'),
                                        Forms\Components\TextInput::make('previous_board')->label('Board'),
                                    ])->columns(3),
                            ]),
                    ])
                    ->columnSpanFull()
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Personal Details')
                    ->schema([
                        Infolists\Components\ImageEntry::make('avatar')
                            ->hiddenLabel() 
                            ->circular()    
                            ->size(120)     
                            ->columnSpanFull() 
                            ->alignCenter(),

                        Infolists\Components\TextEntry::make('student_id')
                            ->label('Student ID')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Full Name')
                            ->html()
                            ->formatStateUsing(function ($record) {
                                $bangla = $record->name_bn ? "<br><span class='text-sm text-gray-500 dark:text-gray-400'>{$record->name_bn}</span>" : '';
                                return $record->name . $bangla;
                            }),
                        Infolists\Components\TextEntry::make('email')->label('Email Address'),
                        Infolists\Components\TextEntry::make('dob')->label('Date of Birth')->date('d M Y'),
                        Infolists\Components\TextEntry::make('gender'),
                        Infolists\Components\TextEntry::make('blood_group')->label('Blood Group')->badge(),
                        Infolists\Components\TextEntry::make('religion'),
                    ])->columns(3),

                Infolists\Components\Section::make('Family Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('father_name')
                            ->label("Father's Name")
                            ->html()
                            ->formatStateUsing(function ($record) {
                                $bangla = $record->father_name_bn ? "<br><span class='text-sm text-gray-500 dark:text-gray-400'>{$record->father_name_bn}</span>" : '';
                                return $record->father_name . $bangla;
                            }),
                        Infolists\Components\TextEntry::make('father_mobile')->label("Father's Mobile"),
                        
                        Infolists\Components\TextEntry::make('mother_name')
                            ->label("Mother's Name")
                            ->html()
                            ->formatStateUsing(function ($record) {
                                $bangla = $record->mother_name_bn ? "<br><span class='text-sm text-gray-500 dark:text-gray-400'>{$record->mother_name_bn}</span>" : '';
                                return $record->mother_name . $bangla;
                            }),
                        Infolists\Components\TextEntry::make('mother_mobile')->label("Mother's Mobile"),
                    ])->columns(2),

                Infolists\Components\Section::make('Academic Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('enrollments.schoolClass.name')->label('Enrolled Class')->badge(),
                        Infolists\Components\TextEntry::make('enrollments.studyGroup.name')->label('Study Group')->badge(),
                        Infolists\Components\TextEntry::make('enrollments.section.name')->label('Section')->badge()->default('N/A'),
                        Infolists\Components\TextEntry::make('enrollments.roll_number')->label('Roll No')->badge(),
                    ])->columns(4),
                    
                // --- FIXED CONTEXT-AWARE SMART ASSIGNED SUBJECTS PROFILE VIEW ---
                Infolists\Components\Section::make('Assigned Course Subjects')
                    ->schema([
                        Infolists\Components\TextEntry::make('assigned_subjects_list')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(function ($record) {
                                $enrollment = $record->enrollments()->latest()->first();
                                if (! $enrollment) {
                                    return "<span class='text-gray-400'>No active enrollment record found.</span>";
                                }

                                $classId = $enrollment->school_class_id;
                                $studyGroupId = $enrollment->study_group_id;
                                $optionalSubjectId = $enrollment->optional_subject_id;
                                
                                // 🌟 Grab the student's religion and make it lowercase for matching
                                $studentReligion = strtolower($record->religion ?? '');

                                // Fetch cleanly filtered subjects matching their stream choices
                                $subjects = \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($classId) {
                                        $q->where('school_classes.id', $classId);
                                    })
                                    ->where(function ($query) use ($studyGroupId, $optionalSubjectId) {
                                        $query->whereNull('study_group_id')
                                            ->where(fn($q) => $q->where('subject_type', 'Core')->orWhere('type', 'Core'))
                                            ->orWhere(function ($q) use ($optionalSubjectId) {
                                                $q->whereNull('study_group_id')
                                                    ->where(fn($e) => $e->where('subject_type', 'Optional')->orWhere('type', 'Optional'))
                                                    ->where('id', $optionalSubjectId);
                                            })
                                            ->orWhere(function ($q) use ($studyGroupId, $optionalSubjectId) {
                                                $q->where('study_group_id', $studyGroupId)
                                                    ->where(function ($subQ) use ($optionalSubjectId) {
                                                        $subQ->whereIn('subject_type', ['Group', 'Core'])
                                                            ->orWhereIn('type', ['Group', 'Core'])
                                                            ->orWhere('id', $optionalSubjectId);
                                                    });
                                            });
                                    })
                                    ->orderBy('code')
                                    ->get();

                                // 🌟 THE RELIGION FILTER: Hide religious subjects that do not match the student
                                $subjects = $subjects->filter(function ($subject) use ($studentReligion) {
                                    $name = strtolower($subject->name);
                                    
                                    $isIslamic = \Illuminate\Support\Str::contains($name, ['islam', 'ইসলাম']);
                                    $isHindu = \Illuminate\Support\Str::contains($name, ['hindu', 'হিন্দু']);
                                    $isChristian = \Illuminate\Support\Str::contains($name, ['christian', 'খ্রিষ্ট', 'খ্রিস্ট']);
                                    $isBuddhist = \Illuminate\Support\Str::contains($name, ['buddh', 'বৌদ্ধ']);

                                    if ($isIslamic && $studentReligion !== 'islam') return false;
                                    if ($isHindu && $studentReligion !== 'hinduism') return false;
                                    if ($isChristian && $studentReligion !== 'christianity') return false;
                                    if ($isBuddhist && $studentReligion !== 'buddhism') return false;

                                    return true; // Keep the subject if it passed the checks
                                });

                                if ($subjects->isEmpty()) {
                                    return "<span class='text-gray-400'>No subjects mapped matching this stream's criteria.</span>";
                                }

                                // Build the clean visual layout block
                                return $subjects->map(function ($subject) use ($optionalSubjectId) {
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
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student_id')
                    ->label('ID No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Student ID copied')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                // --- SMART CONTEXTUAL ACADEMIC YEAR COLUMN ---
                Tables\Columns\TextColumn::make('enrollments.academicYear.name')
                    ->label('Academic Year')
                    ->badge()
                    ->getStateUsing(function ($record, $livewire) {
                        $enrollments = $record->enrollments;
                        
                        $filters = $livewire->getTableFilterState('class_section_filter') ?? [];
                        if (!empty($filters['academic_year_id'])) {
                            $enrollments = $enrollments->where('academic_year_id', $filters['academic_year_id']);
                        }
                        if (!empty($filters['school_class_id'])) {
                            $enrollments = $enrollments->where('school_class_id', $filters['school_class_id']);
                        }
                        if (!empty($filters['section_id'])) {
                            $enrollments = $enrollments->where('section_id', $filters['section_id']);
                        }

                        $activeTab = $livewire->activeTab;
                        if ($activeTab && $activeTab !== 'all') {
                            $normalizedTab = strtolower(str_replace(['-', '_'], ' ', $activeTab));
                            $filtered = $enrollments->filter(function($e) use ($normalizedTab) {
                                $className = strtolower($e->schoolClass->name ?? '');
                                return $className && (str_contains($normalizedTab, $className) || str_contains($className, $normalizedTab));
                            });
                            if ($filtered->isNotEmpty()) {
                                $enrollments = $filtered;
                            }
                        }

                        $years = $enrollments->pluck('academicYear.name')->filter()->unique()->toArray();
                        return empty($years) ? ['N/A'] : $years;
                    }),

                Tables\Columns\TextColumn::make('enrollments.schoolClass.name')
                    ->label('Enrolled Class')
                    ->badge()
                    ->getStateUsing(function ($record, $livewire) {
                        $enrollments = $record->enrollments;
                        
                        $filters = $livewire->getTableFilterState('class_section_filter') ?? [];
                        if (!empty($filters['academic_year_id'])) {
                            $enrollments = $enrollments->where('academic_year_id', $filters['academic_year_id']);
                        }
                        if (!empty($filters['school_class_id'])) {
                            $enrollments = $enrollments->where('school_class_id', $filters['school_class_id']);
                        }
                        if (!empty($filters['section_id'])) {
                            $enrollments = $enrollments->where('section_id', $filters['section_id']);
                        }

                        $activeTab = $livewire->activeTab;
                        if ($activeTab && $activeTab !== 'all') {
                            $normalizedTab = strtolower(str_replace(['-', '_'], ' ', $activeTab));
                            $filtered = $enrollments->filter(function($e) use ($normalizedTab) {
                                $className = strtolower($e->schoolClass->name ?? '');
                                return $className && (str_contains($normalizedTab, $className) || str_contains($className, $normalizedTab));
                            });
                            if ($filtered->isNotEmpty()) {
                                $enrollments = $filtered;
                            }
                        }

                        $classes = $enrollments->pluck('schoolClass.name')->filter()->unique()->toArray();
                        return empty($classes) ? ['N/A'] : $classes;
                    }),

                Tables\Columns\TextColumn::make('enrollments.section.name')
                    ->label('Section')
                    ->badge()
                    ->getStateUsing(function ($record, $livewire) {
                        $enrollments = $record->enrollments;
                        
                        $filters = $livewire->getTableFilterState('class_section_filter') ?? [];
                        if (!empty($filters['academic_year_id'])) {
                            $enrollments = $enrollments->where('academic_year_id', $filters['academic_year_id']);
                        }
                        if (!empty($filters['school_class_id'])) {
                            $enrollments = $enrollments->where('school_class_id', $filters['school_class_id']);
                        }
                        if (!empty($filters['section_id'])) {
                            $enrollments = $enrollments->where('section_id', $filters['section_id']);
                        }

                        $activeTab = $livewire->activeTab;
                        if ($activeTab && $activeTab !== 'all') {
                            $normalizedTab = strtolower(str_replace(['-', '_'], ' ', $activeTab));
                            $filtered = $enrollments->filter(function($e) use ($normalizedTab) {
                                $className = strtolower($e->schoolClass->name ?? '');
                                return $className && (str_contains($normalizedTab, $className) || str_contains($className, $normalizedTab));
                            });
                            if ($filtered->isNotEmpty()) {
                                $enrollments = $filtered;
                            }
                        }

                        $sections = $enrollments->pluck('section.name')->filter()->unique()->toArray();
                        return empty($sections) ? ['N/A'] : $sections;
                    }),
            ])
            ->recordAction(Tables\Actions\ViewAction::class) 
            ->filters([
                Tables\Filters\Filter::make('class_section_filter')
                    ->form([
                        Forms\Components\Select::make('academic_year_id')
                            ->label('Filter by Year')
                            ->options(\App\Models\AcademicYear::pluck('name', 'id')),
                            
                        Forms\Components\Select::make('school_class_id')
                            ->label('Filter by Class')
                            ->options(\App\Models\SchoolClass::pluck('name', 'id')),
                            
                        Forms\Components\Select::make('section_id')
                            ->label('Filter by Section')
                            ->options(function () {
                                $options = [];
                                
                                // Query from SchoolClass since we know the 'sections' relation exists 🌟
                                foreach (\App\Models\SchoolClass::with('sections')->get() as $schoolClass) {
                                    foreach ($schoolClass->sections as $section) {
                                        $options[$section->id] = "{$schoolClass->name} - {$section->name}";
                                    }
                                }
                                
                                return $options;
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['academic_year_id'] || $data['school_class_id'] || $data['section_id'],
                            function (Builder $query) use ($data) {
                                $query->whereHas('enrollments', function (Builder $query) use ($data) {
                                    if ($data['academic_year_id']) {
                                        $query->where('academic_year_id', $data['academic_year_id']);
                                    }
                                    if ($data['school_class_id']) {
                                        $query->where('school_class_id', $data['school_class_id']);
                                    }
                                    if ($data['section_id']) {
                                        $query->where('section_id', $data['section_id']);
                                    }
                                });
                            }
                        );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), 
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
            'create' => Pages\CreateStudent::route('/create'), // Make sure you have this mapped!
        ];
    }
}