<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Student Testimonial Information')
                    ->schema([
                        
                        // 🌟 THE SMART AUTOFILL DROPDOWN 🌟
                        Select::make('auto_fill_student')
                            ->label('🔍 Search Existing Student to Autofill')
                            ->searchable()
                            ->dehydrated(false) // Tells Laravel not to save this specific helper field to the database
                            ->options(function () {
                                return \App\Models\Enrollment::with('user', 'schoolClass')
                                    ->get()
                                    ->mapWithKeys(function ($enrollment) {
                                        $name = $enrollment->user->name ?? 'Unknown';
                                        $class = $enrollment->schoolClass->name ?? 'N/A';
                                        return [$enrollment->id => "{$name} - Roll: {$enrollment->roll_number} ({$class})"];
                                    })->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if (!$state) return;

                                $enrollment = \App\Models\Enrollment::with(['user', 'schoolClass'])->find($state);
                                
                                if ($enrollment && $enrollment->user) {
                                    $set('name', strtoupper($enrollment->user->name ?? ''));
                                    $set('father_name', strtoupper($enrollment->user->father_name ?? $enrollment->father_name ?? ''));
                                    $set('mother_name', strtoupper($enrollment->user->mother_name ?? $enrollment->mother_name ?? ''));
                                    $set('roll_number', $enrollment->roll_number);
                                    $set('study_group', $enrollment->study_group ?? 'General');
                                    
                                    $set('birth_date', $enrollment->user->date_of_birth ?? $enrollment->user->dob ?? null);
                                    
                                    // 🌟 ADDED THIS LINE FOR THE PERMANENT ADDRESS 🌟
                                    // Checks common column names like 'permanent_address' or 'permanent_address_en'
                                    $set('address', strtoupper($enrollment->user->permanent_address ?? $enrollment->user->permanent_address_en ?? $enrollment->user->address ?? ''));
                                    
                                    // $set('registration_number', $enrollment->user->registration_no ?? $enrollment->user->birth_reg_no ?? '');
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('serial_no')
                            ->label('Serial No.')
                            ->placeholder('e.g., TST-0001/2026')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name')
                            ->label('Student Name')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('father_name')
                            ->label('Father Name')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('mother_name')
                            ->label('Mother Name')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('birth_date')
                            ->label('Date of Birth')
                            ->displayFormat('d F, Y') 
                            ->required(),

                        TextInput::make('registration_number')
                            ->label('Registration No.')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('roll_number')
                            ->label('Roll No.')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('session')
                            ->label('Session')
                            ->placeholder('e.g., 2018-2019')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('school_name')
                            ->label('School Name')
                            ->default(function () {
                                $schoolName = 'KRISNAGOBINDAPUR HIGH SCHOOL'; 
                                if (class_exists('\App\Models\SiteSetting')) {
                                    $setting = \App\Models\SiteSetting::first();
                                    if ($setting) $schoolName = $setting->school_name_english ?? $setting->school_name ?? $setting->name ?? $schoolName;
                                } elseif (class_exists('\App\Models\Setting')) {
                                    $setting = \App\Models\Setting::first();
                                    if ($setting) $schoolName = $setting->school_name_english ?? $setting->school_name ?? $setting->name ?? $schoolName;
                                }
                                return strtoupper($schoolName);
                            })
                            ->required()
                            ->maxLength(255),

                        // 🌟 ADDED EDUCATION BOARD FIELD 🌟
                        TextInput::make('education_board')
                            ->label('Education Board')
                            ->default('Rajshahi')
                            ->required()
                            ->maxLength(255),
                            
                        Select::make('study_group')
                            ->label('Group')
                            ->options([
                                'Science' => 'Science',
                                'Arts / Humanities' => 'Arts / Humanities',
                                'Commerce' => 'Commerce',
                                'General' => 'General',
                            ])
                            ->required(),
                            
                        TextInput::make('exam_name')
                            ->label('Exam Name')
                            ->default('Secondary School Certificate Examination')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('result')
                            ->label('Result (GPA)')
                            ->required()
                            ->maxLength(255),
                            
                        Textarea::make('address')
                            ->label('Permanent Address')
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('Village: Pathanpara, Post Office: Chapainawabganj...'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('roll_number')->searchable(),
                TextColumn::make('registration_number')->searchable(),
                TextColumn::make('exam_name')->searchable(),
                TextColumn::make('result')->label('GPA'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // You can add filters here later (e.g., filter by Year or Exam)
            ])
            ->headerActions([
                Tables\Actions\Action::make('print_batch')
                    ->label('Print All (Batch)')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->action(function ($livewire) {
                        $ids = $livewire->getFilteredTableQuery()->pluck('id')->implode(',');
                        
                        if (empty($ids)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No testimonials found to print.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $url = route('print.testimonials', ['ids' => $ids]);
                        $livewire->js("window.open('{$url}', '_blank');");
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn ($record) => route('print.testimonials', ['ids' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('print_selected')
                        ->label('Print Selected')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(function (Collection $records, \Livewire\Component $livewire) {
                            $ids = $records->pluck('id')->implode(',');
                            $url = route('print.testimonials', ['ids' => $ids]);
                            $livewire->js("window.open('{$url}', '_blank');");
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}