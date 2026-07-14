<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Enrollment;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function afterCreate(): void
    {
        $student = $this->record;
        $formData = $this->form->getRawState();

        // Create the enrollment row containing group and optional choices safely 🌟
        if ($student->type === 'student' && !empty($formData['school_class_id'])) {
            \App\Models\Enrollment::create([
                'user_id' => $student->id,
                'academic_year_id' => $formData['academic_year_id'],
                'school_class_id' => $formData['school_class_id'],
                'section_id' => $formData['section_id'] ?? null,
                'roll_number' => $formData['roll_number'],
                'study_group' => $formData['study_group'] ?? 'General',
                'optional_subject_id' => $formData['optional_subject_id'] ?? null,
                'status' => 'Active',
            ]);
        }
    }
}