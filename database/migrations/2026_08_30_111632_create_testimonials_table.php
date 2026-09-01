<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('testimonials', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('father_name');
        $table->string('mother_name');
        $table->string('registration_number');
        $table->string('roll_number');
        $table->string('school_name');
        $table->string('study_group');
        $table->string('exam_name');
        $table->string('result');
        $table->date('birth_date');
        $table->text('address');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
