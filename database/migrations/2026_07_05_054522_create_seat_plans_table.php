<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');             // e.g., "Room 102", "Auditorium"
            $table->integer('bench_number');           // Bench sequential tracking index
            $table->integer('seat_position');          // Position index: 1, 2, or 3 on that specific bench
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete(); // The assigned candidate
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();   // Student's class level
            $table->string('roll_number');             // Fast print caching tracking property
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_plans');
    }
};