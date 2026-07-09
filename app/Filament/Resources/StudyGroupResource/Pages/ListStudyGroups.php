<?php

namespace App\Filament\Resources\StudyGroupResource\Pages;

use App\Filament\Resources\StudyGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudyGroups extends ListRecords
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
