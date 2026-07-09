<?php

namespace App\Filament\Resources\GradeScaleResource\Pages;

use App\Filament\Resources\GradeScaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGradeScales extends ManageRecords
{
    protected static string $resource = GradeScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
