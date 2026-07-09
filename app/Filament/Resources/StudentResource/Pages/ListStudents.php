<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SchoolClass;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // --- THE EXCEL IMPORT BUTTON ---
            \Filament\Actions\Action::make('import_students')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Upload Student Excel File')
                ->modalDescription('Upload an Excel file to automatically create users, enrollments, and assign subjects.')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('attachment')
                        ->label('Excel File (.xlsx)')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = $data['attachment'];
                    
                    \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\StudentsImport, $filePath, 'local');
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);

                    \Filament\Notifications\Notification::make()
                        ->title('Import Complete!')
                        ->body('All students have been successfully imported and enrolled.')
                        ->success()
                        ->send();
                }),

            // The default "New Student" button
            \Filament\Actions\CreateAction::make(),
        ];
    }

    // --- THE DYNAMIC TABS MAGIC ---
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All Students'),
        ];

        // 1. Fetch all classes from your database
        $classes = SchoolClass::all();

        // 2. Loop through them and create a tab for each one automatically!
        foreach ($classes as $schoolClass) {
            $tabs[$schoolClass->name] = Tab::make($schoolClass->name)
                ->modifyQueryUsing(function (Builder $query) use ($schoolClass) {
                    // Only show students who have an enrollment in this specific class
                    return $query->whereHas('enrollments', function (Builder $query) use ($schoolClass) {
                        $query->where('school_class_id', $schoolClass->id);
                    });
                });
        }

        return $tabs;
    }
}