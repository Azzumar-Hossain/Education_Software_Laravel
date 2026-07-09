<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            // We specifically link this to the users table (where type = student)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); 
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            
            // Adding a roll number for the student in this specific class
            $table->string('roll_number')->nullable(); 
            $table->timestamps();
            
            // Ensure a student can't be enrolled in the same year twice
            $table->unique(['user_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};