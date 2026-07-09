<?php

namespace App\Filament\Resources\ClassTeacherResource\Pages;

use App\Filament\Resources\ClassTeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassTeacher extends EditRecord
{
    protected static string $resource = ClassTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
