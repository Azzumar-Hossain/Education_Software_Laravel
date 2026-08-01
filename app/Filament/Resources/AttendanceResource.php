<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\TeacherAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Daily Attendance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'name')
                    ->disabled(),
                Forms\Components\DatePicker::make('attendance_date')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'present' => 'Present',
                        'absent' => 'Absent',
                        'late' => 'Late',
                        'half_day' => 'Half Day',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('remarks'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attendance_date')
                    ->date()
                    ->sortable()
                    ->label('Date'),
                    
                Tables\Columns\TextColumn::make('student.name')
                    ->searchable()
                    ->sortable()
                    ->label('Student'),

                Tables\Columns\TextColumn::make('schoolClass.name')
                    ->sortable()
                    ->label('Class'),

                Tables\Columns\TextColumn::make('section.name')
                    ->sortable()
                    ->label('Section'),

                // INLINE EDITING FOR ATTENDANCE STATUS
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'present' => 'Present',
                        'absent' => 'Absent',
                        'late' => 'Late',
                        'half_day' => 'Half Day',
                    ])
                    ->selectablePlaceholder(false),

                Tables\Columns\TextInputColumn::make('remarks')
                    ->searchable(),
            ])
            ->defaultSort('attendance_date', 'desc')
            ->filters([
                // Quick filter by Date
                Tables\Filters\Filter::make('attendance_date')
                    ->form([
                        Forms\Components\DatePicker::make('date')->default(now())->label('Filter by Date'),
                        Forms\Components\Select::make('school_class_id')
                            ->label('Class')
                            ->options(function () {
                                $user = auth()->user();
                                if ($user && (strtolower($user->type) === 'teacher' || $user->hasRole('teacher'))) {
                                    $classIds = TeacherAllocation::where('user_id', $user->id)->pluck('school_class_id');
                                    return SchoolClass::whereIn('id', $classIds)->pluck('name', 'id');
                                }
                                return SchoolClass::pluck('name', 'id');
                            })
                            ->live(),
                        Forms\Components\Select::make('section_id')
                            ->label('Section')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];

                                $user = auth()->user();
                                $query = Section::where('school_class_id', $classId);

                                if ($user && (strtolower($user->type) === 'teacher' || $user->hasRole('teacher'))) {
                                    $sectionIds = TeacherAllocation::where('user_id', $user->id)
                                        ->where('school_class_id', $classId)
                                        ->whereNotNull('section_id')
                                        ->pluck('section_id');

                                    if ($sectionIds->isNotEmpty()) {
                                        $query->whereIn('id', $sectionIds);
                                    }
                                }

                                return $query->pluck('name', 'id');
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('attendance_date', '=', $date))
                            ->when($data['school_class_id'] ?? null, fn (Builder $q, $classId) => $q->where('school_class_id', $classId))
                            ->when($data['section_id'] ?? null, fn (Builder $q, $sectionId) => $q->where('section_id', $sectionId));
                    })
            ])
            ->headerActions([
                // GENERATE DAILY REGISTER ACTION
                Tables\Actions\Action::make('generate_register')
                    ->label('Generate Daily Register')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('school_class_id')
                            ->label('Class')
                            ->options(function () {
                                $user = auth()->user();
                                if ($user && (strtolower($user->type) === 'teacher' || $user->hasRole('teacher'))) {
                                    $classIds = TeacherAllocation::where('user_id', $user->id)->pluck('school_class_id');
                                    return SchoolClass::whereIn('id', $classIds)->pluck('name', 'id');
                                }
                                return SchoolClass::pluck('name', 'id');
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('section_id', null)),

                        Forms\Components\Select::make('section_id')
                            ->label('Section')
                            ->options(function (Forms\Get $get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];

                                $user = auth()->user();
                                $query = Section::where('school_class_id', $classId);

                                if ($user && (strtolower($user->type) === 'teacher' || $user->hasRole('teacher'))) {
                                    $sectionIds = TeacherAllocation::where('user_id', $user->id)
                                        ->where('school_class_id', $classId)
                                        ->whereNotNull('section_id')
                                        ->pluck('section_id');

                                    if ($sectionIds->isNotEmpty()) {
                                        $query->whereIn('id', $sectionIds);
                                    }
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->required(),

                        Forms\Components\DatePicker::make('attendance_date')
                            ->label('Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $enrollments = Enrollment::where('school_class_id', $data['school_class_id'])
                            ->where('section_id', $data['section_id'])
                            ->get();

                        if ($enrollments->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Enrolled Students Found!')
                                ->body('No students found for the selected Class and Section.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $createdCount = 0;
                        foreach ($enrollments as $enrollment) {
                            $attendance = Attendance::firstOrCreate([
                                'student_id' => $enrollment->user_id,
                                'attendance_date' => $data['attendance_date'],
                            ], [
                                'academic_year_id' => $enrollment->academic_year_id,
                                'school_class_id' => $data['school_class_id'],
                                'section_id' => $data['section_id'],
                                'status' => 'present', 
                            ]);

                            if ($attendance->wasRecentlyCreated) {
                                $createdCount++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Daily Register Generated!')
                            ->body("Generated register for {$createdCount} students.")
                            ->success()
                            ->send();
                    })
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // SCOPE QUERY: Restrict teachers to view only their assigned classes & sections
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && (strtolower($user->type) === 'teacher' || $user->hasRole('teacher'))) {
            $allocations = TeacherAllocation::where('user_id', $user->id)->get();

            $allocatedClassIds = $allocations->pluck('school_class_id')->unique()->filter();
            $allocatedSectionIds = $allocations->pluck('section_id')->unique()->filter();

            return $query->whereIn('school_class_id', $allocatedClassIds)
                         ->when($allocatedSectionIds->isNotEmpty(), function ($q) use ($allocatedSectionIds) {
                             return $q->whereIn('section_id', $allocatedSectionIds);
                         });
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
            'index' => Pages\ListAttendances::route('/'),
        ];
    }
}