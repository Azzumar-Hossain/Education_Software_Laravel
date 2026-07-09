<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add rules to the Subjects table
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('written_total')->nullable()->default(70);
            $table->integer('written_pass_mark')->nullable()->default(23);
            $table->integer('mcq_total')->nullable()->default(30);
            $table->integer('mcq_pass_mark')->nullable()->default(10);
        });

        // 2. Add individual score boxes to the Marks table
        Schema::table('marks', function (Blueprint $table) {
            $table->decimal('written_mark', 8, 2)->nullable()->default(0)->after('student_id');
            $table->decimal('mcq_mark', 8, 2)->nullable()->default(0)->after('written_mark');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['written_total', 'written_pass_mark', 'mcq_total', 'mcq_pass_mark']);
        });
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn(['written_mark', 'mcq_mark']);
        });
    }
};