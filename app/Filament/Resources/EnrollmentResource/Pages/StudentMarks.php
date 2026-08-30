<?php

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Notifications\Notification;

class StudentMarks extends Page
{
    protected static string $resource = EnrollmentResource::class;
    protected static string $view = 'filament.resources.enrollment-resource.pages.student-marks';

    public Enrollment $record;

    public function mount(Enrollment $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string | Htmlable
    {
        $studentId = $this->record->user->student_id ?? 'N/A';
        return "Marksheet: " . $this->record->user->name . " (ID: " . $studentId . ")";
    }

    public function printPdf($examId)
    {
        // Generates the URL for the single term marksheet
        $url = route('print.marksheet', [
            'enrollment' => $this->record->id,
            'exam' => $examId
        ]);

        // Tells Filament/Livewire to open this URL in a new tab
        $this->js("window.open('{$url}', '_blank');");
    }

    public function printFinalPdf()
    {
        // Generates the URL for the final cumulative marksheet
        $url = route('print.final.marksheet', [
            'id' => $this->record->id
        ]);

        // Tells Filament/Livewire to open this URL in a new tab
        $this->js("window.open('{$url}', '_blank');");
    }
}