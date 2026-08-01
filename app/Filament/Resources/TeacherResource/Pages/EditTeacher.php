<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Livewire\WithFileUploads;

class EditTeacher extends EditRecord
{
    use WithFileUploads;

    protected static string $resource = TeacherResource::class;

    public $newPhoto;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->newPhoto) {
            $data['avatar'] = $this->newPhoto->store('teachers/avatars', 'public');
        }

        return $data;
    }
}