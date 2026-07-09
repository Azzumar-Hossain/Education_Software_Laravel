<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // We make it nullable so your 5 existing exams don't break the database!
            $table->foreignId('school_class_id')->nullable()->constrained()->cascadeOnDelete()->after('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['school_class_id']);
            $table->dropColumn('school_class_id');
        });
    }
};