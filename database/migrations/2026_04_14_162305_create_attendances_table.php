<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            
            $table->date('attendance_date');
            
            // We use an ENUM for strict attendance statuses
            $table->enum('status', ['present', 'absent', 'late', 'half_day'])->default('present');
            
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Ensure a student only has ONE attendance record per day
            $table->unique(['student_id', 'attendance_date'], 'unique_student_attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
