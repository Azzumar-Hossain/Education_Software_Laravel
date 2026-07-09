<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade the Subjects Table
        Schema::table('subjects', function (Blueprint $table) {
            // e.g., 'General' (for Class 1-8), 'Science', 'Arts', 'Commerce'
            $table->string('study_group')->default('General'); 
            // e.g., 'Core' (Bangla, Eng), 'Group' (Physics), 'Optional' (Higher Math)
            $table->string('subject_type')->default('Core'); 
        });

        // 2. Upgrade the Enrollments Table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('study_group')->default('General');
            // Links to the specific 4th subject the student chose
            $table->foreignId('optional_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['study_group', 'subject_type']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['optional_subject_id']);
            $table->dropColumn(['study_group', 'optional_subject_id']);
        });
    }
};
