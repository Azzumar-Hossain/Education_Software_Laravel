<?php

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
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

    // --- UPDATED: Appended the Student ID to the Web Page Title! ---
    public function getTitle(): string | Htmlable
    {
        $studentId = $this->record->user->student_id ?? 'N/A';
        return "Marksheet: " . $this->record->user->name . " (ID: " . $studentId . ")";
    }

    // Handles printing an individual term
    public function printPdf($examId)
    {
        $exam = Exam::find($examId);
        $marks = Mark::with('subject')
            ->where('student_id', $this->record->user_id)
            ->where('academic_year_id', $this->record->academic_year_id)
            ->where('school_class_id', $this->record->school_class_id)
            ->where('exam_id', $examId)
            ->get();

        if ($marks->isEmpty()) {
            Notification::make()->title('No Marks Found')->warning()->send();
            return;
        }

        $pdf = Pdf::loadView('pdf.marksheet', [
            'enrollment' => $this->record,
            'marks' => $marks,
            'exam' => $exam,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "marksheet-{$this->record->roll_number}-{$exam->name}.pdf"
        );
    }

    // Handles combining all terms into one Final PDF!
    public function printFinalPdf()
    {
        $allMarks = Mark::with(['subject', 'exam'])
            ->where('student_id', $this->record->user_id)
            ->where('academic_year_id', $this->record->academic_year_id)
            ->where('school_class_id', $this->record->school_class_id)
            ->get();

        if ($allMarks->isEmpty()) {
            Notification::make()->title('No Marks Found')->warning()->send();
            return;
        }

        $pdf = Pdf::loadView('pdf.final-marksheet', [
            'enrollment' => $this->record,
            'allMarks' => $allMarks,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "final-cumulative-marksheet-{$this->record->roll_number}.pdf"
        );
    }
}