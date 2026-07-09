<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicSetupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create an active Academic Year
        AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
        ]);

        // 2. Create Classes
        $class9 = SchoolClass::create(['name' => 'Class 9', 'numeric_value' => 9]);
        $class10 = SchoolClass::create(['name' => 'Class 10', 'numeric_value' => 10]);

        // 3. Create Sections
        $sectionA = Section::create(['name' => 'Section A']);
        $sectionB = Section::create(['name' => 'Section B']);
        $sectionSci = Section::create(['name' => 'Science']);

        // 4. Create Subjects
        $math = Subject::create(['name' => 'Mathematics', 'code' => 'MTH-101', 'type' => 'core']);
        $english = Subject::create(['name' => 'English', 'code' => 'ENG-101', 'type' => 'core']);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'PHY-101', 'type' => 'practical']);

        // 5. Attach Sections to Classes (Populating the class_section pivot table)
        // Class 9 has Section A and Section B
        $class9->sections()->attach([$sectionA->id, $sectionB->id]);
        
        // Class 10 has Section A, Section B, and Science
        $class10->sections()->attach([$sectionA->id, $sectionB->id, $sectionSci->id]);

        // 6. Attach Subjects to Classes (Populating the class_subject pivot table)
        // Class 9 takes Math and English
        $class9->subjects()->attach([$math->id, $english->id]);
        
        // Class 10 takes Math, English, and Physics
        $class10->subjects()->attach([$math->id, $english->id, $physics->id]);
    }
}
