<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Forms\Get; 
use App\Models\User;
use App\Models\Enrollment;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudyGroup;
use App\Models\Subject;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class AdmitStudent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Admit Student';
    protected static ?string $title = 'Admit New Student';
    protected static string $view = 'filament.pages.admit-student';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Tabs::make('Admission Form')
                    ->tabs([
                        // TAB 1: STUDENT PERSONAL & ACADEMIC
                        Components\Tabs\Tab::make('Student Info')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Components\Section::make('System Account')
                                    ->schema([
                                        Components\FileUpload::make('avatar')
                                                ->image()
                                                ->avatar() // Makes it a nice circle preview
                                                ->directory('student-avatars') // Saves images in storage/app/public/student-avatars
                                                ->label('Student Photo')
                                                ->columnSpanFull() // Spans the full width at the top
                                                ->alignCenter(),

                                        Components\TextInput::make('name')->required()->label('Student Name (English)'),
                                        Components\TextInput::make('name_bn')->label('শিক্ষার্থীর নাম (বাংলায়)'),  
                                        Components\TextInput::make('email')->email()->required()->unique('users', 'email')->label('Login Email'),
                                        Components\TextInput::make('password')->password()->required()->revealable(),
                                    ])->columns(2), // 2 columns for side-by-side layout

                                Components\Section::make('Personal Details')
                                    ->schema([
                                        Components\DatePicker::make('dob')->label('Date of Birth')->displayFormat('d/m/Y'),
                                        Components\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']),
                                        Components\Select::make('religion')->options(['Islam' => 'Islam', 'Hinduism' => 'Hinduism', 'Christianity' => 'Christianity', 'Buddhism' => 'Buddhism', 'Other' => 'Other']),
                                        Components\Select::make('blood_group')->options(['A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'O+' => 'O+', 'O-' => 'O-', 'AB+' => 'AB+', 'AB-' => 'AB-']),
                                        Components\TextInput::make('nationality')->default('Bangladeshi'),
                                        Components\TextInput::make('birth_reg_no')->label('Birth Reg. No'),
                                        Components\TextInput::make('student_mobile_no')->label('Student Mobile No')->tel(),
                                        Components\TextInput::make('quota')->label('Quota (If any)'),
                                    ])->columns(4),

                                Components\Section::make('Enrollment (Current)')
                                    ->schema([
                                        Components\Select::make('academic_year_id')->options(AcademicYear::pluck('name', 'id'))->required()->label('Academic Year'),
                                        
                                        Components\Select::make('school_class_id')
                                            ->options(SchoolClass::pluck('name', 'id'))
                                            ->required()
                                            ->live()
                                            ->label('Class'),
                                            
                                        Components\Select::make('section_id')
                                            ->label('Section')
                                            ->options(function (Get $get) {
                                                $classId = $get('school_class_id');
                                                if (!$classId) return [];
                                                $class = SchoolClass::with('sections')->find($classId);
                                                return $class ? $class->sections->pluck('name', 'id') : [];
                                            })
                                            ->live()
                                            ->nullable(),
                                            
                                        Components\TextInput::make('roll_number')->label('Roll No'),
                                        
                                        Components\Select::make('study_group_id')
                                            ->label('Study Group')
                                            ->options(StudyGroup::pluck('name', 'id'))
                                            ->live(), 
                                            
                                        Components\Select::make('optional_subject_id')
                                            ->label('4th / Optional Subject')
                                            ->options(function (Get $get) {
                                                $groupId = $get('study_group_id');
                                                if (!$groupId) return [];
                                                
                                                return Subject::where('subject_type', 'Optional')
                                                    ->whereHas('studyGroups', function ($query) use ($groupId) {
                                                        $query->where('study_groups.id', $groupId);
                                                    })
                                                    ->pluck('name', 'id');
                                            })
                                            ->live()
                                            // --- NEW VISIBILITY LOGIC ---
                                            ->visible(function (Get $get) {
                                                $classId = $get('school_class_id');
                                                
                                                if (!$classId) {
                                                    return false; // Hide if no class is selected
                                                }

                                                // Find the selected class in the database
                                                $schoolClass = \App\Models\SchoolClass::find($classId);

                                                // Show only if the class name matches 9 or 10
                                                // (Added 'Class 09' and 'Class 9' just in case of typos in your DB)
                                                return $schoolClass && in_array($schoolClass->name, ['Class 09', 'Class 9', 'Class 10']);
                                            }),
                                    ])->columns(3),
                            ]),

                        // TAB 2: PARENTS INFORMATION
                        Components\Tabs\Tab::make('Parents Info')
                            ->icon('heroicon-m-users')
                            ->schema([
                                Components\Section::make("Father's Information")
                                    ->schema([
                                        Components\TextInput::make('father_name')->label('Father\'s Name (English)'),
                                        Components\TextInput::make('father_name_bn')->label('পিতার নাম (বাংলা)'),
                                        Components\TextInput::make('father_mobile')->label('Mobile No')->tel(),
                                        Components\TextInput::make('father_email')->label('Email')->email(),
                                        Components\TextInput::make('father_occupation')->label('Occupation'),
                                        Components\TextInput::make('father_nid')->label('National ID'),
                                    ])->columns(2), // English and Bangla side-by-side

                                Components\Section::make("Mother's Information")
                                    ->schema([
                                        Components\TextInput::make('mother_name')->label('Mother\'s Name (English)'),
                                        Components\TextInput::make('mother_name_bn')->label('মাতার নাম (বাংলা)'),
                                        Components\TextInput::make('mother_mobile')->label('Mobile No')->tel(),
                                        Components\TextInput::make('mother_email')->label('Email')->email(),
                                        Components\TextInput::make('mother_occupation')->label('Occupation'),
                                        Components\TextInput::make('mother_nid')->label('National ID'),
                                    ])->columns(2),
                            ]),

                        // TAB 3: ADDRESS & LOCAL GUARDIAN
                        Components\Tabs\Tab::make('Guardian & Address')
                            ->icon('heroicon-m-home')
                            ->schema([
                                Components\Section::make('Addresses')
                                    ->schema([
                                        Components\Textarea::make('present_address')->label('Present Address (English)')->rows(3),
                                        Components\Textarea::make('present_address_bn')->label('বর্তমান ঠিকানা (বাংলা)')->rows(3),
                                        
                                        Components\Textarea::make('permanent_address')->label('Permanent Address (English)')->rows(3),
                                        Components\Textarea::make('permanent_address_bn')->label('স্থায়ী ঠিকানা (বাংলা)')->rows(3),
                                    ])->columns(2),

                                Components\Section::make('Local Guardian (If Needed)')
                                    ->schema([
                                        Components\Select::make('current_guardian')->options(['Father' => 'Father', 'Mother' => 'Mother', 'Local Guardian' => 'Local Guardian'])->label('Current Guardian Type'),
                                        Components\TextInput::make('local_guardian_name')->label('Full Name'),
                                        Components\TextInput::make('local_guardian_mobile')->label('Mobile No')->tel(),
                                        Components\TextInput::make('local_guardian_email')->label('Email')->email(),
                                        Components\TextInput::make('local_guardian_occupation')->label('Occupation'),
                                        Components\TextInput::make('local_guardian_relation')->label('Relation'),
                                    ])->columns(3),
                            ]),

                        // TAB 4: PREVIOUS ACADEMIC INFO
                        Components\Tabs\Tab::make('Academic History')
                            ->icon('heroicon-m-academic-cap')
                            ->schema([
                                Components\Section::make('Previous Academic Information')
                                    ->schema([
                                        Components\TextInput::make('previous_exam_name')->label('Exam Name'),
                                        Components\TextInput::make('previous_passing_year')->label('Passing Year'),
                                        Components\TextInput::make('previous_institution')->label('Institution'),
                                        Components\TextInput::make('previous_gpa')->label('GPA/Marks'),
                                        Components\TextInput::make('previous_board')->label('Board'),
                                    ])->columns(3),
                            ]),
                    ])
                    ->columnSpanFull()
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // 1. Create the User account
        $userData = array_diff_key($data, array_flip([
            'academic_year_id', 'school_class_id', 'section_id', 'roll_number', 
            'study_group_id', 'optional_subject_id'
        ]));
        $userData['type'] = 'student';
        $userData['password'] = Hash::make($userData['password']);
        
        $user = User::create($userData);

        // 2. Create the Enrollment
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'academic_year_id' => $data['academic_year_id'],
            'school_class_id' => $data['school_class_id'],
            'section_id' => $data['section_id'],
            'roll_number' => $data['roll_number'] ?? null,
            'study_group_id' => $data['study_group_id'] ?? null,
            'optional_subject_id' => $data['optional_subject_id'] ?? null,
        ]);

        // 3. --- THE AUTOMATIC SUBJECT ASSIGNMENT MAGIC ---
        $schoolClass = SchoolClass::with('subjects')->find($data['school_class_id']);
        
        if ($schoolClass) {
            $subjectIdsToAssign = [];

            foreach ($schoolClass->subjects as $subject) {
                if ($subject->subject_type === 'Core') {
                    $subjectIdsToAssign[] = $subject->id;
                }
            }

            if (!empty($data['study_group_id'])) {
                $groupSubjects = Subject::whereHas('studyGroups', function ($query) use ($data) {
                    $query->where('study_groups.id', $data['study_group_id']);
                })->where('subject_type', '!=', 'Optional')->pluck('id')->toArray();
                
                $subjectIdsToAssign = array_merge($subjectIdsToAssign, $groupSubjects);
            }

            if (!empty($data['optional_subject_id'])) {
                $subjectIdsToAssign[] = $data['optional_subject_id'];
            }

            $enrollment->subjects()->attach(array_unique($subjectIdsToAssign));
        }

        Notification::make()
            ->title('Success!')
            ->body('Student successfully admitted and subjects assigned automatically.')
            ->success()
            ->send();

        $this->form->fill();
    }
}