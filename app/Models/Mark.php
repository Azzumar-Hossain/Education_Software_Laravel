<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    // Ensure these properties match your database columns map fillables array
    protected $fillable = [
        'academic_year_id', 'school_class_id', 'exam_id', 'section_id',
        'subject_id', 'student_id', 'written_mark', 'mcq_mark', 'practical_mark',
        'marks_obtained', 'grade', 'gpa'
    ];

    protected static function booted()
    {
        /**
         * 🌟 THE RECOMPILATION ENGINE
         * Runs calculations dynamically against custom Grade Scales database tables before saving.
         */
        static::saving(function (Mark $mark) {
            // 1. Calculate combined raw values safely
            $written   = (float) ($mark->written_mark ?? 0);
            $mcq       = (float) ($mark->mcq_mark ?? 0);
            $practical = (float) ($mark->practical_mark ?? 0);
            
            $mark->marks_obtained = $written + $mcq + $practical;

            // 2. Resolve subject configuration and pass limits
            $subject = $mark->subject;
            $totalPossibleMarks = 100; // Default baseline fallback

            $writtenPass   = $subject->written_pass_mark ?? 33;
            $mcqPass       = $subject->mcq_pass_mark ?? 0;
            $practicalPass = $subject->practical_pass_mark ?? 0;

            if ($subject && method_exists($subject, 'getMarksForExam')) {
                $examSettings = $subject->getMarksForExam($mark->exam_id);
                $wMax = $examSettings['written_total'] ?? 0;
                $mMax = $examSettings['mcq_total'] ?? 0;
                $pMax = $examSettings['practical_total'] ?? 0;
                $combinedMax = $wMax + $mMax + $pMax;
                
                if ($combinedMax > 0) {
                    $totalPossibleMarks = $combinedMax;
                }

                // If pass mark overrides exist in custom exam settings, use them
                if (isset($examSettings['written_pass_mark']))   $writtenPass   = $examSettings['written_pass_mark'];
                if (isset($examSettings['mcq_pass_mark']))       $mcqPass       = $examSettings['mcq_pass_mark'];
                if (isset($examSettings['practical_pass_mark'])) $practicalPass = $examSettings['practical_pass_mark'];
            }

            // 🌟 3. EVALUATE PASS / FAIL STATUS 🌟
            $isPassed = true;

            if ($subject && $subject->overall_pass_only) {
                // Combined Rule: Student passes if total marks >= overall pass mark
                $overallPassMark = $subject->overall_pass_mark ?? 33;
                $isPassed = ($mark->marks_obtained >= $overallPassMark);
            } else {
                // Strict Individual Rule: Check each paper component
                if ($written < $writtenPass) {
                    $isPassed = false;
                }
                if ($mcq < $mcqPass) {
                    $isPassed = false;
                }
                if ($practical < $practicalPass) {
                    $isPassed = false;
                }
            }

            // 4. Compute grade based on Pass/Fail outcome
            if (!$isPassed) {
                $mark->grade = 'F';
                $mark->gpa   = 0.00;
            } else {
                // Compute percentage value matching grade configuration rules
                $percentageObtained = ($mark->marks_obtained / $totalPossibleMarks) * 100;

                // Run the user-friendly custom database scale matcher
                $gradeDetails = \App\Models\GradeScale::getGradeForMark($percentageObtained);

                // Commit calculated properties to the object pipeline
                $mark->grade = $gradeDetails['grade'];
                $mark->gpa   = $gradeDetails['point'];
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}