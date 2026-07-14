<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicYear;

class FixClassSevenEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get or create the Academic Year context
        $academicYear = AcademicYear::firstOrCreate(['name' => '2027']); 

        // 2. Get or create the School Class
        $schoolClass = SchoolClass::firstOrCreate(
            ['name' => 'Class 7'],
            ['numeric_value' => 7] 
        ); 

        // 🌟 FIXED: Using 'class_id' instead of 'school_class_id' to match your database schema
        $section = Section::firstOrCreate([
            'class_id' => $schoolClass->id,
            'name' => 'A'
        ]);

        // 3. Find all uploaded users between ID 20260097 and 20260140
        $students = User::where('type', 'student')
            ->whereBetween('student_id', ['20260097', '20260140'])
            ->get();

        $enrolledCount = 0;

        foreach ($students as $index => $student) {
            // Auto-calculate class roll starting from 1 sequentially
            $rollNumber = $index + 1;

            // Prevent duplicate double assignments
            Enrollment::where('academic_year_id', $academicYear->id)
                ->where('user_id', $student->id)
                ->delete();

            // Link them into the academic panel cleanly
            Enrollment::create([
                'user_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass->id,
                'section_id' => $section->id,
                'roll_number' => $rollNumber,
                'study_group' => 'General',
                'status' => 'Active',
            ]);

            $enrolledCount++;
        }

        $this->command->info("Success! Connected {$enrolledCount} profiles to Class 7 - Section A.");
    }
}