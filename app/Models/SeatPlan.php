<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatPlan extends Model
{
    /**
     * 🌟 THE FILLABLE MATRIX WHITELIST
     * Explicitly allows the Seat Plan Engine to save data parameters into these columns.
     */
    protected $fillable = [
        'academic_year_id',
        'exam_id',
        'room_number',
        'bench_number',
        'seat_position',
        'student_id',
        'school_class_id',
        'roll_number',
    ];

    /**
     * Get the academic year associated with this seat plan assignment.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the exam event associated with this seat plan assignment.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the student candidate assigned to this seat slip.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the class grade structure associated with this seat slip.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}