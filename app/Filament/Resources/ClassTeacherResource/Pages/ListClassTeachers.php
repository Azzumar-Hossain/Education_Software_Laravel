<?php

namespace App\Filament\Resources\ClassTeacherResource\Pages;

use App\Filament\Resources\ClassTeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassTeachers extends ListRecords
{
    protected static string $resource = ClassTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
