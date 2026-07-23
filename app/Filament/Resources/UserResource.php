<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // 🌟 MOVES "Users" UNDER THE "Settings" COLLAPSIBLE SIDEBAR GROUP
    protected static ?string $navigationGroup = 'Settings';

    // 🌟 (OPTIONAL) CONTROL ORDER WITHIN THE SETTINGS GROUP
    protected static ?int $navigationSort = 2;
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'School Admin',
                        'teacher' => 'Teacher',
                        'student' => 'Student',
                        'parent' => 'Parent',
                    ])
                    ->default('student')
                    ->live() // Added live() so the form updates instantly when changed
                    ->native(false),
                    
                // --- NEWLY ADDED: SMART STUDENT ID FIELD ---
                Forms\Components\TextInput::make('student_id')
                    ->label('Student ID (Auto-Generated)')
                    ->readOnly() // Prevents users from typing their own ID
                    ->visible(fn (Forms\Get $get) => $get('type') === 'student') // Only shows if 'student' is selected
                    ->helperText('The system will automatically generate this ID when the student is saved.'),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->revealable()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                    
                // --- NEWLY ADDED: STUDENT ID COLUMN ---
                Tables\Columns\TextColumn::make('student_id')
                    ->label('ID No.')
                    ->searchable()
                    ->sortable()
                    ->toggleable(), // Lets you hide it from the table view if you want
                    
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',  // Red
                        'admin' => 'warning',       // Orange
                        'teacher' => 'success',     // Green
                        'student' => 'info',        // Blue
                        'parent' => 'gray',         // Gray
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', Str::title($state))),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Only Super Admins and Admins can see this menu item
        return $user->type === 'super_admin' || $user->type === 'admin';
    }

    public static function getEloquentQuery(): Builder
    {
        // This forces the Users menu to ONLY show admins and super admins
        return parent::getEloquentQuery()->whereIn('type', ['admin', 'super_admin']);
    }
}