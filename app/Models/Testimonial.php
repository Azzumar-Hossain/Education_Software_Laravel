<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'serial_no', 'name', 'father_name', 'mother_name', 'registration_number', 
        'roll_number', 'session', 'school_name', 'education_board', // 🌟 Added here
        'study_group', 'exam_name', 'result', 'birth_date', 'address'
    ];
}