<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    protected $fillable = [
        'letter_grade', 
        'min_mark', 
        'max_mark', 
        'grade_point', 
        'is_fail_grade'
    ];

    protected $casts = [
        'min_mark' => 'float',
        'max_mark' => 'float',
        'grade_point' => 'float',
        'is_fail_grade' => 'boolean',
    ];

    /**
     * 🌟 RESOLVES LETTER GRADE & GPA FROM DATABASE METRICS WITH COMPONENT PASS SAFEGUARD
     */
    public static function getGradeForMark(float $markPercentage, bool $isComponentFailed = false): array
    {
        // 1. 🌟 COMPONENT FAIL SAFEGUARD
        // If student failed Written, MCQ, or Practical individually -> HARD OVERRIDE TO 'F'
        if ($isComponentFailed) {
            $failScale = self::where('is_fail_grade', true)->first();

            return [
                'grade'   => $failScale?->letter_grade ?? 'F',
                'point'   => (float) ($failScale?->grade_point ?? 0.00),
                'is_fail' => true,
            ];
        }

        // 2. DYNAMIC LOOKUP FROM DATABASE
        $matched = self::where('min_mark', '<=', $markPercentage)
            ->where('max_mark', '>=', $markPercentage)
            ->first();

        if ($matched) {
            return [
                'grade'   => $matched->letter_grade,
                'point'   => (float) $matched->grade_point,
                'is_fail' => (bool) $matched->is_fail_grade,
            ];
        }

        // 3. SAFE FALLBACK (Standard Scale if GradeScale table is empty)
        if ($markPercentage >= 80) return ['grade' => 'A+', 'point' => 5.00, 'is_fail' => false];
        if ($markPercentage >= 70) return ['grade' => 'A',  'point' => 4.00, 'is_fail' => false];
        if ($markPercentage >= 60) return ['grade' => 'A-', 'point' => 3.50, 'is_fail' => false];
        if ($markPercentage >= 50) return ['grade' => 'B',  'point' => 3.00, 'is_fail' => false];
        if ($markPercentage >= 40) return ['grade' => 'C',  'point' => 2.00, 'is_fail' => false];
        if ($markPercentage >= 33) return ['grade' => 'D',  'point' => 1.00, 'is_fail' => false];

        return ['grade' => 'F', 'point' => 0.00, 'is_fail' => true];
    }
}