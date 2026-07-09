<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            
            // Link to the user table for the teacher
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // CRITICAL: This ensures a Class & Section can only have ONE Class Teacher per year!
            $table->unique(['academic_year_id', 'school_class_id', 'section_id'], 'unique_class_teacher_per_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teachers');
    }
};