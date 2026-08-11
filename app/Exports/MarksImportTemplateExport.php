<?php

namespace App\Exports;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\AcademicYear;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;

class MarksImportTemplateExport implements FromCollection, WithHeadings, WithTitle, WithMapping
{
    protected int $yearId;
    protected int $classId;
    protected ?int $sectionId;
    protected int $examId;
    protected int $subjectId;

    protected string $yearName;
    protected string $className;
    protected string $sectionName;
    protected string $examName;
    protected string $subjectName;

    public function __construct(int $yearId, int $classId, ?int $sectionId, int $examId, int $subjectId)
    {
        $this->yearId    = $yearId;
        $this->classId   = $classId;
        $this->sectionId = $sectionId;
        $this->examId    = $examId;
        $this->subjectId = $subjectId;

        // Fetch clear human-readable names
        $this->yearName    = AcademicYear::find($yearId)?->name ?? '';
        $this->className   = SchoolClass::find($classId)?->name ?? '';
        $this->sectionName = $sectionId ? (Section::find($sectionId)?->name ?? '') : '';
        $this->examName    = Exam::find($examId)?->name ?? '';
        $this->subjectName = Subject::find($subjectId)?->name ?? '';
    }

    public function title(): string
    {
        return 'Marks Entry Template';
    }

    public function headings(): array
    {
        return [
            'roll_number',
            'student_id',
            'student_name',
            'written_marks',
            'mcq_marks',
            'practical_marks',
            'exam_name',
            'subject_name',
            'academic_year',
            'class_name',
            'section_name',
        ];
    }

    public function collection()
    {
        $query = Enrollment::with('user')
            ->where('academic_year_id', $this->yearId)
            ->where('school_class_id', $this->classId);

        if ($this->sectionId) {
            $query->where('section_id', $this->sectionId);
        }

        $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

        // 🌟 APPLY RELIGION FILTER FOR TEMPLATE GENERATION
        $subNameLower = strtolower($this->subjectName);
        $isReligionSubject = str_contains($subNameLower, 'islam') || str_contains($subNameLower, 'hindu') || str_contains($subNameLower, 'christian') || str_contains($subNameLower, 'buddhi');

        if ($isReligionSubject) {
            return $enrollments->filter(function ($enrollment) use ($subNameLower) {
                $studentRel = strtolower(trim($enrollment->user->religion ?? ''));
                
                // Mismatch rules
                if (str_contains($subNameLower, 'islam') && $studentRel !== 'islam') return false;
                if (str_contains($subNameLower, 'hindu') && $studentRel !== 'hinduism' && $studentRel !== 'hindu') return false;
                if (str_contains($subNameLower, 'christian') && $studentRel !== 'christianity' && $studentRel !== 'christian') return false;
                if (str_contains($subNameLower, 'buddhi') && $studentRel !== 'buddhism' && $studentRel !== 'buddhi') return false;
                
                return true; // Keep student if religion matches the subject
            })->values(); // Reset array keys
        }

        return $enrollments;
    }

    public function map($enrollment): array
    {
        return [
            $enrollment->roll_number,
            $enrollment->user->student_id ?? 'N/A',
            $enrollment->user->name ?? 'N/A',
            '', // Written marks input
            '', // MCQ marks input
            '', // Practical marks input
            $this->examName,
            $this->subjectName,
            $this->yearName,
            $this->className,
            $this->sectionName,
        ];
    }
}