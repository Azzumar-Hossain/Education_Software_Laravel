<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    // Add this line to allow Laravel to save the name!
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
    ];
}
