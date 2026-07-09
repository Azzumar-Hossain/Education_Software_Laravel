<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nick_name')->nullable();
            $table->string('nationality')->default('Bangladeshi')->nullable();
            $table->string('birth_reg_no')->nullable();
            $table->string('student_mobile_no')->nullable();
            $table->string('current_guardian')->nullable();
            $table->string('quota')->nullable();

            // Extra Father Details
            $table->string('father_mobile')->nullable();
            $table->string('father_email')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_nid')->nullable();
            $table->string('father_income')->nullable();

            // Extra Mother Details
            $table->string('mother_mobile')->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_nid')->nullable();
            $table->string('mother_income')->nullable();

            // Local Guardian Details
            $table->string('local_guardian_name')->nullable();
            $table->string('local_guardian_mobile')->nullable();
            $table->string('local_guardian_email')->nullable();
            $table->string('local_guardian_occupation')->nullable();
            $table->string('local_guardian_relation')->nullable();

            // Previous Academic Info
            $table->string('previous_exam_name')->nullable();
            $table->string('previous_passing_year')->nullable();
            $table->string('previous_institution')->nullable();
            $table->string('previous_gpa')->nullable();
            $table->string('previous_board')->nullable();
        });
    }

    public function down(): void
    {
        // For simplicity in this step, we leave down() empty or drop the columns
    }
};
