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
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // 🌟 MOVES "Users" UNDER THE "Settings" COLLAPSIBLE SIDEBAR GROUP
    protected static ?string $navigationGroup = 'Settings';

    // 🌟 CONTROL ORDER WITHIN THE SETTINGS GROUP
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Email Search & Select Field
                Forms\Components\Select::make('email')
                    ->label('Email Address')
                    ->options(User::pluck('email', 'email'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $user = User::where('email', $state)->first();
                            if ($user) {
                                $set('name', $user->name);
                                $set('type', $user->type);
                                
                                // Auto-sync Spatie role selection when user email is picked
                                $role = Role::where('name', $user->type)->first();
                                if ($role) {
                                    $set('roles', [$role->id]);
                                }
                            }
                        }
                    })
                    ->helperText('Type or select an existing email (e.g. Teacher email) to auto-fill details.'),

                // 2. Name Field (Auto-filled or manual)
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                // 3. Type Field (Auto-filled or manual)
                Forms\Components\Select::make('type')
                    ->label('User Type / Role')
                    ->required()
                    ->options(function () {
                        // Fetch all roles dynamically from database
                        return Role::pluck('name', 'name')
                            ->mapWithKeys(function ($name) {
                                // Formats 'class_teacher' into 'Class Teacher', 'super_admin' into 'Super Admin'
                                return [$name => Str::title(str_replace('_', ' ', $name))];
                            })
                            ->toArray();
                    })
                    ->default('teacher')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            // 🌟 Automatically update the Spatie Roles relation when type changes
                            $role = Role::where('name', $state)->first();
                            if ($role) {
                                $set('roles', [$role->id]);
                            }
                        }
                    })
                    ->searchable()
                    ->native(false),

                // 4. Assign Spatie Permissions / Roles
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Assign Permissions / Roles')
                    ->helperText('Select the dynamic Spatie role defining what parts of the system this user can view/edit.'),

                // 5. Student ID (Only visible if student)
                Forms\Components\TextInput::make('student_id')
                    ->label('Student ID (Auto-Generated)')
                    ->readOnly()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'student'),

                // 6. Password Field
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
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
                    
                // --- STUDENT ID COLUMN ---
                Tables\Columns\TextColumn::make('student_id')
                    ->label('ID No.')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin'   => 'danger',  // Red
                        'admin'         => 'warning', // Orange
                        'teacher'       => 'success', // Green
                        'class_teacher' => 'info',    // Cyan / Blue
                        'student'       => 'info',    // Blue
                        'parent'        => 'gray',    // Gray
                        default         => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::title(str_replace('_', ' ', $state))),

                // 🌟 DYNAMIC ROLES BADGES IN TABLE 🌟
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Assigned Roles')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => Str::title(str_replace('_', ' ', $state)))
                    ->separator(','),
                    
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Allow if user is super admin, admin, or has explicit role permission
        return $user->type === 'super_admin' 
            || $user->type === 'admin' 
            || $user->hasPermissionTo('view_any_user');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}