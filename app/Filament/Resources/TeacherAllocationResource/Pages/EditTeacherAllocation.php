<?php

namespace App\Filament\Resources\TeacherAllocationResource\Pages;

use App\Filament\Resources\TeacherAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeacherAllocation extends EditRecord
{
    protected static string $resource = TeacherAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
