<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->string('grade')->nullable()->after('marks_obtained');
            $table->decimal('gpa', 3, 2)->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn(['grade', 'gpa']);
        });
    }
};