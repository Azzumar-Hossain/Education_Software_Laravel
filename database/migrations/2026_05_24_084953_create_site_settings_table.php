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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name_en')->nullable();
            $table->string('school_name_bn')->nullable(); // For "কৃষ্ণগোবিন্দপুর উচ্চ বিদ্যালয়"
            $table->string('address_en')->nullable();
            $table->string('address_bn')->nullable();     // For "ডাকঘর: রামচন্দ্রপুর..."
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();           // Stores the image path
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
