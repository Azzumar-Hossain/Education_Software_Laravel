<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('letter_grade')->unique(); // e.g., 'A+', 'A', 'F'
            $table->integer('min_mark');              // e.g., 80, 70, 0
            $table->integer('max_mark');              // e.g., 100, 79, 32
            $table->decimal('grade_point', 3, 2);     // e.g., 5.00, 4.00, 0.00
            $table->boolean('is_fail_grade')->default(false); // Flags if this grade triggers a global fail
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};