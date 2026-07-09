<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'school_class_id', // <-- Added this!
        'name',
        'start_date',
        'end_date',
        'parent_exam_id',
        'contribution_percentage',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // --- ADD THIS NEW RELATIONSHIP ---
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
    public function parentExam()
    {
        return $this->belongsTo(Exam::class, 'parent_exam_id');
    }

    // This tells Laravel that this exam might have multiple Child Exams (like 1st Mid, 2nd Mid)
    public function childExams()
    {
        return $this->hasMany(Exam::class, 'parent_exam_id');
    }
}