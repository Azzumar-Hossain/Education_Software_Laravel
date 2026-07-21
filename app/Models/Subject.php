<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'linked_subject_id',
        'code', 
        'combined_group',
        'study_group_id', 
        'subject_type',
        'written_total',
        'written_pass_mark',
        'mcq_total',
        'mcq_pass_mark',
        'practical_total',
        'practical_pass_mark',
        'overall_pass_only',  // 🌟 Make sure this is fillable
        'overall_pass_mark',  // 🌟 Make sure this is fillable
        'exam_overrides',
    ];

    protected $casts = [
        'overall_pass_only' => 'boolean',
        'overall_pass_mark' => 'integer',
        'exam_overrides' => 'array', // Tells Laravel this is a JSON array
    ];

    public function studyGroup() 
    {
        return $this->belongsTo(StudyGroup::class, 'study_group_id');
    }
    // UPDATED: Changed to studyGroups (plural) to match convention
    public function studyGroups(): BelongsToMany
    {
        return $this->belongsToMany(StudyGroup::class, 'group_subject');
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject');
    }
    public function linkedSubject()
    {
        return $this->belongsTo(Subject::class, 'linked_subject_id');
    }

    // Use this anywhere in your app to get the exact marks for a specific exam!
    public function getMarksForExam($examId)
    {
        $overrides = $this->exam_overrides ?? [];
        
        // 1. Check if there is a special rule for this specific exam
        foreach($overrides as $override) {
            if(isset($override['exam_id']) && $override['exam_id'] == $examId) {
                return [
                    'written_total' => $override['written_total'] ?? 0,
                    'mcq_total' => $override['mcq_total'] ?? 0,
                    'practical_total' => $override['practical_total'] ?? 0,
                    'full_marks' => ($override['written_total'] ?? 0) + ($override['mcq_total'] ?? 0) + ($override['practical_total'] ?? 0),
                ];
            }
        }
        
        // 2. If no special rule exists, return the Default standard marks!
        return [
            'written_total' => $this->written_total,
            'mcq_total' => $this->mcq_total,
            'practical_total' => $this->practical_total,
            'full_marks' => $this->written_total + $this->mcq_total + $this->practical_total,
        ];
    }

    public function getFullLabelAttribute()
    {
        // If the code is missing, just return the name. If it exists, return "Name (Code)"
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }

}