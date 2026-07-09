<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('receipt_items', function (Blueprint $table) {
        // Adds a nullable string column for the month
        $table->string('related_month')->nullable()->after('amount'); 
    });
}

public function down(): void
{
    Schema::table('receipt_items', function (Blueprint $table) {
        $table->dropColumn('related_month');
    });
}
};
