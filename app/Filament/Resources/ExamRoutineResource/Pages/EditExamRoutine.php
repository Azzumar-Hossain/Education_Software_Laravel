<?php

namespace App\Filament\Resources\ExamRoutineResource\Pages;

use App\Filament\Resources\ExamRoutineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExamRoutine extends EditRecord
{
    protected static string $resource = ExamRoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
