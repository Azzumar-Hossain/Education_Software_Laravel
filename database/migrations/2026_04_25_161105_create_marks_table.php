<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            
            // Tracking exactly where this mark belongs
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            
            // The actual score
            $table->decimal('marks_obtained', 5, 2)->default(0);
            
            $table->timestamps();

            // A student can only have ONE mark per subject per exam
            $table->unique(['exam_id', 'subject_id', 'student_id'], 'unique_student_subject_exam');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
