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
        Schema::table('exams', function (Blueprint $table) {
            // Points to the main exam (e.g., 1st Mid points to Mid Term)
            $table->foreignId('parent_exam_id')->nullable()->constrained('exams')->nullOnDelete();
            
            // How much % this exam contributes to the parent exam
            $table->decimal('contribution_percentage', 5, 2)->nullable(); 
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['parent_exam_id']);
            $table->dropColumn(['parent_exam_id', 'contribution_percentage']);
        });
    }
};
