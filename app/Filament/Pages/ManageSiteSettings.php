<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ManageSiteSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $title = 'Site Settings';
    protected static string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Fetch the first setting row, or create an empty one
        $setting = SiteSetting::first();
        if ($setting) {
            $this->form->fill($setting->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('School Information')
                    ->schema([
                        Forms\Components\TextInput::make('school_name_en')
                            ->label('School Name (English)'),
                        Forms\Components\TextInput::make('school_name_bn')
                            ->label('School Name (Bengali)'),
                        Forms\Components\TextInput::make('address_en')
                            ->label('Address (English)'),
                        Forms\Components\TextInput::make('address_bn')
                            ->label('Address (Bengali)'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number'),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email(),
                    ])->columns(2),

                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('School Logo')
                            ->image()
                            ->directory('settings')
                            ->preserveFilenames(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = SiteSetting::first();

        if ($setting) {
            $setting->update($data);
        } else {
            SiteSetting::create($data);
        }

        Notification::make()
            ->success()
            ->title('Settings Saved!')
            ->send();
    }
}