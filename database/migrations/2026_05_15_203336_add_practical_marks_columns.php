<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('practical_total')->nullable()->default(0);
            $table->integer('practical_pass_mark')->nullable()->default(0);
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->decimal('practical_mark', 8, 2)->nullable()->default(0)->after('mcq_mark');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['practical_total', 'practical_pass_mark']);
        });
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn('practical_mark');
        });
    }
};