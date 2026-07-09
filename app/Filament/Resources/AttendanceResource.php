<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Enrollment;
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

                // THIS IS THE INLINE EDITING MAGIC!
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
                // Adds a quick filter so teachers can just look at today's date
                Tables\Filters\Filter::make('attendance_date')
                    ->form([
                        Forms\Components\DatePicker::make('date')->default(now())->label('Filter by Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn (Builder $query, $date): Builder => $query->whereDate('attendance_date', '=', $date),
                        );
                    })
            ])
            ->headerActions([
                // THE BUTTON THAT GENERATES THE DAILY REGISTER
                Tables\Actions\Action::make('generate_register')
                    ->label('Generate Daily Register')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('school_class_id')
                            ->relationship('schoolClass', 'name')
                            ->label('Class')
                            ->required(),
                        Forms\Components\Select::make('section_id')
                            ->relationship('section', 'name')
                            ->label('Section')
                            ->required(),
                        Forms\Components\DatePicker::make('attendance_date')
                            ->label('Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // 1. Find all students enrolled in this specific class & section
                        $enrollments = Enrollment::where('school_class_id', $data['school_class_id'])
                            ->where('section_id', $data['section_id'])
                            ->get();

                        // 2. Loop through them and create a 'Present' record for today
                        foreach ($enrollments as $enrollment) {
                            Attendance::firstOrCreate([
                                'student_id' => $enrollment->user_id,
                                'attendance_date' => $data['attendance_date'],
                            ], [
                                'academic_year_id' => $enrollment->academic_year_id,
                                'school_class_id' => $data['school_class_id'],
                                'section_id' => $data['section_id'],
                                'status' => 'present', 
                            ]);
                        }
                    })
            ])
            ->actions([
                // Removing standard edit/delete actions to keep the interface clean
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ROLE BASED ACCESS: Ensure teachers only see their own students' attendance
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->type === 'teacher') {
            $allocatedClassIds = \App\Models\TeacherAllocation::where('user_id', $user->id)->pluck('school_class_id');
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
            'index' => Pages\ListAttendances::route('/'),
            // We don't need Create/Edit pages because we are doing everything on the Table!
        ];
    }
}