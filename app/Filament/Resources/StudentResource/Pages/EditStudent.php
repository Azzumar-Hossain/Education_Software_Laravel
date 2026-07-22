<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\EditRecord;
use Livewire\WithFileUploads;

class EditStudent extends EditRecord
{
    use WithFileUploads;

    protected static string $resource = StudentResource::class;

    public $newPhoto;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->newPhoto) {
            $path = $this->newPhoto->store('student-photos', 'public');
            
            // Assign to both possible attribute names to guarantee view modal compatibility
            $data['avatar'] = $path;
            $data['profile_photo_path'] = $path;
            
            $this->newPhoto = null;
        }

        return $data;
    }
}