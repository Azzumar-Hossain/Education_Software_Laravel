<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class StudentsChart extends ChartWidget
{
    protected static ?string $heading = 'Student Growth (Demo)';
    
    // Sort property puts this underneath the stats cards
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // We are using some static numbers for the past months, 
        // and pulling the real data for the current month!
        $currentStudents = User::where('type', 'student')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Total Enrolled Students',
                    'data' => [0, 5, 12, 18, $currentStudents],
                    'fill' => 'start',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
        ];
    }

    protected function getType(): string
    {
        return 'line'; // You can change this to 'bar' or 'doughnut' later if you prefer!
    }
}