<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Ensures we don't accidentally assign the same subject twice to one enrollment
            $table->unique(['enrollment_id', 'subject_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_subject');
    }
};