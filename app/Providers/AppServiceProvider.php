<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🌟 1. GLOBAL SUPER ADMIN BYPASS 🌟
        // Automatically grants all permissions to super admins without needing hardcoded checks
        Gate::before(function ($user, $ability) {
            return ($user->type === 'super_admin' || $user->hasRole('super_admin')) ? true : null;
        });

        // 🌟 2. FILAMENT LANGUAGE SWITCH CONFIGURATION 🌟
        \BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch::configureUsing(function (\BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'bn']); // en = English, bn = Bangla
        });
    }
}