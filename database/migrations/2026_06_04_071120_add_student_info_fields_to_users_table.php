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
    Schema::table('users', function (Blueprint $table) {
        
        // Personal Details
        if (!Schema::hasColumn('users', 'name_bn')) { $table->string('name_bn')->nullable(); }
        if (!Schema::hasColumn('users', 'nick_name')) { $table->string('nick_name')->nullable(); }
        if (!Schema::hasColumn('users', 'dob')) { $table->date('dob')->nullable(); }
        if (!Schema::hasColumn('users', 'gender')) { $table->string('gender')->nullable(); }
        if (!Schema::hasColumn('users', 'religion')) { $table->string('religion')->nullable(); }
        if (!Schema::hasColumn('users', 'blood_group')) { $table->string('blood_group')->nullable(); }
        if (!Schema::hasColumn('users', 'nationality')) { $table->string('nationality')->default('Bangladeshi'); }
        if (!Schema::hasColumn('users', 'birth_reg_no')) { $table->string('birth_reg_no')->nullable(); }
        if (!Schema::hasColumn('users', 'student_mobile_no')) { $table->string('student_mobile_no')->nullable(); }
        if (!Schema::hasColumn('users', 'quota')) { $table->string('quota')->nullable(); }

        // Father Info
        if (!Schema::hasColumn('users', 'father_name')) { $table->string('father_name')->nullable(); }
        if (!Schema::hasColumn('users', 'father_name_bn')) { $table->string('father_name_bn')->nullable(); }
        if (!Schema::hasColumn('users', 'father_mobile')) { $table->string('father_mobile')->nullable(); }
        if (!Schema::hasColumn('users', 'father_email')) { $table->string('father_email')->nullable(); }
        if (!Schema::hasColumn('users', 'father_occupation')) { $table->string('father_occupation')->nullable(); }
        if (!Schema::hasColumn('users', 'father_nid')) { $table->string('father_nid')->nullable(); }
        if (!Schema::hasColumn('users', 'father_income')) { $table->string('father_income')->nullable(); }

        // Mother Info
        if (!Schema::hasColumn('users', 'mother_name')) { $table->string('mother_name')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_name_bn')) { $table->string('mother_name_bn')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_mobile')) { $table->string('mother_mobile')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_email')) { $table->string('mother_email')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_occupation')) { $table->string('mother_occupation')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_nid')) { $table->string('mother_nid')->nullable(); }
        if (!Schema::hasColumn('users', 'mother_income')) { $table->string('mother_income')->nullable(); }

        // Address & Guardian
        if (!Schema::hasColumn('users', 'present_address')) { $table->text('present_address')->nullable(); }
        if (!Schema::hasColumn('users', 'present_address_bn')) { $table->text('present_address_bn')->nullable(); }
        if (!Schema::hasColumn('users', 'permanent_address')) { $table->text('permanent_address')->nullable(); }
        if (!Schema::hasColumn('users', 'permanent_address_bn')) { $table->text('permanent_address_bn')->nullable(); }
        if (!Schema::hasColumn('users', 'current_guardian')) { $table->string('current_guardian')->nullable(); }
        if (!Schema::hasColumn('users', 'local_guardian_name')) { $table->string('local_guardian_name')->nullable(); }
        if (!Schema::hasColumn('users', 'local_guardian_mobile')) { $table->string('local_guardian_mobile')->nullable(); }
        if (!Schema::hasColumn('users', 'local_guardian_email')) { $table->string('local_guardian_email')->nullable(); }
        if (!Schema::hasColumn('users', 'local_guardian_occupation')) { $table->string('local_guardian_occupation')->nullable(); }
        if (!Schema::hasColumn('users', 'local_guardian_relation')) { $table->string('local_guardian_relation')->nullable(); }

        // Academic History
        if (!Schema::hasColumn('users', 'previous_exam_name')) { $table->string('previous_exam_name')->nullable(); }
        if (!Schema::hasColumn('users', 'previous_passing_year')) { $table->string('previous_passing_year')->nullable(); }
        if (!Schema::hasColumn('users', 'previous_institution')) { $table->string('previous_institution')->nullable(); }
        if (!Schema::hasColumn('users', 'previous_gpa')) { $table->string('previous_gpa')->nullable(); }
        if (!Schema::hasColumn('users', 'previous_board')) { $table->string('previous_board')->nullable(); }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
