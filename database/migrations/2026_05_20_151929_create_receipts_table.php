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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique(); // e.g., "15941"
            $table->date('receipt_date');
            
            // We link to Enrollment so we instantly know the Student, Class, Year, and Roll!
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            
            $table->string('paid_for_month')->nullable(); // e.g., "January"
            $table->string('paid_for_year')->nullable(); // e.g., "2026"
            
            $table->decimal('total_amount', 10, 2)->default(0.00);
            
            // Who collected the money? (The logged-in admin/teacher)
            $table->foreignId('collected_by')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
