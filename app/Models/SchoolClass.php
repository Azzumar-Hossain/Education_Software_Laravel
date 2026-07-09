<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SchoolClass extends Model
{
    protected $fillable = ['name', 'numeric_value'];

    // A Class has many Sections
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'class_section');
    }

    // A Class has many Subjects
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject');
    }
}