<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;


class TeacherResource extends Resource
{
    // Tell Filament to use the User table
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Teacher';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Teacher List';
    protected static ?string $modelLabel = 'Teacher';
    protected static ?string $slug = 'teachers'; 

    // ONLY show teachers in this list
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'teacher');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. ACCOUNT CREDENTIALS
                Forms\Components\Section::make('Account Credentials')
                    ->collapsible()
                    ->schema([
                        Forms\Components\ViewField::make('avatar')
                            ->label('Teacher Photo')
                            ->view('filament.forms.components.teacher-photo-upload')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                            ->maxLength(255),

                        Forms\Components\Hidden::make('type')->default('teacher'),
                    ])->columns(3),
                    
                // 2. EMPLOYMENT DETAILS (NEW SECTION)
                Forms\Components\Section::make('Employment Details')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('designation')
                            ->label('Designation')
                            ->options([
                                'Headmaster' => 'Headmaster',
                                'Assistant Headmaster' => 'Assistant Headmaster',
                                'Senior Teacher' => 'Senior Teacher',
                                'Assistant Teacher' => 'Assistant Teacher',
                                'Junior Teacher' => 'Junior Teacher',
                                'Librarian' => 'Librarian',
                                'Other' => 'Other',
                            ])
                            ->searchable()
                            ->required(),

                        Forms\Components\DatePicker::make('joining_date')
                            ->label('Joined Date')
                            ->default(now()),

                        Forms\Components\TextInput::make('index_number')
                            ->label('Index No.')
                            ->placeholder('e.g., N-123456'),
                    ])->columns(3),

                // 3. PERSONAL INFORMATION
                Forms\Components\Section::make('Personal Information')
                    ->collapsible()
                    ->schema([
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth'),

                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ]),

                        Forms\Components\Select::make('religion')
                            ->label('Religion')
                            ->options([
                                'Islam' => 'Islam',
                                'Hinduism' => 'Hinduism',
                                'Christianity' => 'Christianity',
                                'Buddhism' => 'Buddhism',
                                'Other' => 'Other',
                            ]),

                        Forms\Components\Select::make('blood_group')
                            ->label('Blood Group')
                            ->options([
                                'A+' => 'A+', 'A-' => 'A-',
                                'B+' => 'B+', 'B-' => 'B-',
                                'O+' => 'O+', 'O-' => 'O-',
                                'AB+' => 'AB+', 'AB-' => 'AB-',
                            ]),

                        Forms\Components\TextInput::make('nationality')
                            ->label('Nationality')
                            ->default('Bangladeshi'),

                        Forms\Components\TextInput::make('nid')
                            ->label('NID / Smart Card No'),

                        Forms\Components\TextInput::make('father_name')
                            ->label("Father's Name (English)"),

                        Forms\Components\TextInput::make('mother_name')
                            ->label("Mother's Name (English)"),
                    ])->columns(3),

                // 4. EDUCATIONAL QUALIFICATIONS
                Forms\Components\Section::make('Educational Qualifications')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('SSC / Equivalent')
                            ->schema([
                                Forms\Components\TextInput::make('ssc_degree')->label('Exam Name')->placeholder('SSC / Dakhil'),
                                Forms\Components\TextInput::make('ssc_board')->label('Board'),
                                Forms\Components\TextInput::make('ssc_passing_year')->label('Passing Year')->numeric(),
                                Forms\Components\TextInput::make('ssc_result')->label('GPA / Result'),
                            ])->columns(4),

                        Forms\Components\Fieldset::make('HSC / Equivalent')
                            ->schema([
                                Forms\Components\TextInput::make('hsc_degree')->label('Exam Name')->placeholder('HSC / Alim'),
                                Forms\Components\TextInput::make('hsc_board')->label('Board'),
                                Forms\Components\TextInput::make('hsc_passing_year')->label('Passing Year')->numeric(),
                                Forms\Components\TextInput::make('hsc_result')->label('GPA / Result'),
                            ])->columns(4),

                        Forms\Components\Fieldset::make("Graduation (Honors / Bachelor's)")
                            ->schema([
                                Forms\Components\TextInput::make('honors_degree')->label('Degree Name')->placeholder('B.Sc / B.A / B.S.S'),
                                Forms\Components\TextInput::make('honors_subject')->label('Subject / Major'),
                                Forms\Components\TextInput::make('honors_university')->label('University / Institute'),
                                Forms\Components\TextInput::make('honors_passing_year')->label('Passing Year')->numeric(),
                                Forms\Components\TextInput::make('honors_result')->label('CGPA / Division'),
                            ])->columns(5),

                        Forms\Components\Fieldset::make("Post Graduation (Master's)")
                            ->schema([
                                Forms\Components\TextInput::make('masters_degree')->label('Degree Name')->placeholder('M.Sc / M.A / M.S.S'),
                                Forms\Components\TextInput::make('masters_subject')->label('Subject / Major'),
                                Forms\Components\TextInput::make('masters_university')->label('University / Institute'),
                                Forms\Components\TextInput::make('masters_passing_year')->label('Passing Year')->numeric(),
                                Forms\Components\TextInput::make('masters_result')->label('CGPA / Division'),
                            ])->columns(5),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}