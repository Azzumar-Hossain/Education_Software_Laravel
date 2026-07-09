<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_allocations', function (Blueprint $table) {
            $table->id();
            // We specifically link this to the users table (where type = teacher)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Ensure a teacher isn't assigned to the exact same class/section/subject twice
            $table->unique(['user_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id'], 'teacher_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_allocations');
    }
};
