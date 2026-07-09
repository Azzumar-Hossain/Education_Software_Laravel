<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // If the 'dob' column is missing, add all the Step 1 admission fields
            if (!Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable();
                $table->string('gender')->nullable();
                $table->string('religion')->nullable();
                $table->string('blood_group')->nullable();
                
                $table->string('father_name')->nullable();
                $table->string('mother_name')->nullable();
                $table->string('phone')->nullable();
                $table->text('present_address')->nullable();
                $table->text('permanent_address')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No down method needed for a safety patch
    }
};