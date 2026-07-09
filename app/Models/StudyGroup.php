<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudyGroup extends Model
{
    use HasFactory;

    // This line tells Laravel that it is safe to save 'name' to the database
    protected $fillable = ['name'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'group_subject');
    }
    
}