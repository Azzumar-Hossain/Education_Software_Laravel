<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Employment Details
            $table->string('designation')->nullable()->after('type');
            $table->date('joining_date')->nullable()->after('designation');
            $table->string('index_number')->nullable()->after('joining_date');

            // Personal Information
            $table->string('nid')->nullable()->after('index_number');
            
            // Educational Qualifications
            $table->string('ssc_degree')->nullable();
            $table->string('ssc_passing_year')->nullable();
            $table->string('ssc_result')->nullable();
            $table->string('ssc_board')->nullable();

            $table->string('hsc_degree')->nullable();
            $table->string('hsc_passing_year')->nullable();
            $table->string('hsc_result')->nullable();
            $table->string('hsc_board')->nullable();

            $table->string('honors_degree')->nullable();
            $table->string('honors_subject')->nullable();
            $table->string('honors_passing_year')->nullable();
            $table->string('honors_result')->nullable();
            $table->string('honors_university')->nullable();

            $table->string('masters_degree')->nullable();
            $table->string('masters_subject')->nullable();
            $table->string('masters_passing_year')->nullable();
            $table->string('masters_result')->nullable();
            $table->string('masters_university')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'designation',
                'joining_date',
                'index_number',
                'nid', 
                'ssc_degree', 'ssc_passing_year', 'ssc_result', 'ssc_board',
                'hsc_degree', 'hsc_passing_year', 'hsc_result', 'hsc_board',
                'honors_degree', 'honors_subject', 'honors_passing_year', 'honors_result', 'honors_university',
                'masters_degree', 'masters_subject', 'masters_passing_year', 'masters_result', 'masters_university',
            ]);
        });
    }
};