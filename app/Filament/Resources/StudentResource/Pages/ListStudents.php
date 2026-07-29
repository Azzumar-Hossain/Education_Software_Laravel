<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Exports\StudentImportTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // --- 1. THE DOWNLOAD TEMPLATE BUTTON ---
            Actions\Action::make('download_template')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->modalHeading('Download Import Template')
                ->modalDescription('Select Class and Section to generate an Excel template formatted for your import.')
                ->form([
                    Grid::make(2)->schema([
                        Select::make('school_class_id')
                            ->label('Select Class')
                            ->options(SchoolClass::pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('section_id', null)),

                        Select::make('section_id')
                            ->label('Select Section')
                            ->options(function ($get) {
                                $classId = $get('school_class_id');
                                if (!$classId) return [];

                                // Fetch sections associated with the selected class
                                return Section::whereHas('schoolClasses', function ($q) use ($classId) {
                                    $q->where('school_classes.id', $classId);
                                })->pluck('name', 'id');
                            })
                            ->placeholder('Select Section')
                            ->live(),
                    ]),
                ])
                ->action(function (array $data) {
                    $class = SchoolClass::find($data['school_class_id']);
                    $section = !empty($data['section_id']) ? Section::find($data['section_id']) : null;

                    $className = $class ? str_replace(' ', '_', $class->name) : 'Class';
                    $sectionName = $section ? '_' . $section->name : '';

                    $fileName = "Student_Import_Template_{$className}{$sectionName}.xlsx";

                    return Excel::download(
                        new StudentImportTemplateExport($data['school_class_id'], $data['section_id'] ?? null),
                        $fileName
                    );
                }),

            // --- 2. THE EXCEL IMPORT BUTTON ---
            Actions\Action::make('import_students')
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
                    
                    Excel::import(new \App\Imports\StudentsImport, $filePath, 'local');
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);

                    \Filament\Notifications\Notification::make()
                        ->title('Import Complete!')
                        ->body('All students have been successfully imported and enrolled.')
                        ->success()
                        ->send();
                }),

            // --- 3. THE DEFAULT "NEW STUDENT" BUTTON ---
            Actions\CreateAction::make(),
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