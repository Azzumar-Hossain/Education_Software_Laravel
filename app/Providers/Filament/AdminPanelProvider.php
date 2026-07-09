<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Assets\Css;
use Filament\Navigation\NavigationGroup;
use App\Filament\Resources\GradeScaleResource; // 🌟 IMPORTED THE GRADE SCALE RESOURCE

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Using a closure here prevents the view from crashing 
            // the panel boot process if the database isn't ready
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('3rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            
            // 🌟 FORCE MANUAL RADAR TRACKING REGISTRATION FOR THE GRADING POLICY RESOURCE 🌟
            ->resources([
                GradeScaleResource::class,
            ])
            
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\AdmitCardGenerator::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets removed to keep dashboard clean
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class, // Crucial for preventing redirect loops
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->assets([
                Css::make('custom-print', public_path('css/custom-print.css')),
            ])
            ->navigationGroups([
                // 1. Clean label value for matching resources
                NavigationGroup::make()
                    ->label('Exam')
                    ->collapsible(true),

                // 2. Teacher Group sits in the middle
                NavigationGroup::make()
                    ->label('Teacher')
                    ->collapsible(true),

                // 3. Finance Group sits in the middle
                NavigationGroup::make()
                    ->label('Finance')
                    ->collapsible(true),

                // 4. Settings Group is pushed to the absolute bottom
                NavigationGroup::make()
                    ->label('Settings')
                    ->collapsible(true),
            ]);
    }
}