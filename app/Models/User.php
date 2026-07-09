<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // 1. IMPORTANT: Import the trait

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; // 2. IMPORTANT: Use the trait here

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'name_bn', 'avatar', 'email', 'password', 'type', 'nick_name',
        'father_name', 'father_name_bn', 'mother_name', 'mother_name_bn',
        'present_address', 'present_address_bn', 'permanent_address', 'permanent_address_bn',
        
        // --- NEWLY ADDED FIELDS ---
        'student_id', // <-- ADDED HERE so Filament can save it!
        'dob', 'gender', 'blood_group', 'religion', 'nationality', 'birth_reg_no', 'student_mobile_no', 'current_guardian', 'quota',
        'father_mobile', 'father_email', 'father_occupation', 'father_nid', 'father_income',
        'mother_mobile', 'mother_email', 'mother_occupation', 'mother_nid', 'mother_income',
        'local_guardian_name', 'local_guardian_mobile', 'local_guardian_email', 'local_guardian_occupation', 'local_guardian_relation',
        'previous_exam_name', 'previous_passing_year', 'previous_institution', 'previous_gpa', 'previous_board',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- NEW: AUTO GENERATE STUDENT ID ---
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Check if the user being created is a student.
            // (Adjust 'student' if your type string is different, e.g., 'Student')
            if (strtolower($user->type) === 'student') {
                
                // Only generate if they don't already have one
                if (empty($user->student_id)) {
                    $year = date('Y');
                    
                    // Find the highest student ID issued THIS year
                    $lastUser = self::where('student_id', 'like', $year . '%')
                                       ->orderBy('student_id', 'desc')
                                       ->first();

                    if ($lastUser) {
                        // Get the last 4 digits and add 1
                        $sequence = intval(substr($lastUser->student_id, 4)) + 1;
                    } else {
                        // First student of the year!
                        $sequence = 1;
                    }

                    // Pad with zeros (e.g., 20260001)
                    $user->student_id = $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
                }
            }
        });
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teacherAllocations()
    {
        return $this->hasMany(TeacherAllocation::class);
    }

    public function latestEnrollment()
    {
        // Grabs the most recently created enrollment for this student
        return $this->hasOne(\App\Models\Enrollment::class)->latestOfMany();
    }
}