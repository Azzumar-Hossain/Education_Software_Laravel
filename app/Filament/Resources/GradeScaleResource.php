<?php

namespace App\Filament\Resources; // 🌟 FIXED: Changed namespace from ...\Pages to the correct parent folder

use App\Filament\Resources\GradeScaleResource\Pages as GradeScalePages; // Unique alias to prevent duplicate name crashes
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
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('letter_grade')
                        ->label('Letter Grade Name')
                        ->placeholder('e.g., A+, A, A-, Fail')
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
                        ->placeholder('e.g., 5.00, 4.00, 0.00')
                        ->required(),
                    Forms\Components\Toggle::make('is_fail_grade')
                        ->label('Mark as Failing Grade')
                        ->helperText('Enabling this option will automatically fail a student globally if they drop into this range.'),
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
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('min_mark')
                    ->label('Min %')
                    ->fontFamily('mono'), // 🌟 FIXED: Changed from ->fontMono()
                    
                Tables\Columns\TextColumn::make('max_mark')
                    ->label('Max %')
                    ->fontFamily('mono'), // 🌟 FIXED: Changed from ->fontMono()
                    
                Tables\Columns\TextColumn::make('grade_point')
                    ->label('Grade Points (GPA)')
                    ->fontFamily('mono') // 🌟 FIXED: Changed from ->fontMono()
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\IconColumn::make('is_fail_grade')
                    ->label('Triggers Retained Status')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            // 🌟 FIXED: Points to the unique alias target map cleanly
            'index' => GradeScalePages\ManageGradeScales::route('/'),
        ];
    }
}