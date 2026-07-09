<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'academic_year_id',
        'school_class_id',
        'section_id',
        'roll_number',
        'study_group', 
        'optional_subject_id', 
        'status',
    ];

    // --- ADDED THIS MISSING RELATIONSHIP FOR FILAMENT ---
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // An enrollment belongs to a specific student (User)
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function optionalSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'optional_subject_id');
    }

    public function subjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'enrollment_subject')->withTimestamps();
    }
}