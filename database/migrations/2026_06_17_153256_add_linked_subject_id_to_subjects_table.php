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
        Schema::table('subjects', function (Blueprint $table) {
            // This allows a subject to link to the ID of another subject
            $table->foreignId('linked_subject_id')
                  ->nullable()
                  ->constrained('subjects') // Points back to its own table!
                  ->nullOnDelete()
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['linked_subject_id']);
            $table->dropColumn('linked_subject_id');
        });
    }
};
