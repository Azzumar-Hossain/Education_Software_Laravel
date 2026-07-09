<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Force check: If the column truly doesn't exist, create it!
        if (!Schema::hasColumn('exams', 'school_class_id')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // No down method needed for a safety patch
    }
};