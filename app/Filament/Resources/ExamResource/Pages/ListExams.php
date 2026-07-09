<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SchoolClass;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    // --- THE DYNAMIC TABS MAGIC ---
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All Exams'),
        ];

        // 1. Fetch all classes from your database
        $classes = SchoolClass::all();

        // 2. Loop through them and create a tab for each one
        foreach ($classes as $schoolClass) {
            $tabs[$schoolClass->name] = Tab::make($schoolClass->name)
                ->modifyQueryUsing(function (Builder $query) use ($schoolClass) {
                    return $query->where('school_class_id', $schoolClass->id);
                });
        }

        // 3. A safety tab for your old exams that don't have a class yet
        $tabs['unassigned'] = Tab::make('Unassigned (Old Exams)')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->whereNull('school_class_id');
            });

        return $tabs;
    }
}