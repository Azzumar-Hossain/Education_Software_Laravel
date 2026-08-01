<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

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
        'student_id',
        'dob', 'gender', 'blood_group', 'religion', 'nationality', 'birth_reg_no', 'student_mobile_no', 'current_guardian', 'quota',
        'father_mobile', 'father_email', 'father_occupation', 'father_nid', 'father_income',
        'mother_mobile', 'mother_email', 'mother_occupation', 'mother_nid', 'mother_income',
        'local_guardian_name', 'local_guardian_mobile', 'local_guardian_email', 'local_guardian_occupation', 'local_guardian_relation',
        'previous_exam_name', 'previous_passing_year', 'previous_institution', 'previous_gpa', 'previous_board',

        // --- NEW TEACHER FIELDS ---
        'nid',
        'designation', 'joining_date', 'index_number',
        'ssc_degree', 'ssc_passing_year', 'ssc_result', 'ssc_board',
        'hsc_degree', 'hsc_passing_year', 'hsc_result', 'hsc_board',
        'honors_degree', 'honors_subject', 'honors_passing_year', 'honors_result', 'honors_university',
        'masters_degree', 'masters_subject', 'masters_passing_year', 'masters_result', 'masters_university',
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

    // --- AUTO GENERATE STUDENT ID ---
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (strtolower($user->type) === 'student') {
                if (empty($user->student_id)) {
                    $year = date('Y');
                    
                    $lastUser = self::where('student_id', 'like', $year . '%')
                                       ->orderBy('student_id', 'desc')
                                       ->first();

                    if ($lastUser) {
                        $sequence = intval(substr($lastUser->student_id, 4)) + 1;
                    } else {
                        $sequence = 1;
                    }

                    $user->student_id = $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
                }
            }
        });
    }

    // --- RELATIONSHIPS ---

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teacherAllocations()
    {
        return $this->hasMany(TeacherAllocation::class, 'user_id');
    }

    public function classTeacherAllocations()
    {
        return $this->hasMany(ClassTeacher::class, 'user_id');
    }

    public function latestEnrollment()
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    // --- HELPER METHODS FOR TEACHER SCOPING ---

    /**
     * Get IDs of classes assigned to this teacher
     */
    public function getAllocatedClassIds(): array
    {
        return $this->teacherAllocations()->pluck('school_class_id')->unique()->toArray();
    }

    /**
     * Get IDs of subjects assigned to this teacher
     */
    public function getAllocatedSubjectIds(): array
    {
        return $this->teacherAllocations()->pluck('subject_id')->unique()->toArray();
    }
}