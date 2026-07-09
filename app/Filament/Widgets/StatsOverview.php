<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\SchoolClass;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // Adding a sort property ensures these cards stay at the very top of the dashboard
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Students', User::where('type', 'student')->count())
                ->description('Active students in the system')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
                
            Stat::make('Total Teachers', User::where('type', 'teacher')->count())
                ->description('Registered teaching staff')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),
                
            Stat::make('Total Classes', SchoolClass::count())
                ->description('Configured academic classes')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('warning'),
        ];
    }
}
