<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\WithFileUploads;

class CreateTeacher extends CreateRecord
{
    use WithFileUploads;

    protected static string $resource = TeacherResource::class;

    public $newPhoto;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->newPhoto) {
            $data['avatar'] = $this->newPhoto->store('teachers/avatars', 'public');
        }

        return $data;
    }
}