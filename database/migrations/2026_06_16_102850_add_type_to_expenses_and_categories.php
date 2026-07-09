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
        // Add Type to Categories
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('type')->default('Expense')->after('id'); // 'Income' or 'Expense'
        });

        // Add Type to the actual transaction records
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('type')->default('Expense')->after('date'); 
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
