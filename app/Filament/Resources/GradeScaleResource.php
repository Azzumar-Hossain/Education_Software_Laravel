<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeScaleResource\Pages as GradeScalePages;
use App\Models\GradeScale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GradeScaleResource extends Resource
{
    protected static ?string $model = GradeScale::class;

    protected static ?string $navigationGroup = 'Exam'; 
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Grading System Settings';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Grade Scale Configuration') // 🌟 Updated Card to Section
                    ->schema([
                        Forms\Components\TextInput::make('letter_grade')
                            ->label('Letter Grade Name')
                            ->placeholder('e.g., A+, A, A-, F')
                            ->required(),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('min_mark')
                                ->label('Minimum Marks (%)')
                                ->numeric()
                                ->required(),

                            Forms\Components\TextInput::make('max_mark')
                                ->label('Maximum Marks (%)')
                                ->numeric()
                                ->required(),
                        ]),

                        Forms\Components\TextInput::make('grade_point')
                            ->label('Grade Point Value (GPA Value)')
                            ->numeric()
                            ->step('0.01') // Allows precise decimal inputs like 3.50
                            ->placeholder('e.g., 5.00, 4.00, 0.00')
                            ->required(),

                        Forms\Components\Toggle::make('is_fail_grade')
                            ->label('Mark as Failing Grade')
                            ->helperText('Enabling this option will automatically fail a student globally if they drop into this range.')
                            ->default(false),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('letter_grade')
                    ->label('Grade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'A+' => 'success',
                        'A', 'A-' => 'info',
                        'B', 'C', 'D' => 'warning',
                        'F' => 'danger',
                        default => 'primary',
                    }),
                    
                Tables\Columns\TextColumn::make('min_mark')
                    ->label('Min %')
                    ->fontFamily('mono')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('max_mark')
                    ->label('Max %')
                    ->fontFamily('mono')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('grade_point')
                    ->label('Grade Points (GPA)')
                    ->fontFamily('mono')
                    ->numeric(2) // 🌟 Ensures formatted output (e.g. 5.00, 3.50)
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\IconColumn::make('is_fail_grade')
                    ->label('Triggers Retained Status')
                    ->boolean(),
            ])
            ->defaultSort('min_mark', 'desc') // Sorts from A+ (80%) down to F (0%)
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => GradeScalePages\ManageGradeScales::route('/'),
        ];
    }
}