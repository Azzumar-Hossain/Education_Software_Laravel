<?php

namespace App\Imports;

use App\Models\Mark;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Models\GradeScale;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MarksImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (empty($row['student_id'])) {
            return null;
        }

        // 1. Get the Student
        $user = User::where('student_id', trim((string)$row['student_id']))->first();
        if (!$user) return null;

        // 2. Fetch Class from Student's Enrollment as an ultimate fallback
        $enrollment = Enrollment::where('user_id', $user->id)->latest()->first();
        $classId = !empty($row['school_class_id']) ? (int) $row['school_class_id'] : ($enrollment?->school_class_id);

        // 3. Resolve Exact IDs safely
        $examId = !empty($row['exam_id']) 
            ? (int) $row['exam_id'] 
            : Exam::where('name', trim($row['exam_name'] ?? ''))->where('school_class_id', $classId)->value('id');

        $subjectId = !empty($row['subject_id']) 
            ? (int) $row['subject_id'] 
            : Subject::where('name', trim($row['subject_name'] ?? ''))
                ->whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                ->value('id');

        if (!$examId || !$subjectId) return null;

        // 4. Calculate Marks
        $written   = is_numeric($row['written_mark'] ?? $row['written_marks'] ?? null) ? (float)($row['written_mark'] ?? $row['written_marks']) : 0;
        $mcq       = is_numeric($row['mcq_mark'] ?? $row['mcq_marks'] ?? null) ? (float)($row['mcq_mark'] ?? $row['mcq_marks']) : 0;
        $practical = is_numeric($row['practical_mark'] ?? $row['practical_marks'] ?? null) ? (float)($row['practical_mark'] ?? $row['practical_marks']) : 0;
        $total     = $written + $mcq + $practical;

        // 🌟 5. NEW COMPONENT-WISE FAIL LOGIC 🌟
        $subject = Subject::find($subjectId);
        $isComponentFailed = false;
        $percentage = $total; // Default

        if ($subject) {
            $writtenFailed   = ($subject->written_pass > 0) && ($written < $subject->written_pass);
            $mcqFailed       = ($subject->mcq_pass > 0) && ($mcq < $subject->mcq_pass);
            $practicalFailed = ($subject->practical_pass > 0) && ($practical < $subject->practical_pass);

            $isComponentFailed = !$subject->overall_pass_rule && ($writtenFailed || $mcqFailed || $practicalFailed);

            $maxPossible = $subject->written_total + $subject->mcq_total + $subject->practical_total;
            $percentage  = $maxPossible > 0 ? ($total / $maxPossible) * 100 : 0;
        }

        // Fetch securely from our updated Model
        $gradeData = GradeScale::getGradeForMark($percentage, $isComponentFailed);
        $grade = $gradeData['grade'];
        $gpa   = $gradeData['point'];

        // 6. MATCH EXACTLY LIKE THE GENERATOR DOES
        $mark = Mark::firstOrNew([
            'exam_id'    => $examId,
            'subject_id' => $subjectId,
            'student_id' => $user->id,
        ]);

        // 7. Update the fields on that exact row
        $mark->academic_year_id = !empty($row['academic_year_id']) ? (int)$row['academic_year_id'] : ($enrollment?->academic_year_id);
        $mark->school_class_id  = $classId;
        $mark->section_id       = !empty($row['section_id']) ? (int)$row['section_id'] : ($enrollment?->section_id);
        
        $mark->written_mark     = $written;
        $mark->mcq_mark         = $mcq;
        $mark->practical_mark   = $practical;
        $mark->marks_obtained   = $total;
        $mark->grade            = $grade;
        $mark->gpa              = $gpa;

        $mark->save();

        return $mark;
    }
}