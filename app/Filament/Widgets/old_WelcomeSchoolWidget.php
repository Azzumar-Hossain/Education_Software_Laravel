<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class WelcomeSchoolWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-school-widget';
    
    protected static ?int $sort = -1; // <--- ADD THIS LINE (Forces it to the top!)
}
