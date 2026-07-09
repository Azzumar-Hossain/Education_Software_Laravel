<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Skip empty rows safely
            if (empty($row['email']) || empty($row['name'])) {
                continue; 
            }

            // 1. Create or Find the User Account
            $user = User::firstOrCreate(
                ['email' => $row['email']], // Prevent duplicate emails
                [
                    'name' => $row['name'],
                    'password' => Hash::make($row['password']),
                    'type' => 'student',
                    'gender' => $row['gender'] ?? null,
                ]
            );

            // 2. Create the Enrollment Record
            $enrollment = Enrollment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'academic_year_id' => $row['academic_year_id'],
                ],
                [
                    'school_class_id' => $row['class_id'],
                    'section_id' => $row['section_id'] ?? null,
                    'roll_number' => $row['roll_number'] ?? null,
                    'study_group' => $row['study_group'] ?? 'General',
                    'optional_subject_id' => $row['optional_subject_id'] ?? null,
                    'status' => 'Active',
                ]
            );

            // 3. THE AUTOMATIC SUBJECT ASSIGNMENT MAGIC
            $schoolClass = SchoolClass::with('subjects')->find($row['class_id']);
            
            if ($schoolClass) {
                $subjectIdsToAssign = [];
                $studentGroup = $row['study_group'] ?? 'General';

                foreach ($schoolClass->subjects as $subject) {
                    if ($subject->subject_type === 'Core') {
                        $subjectIdsToAssign[] = $subject->id;
                    } elseif ($subject->subject_type === 'Group' && $subject->study_group === $studentGroup) {
                        $subjectIdsToAssign[] = $subject->id;
                    }
                }

                if (!empty($row['optional_subject_id'])) {
                    $subjectIdsToAssign[] = $row['optional_subject_id'];
                }

                // Use sync() to attach the subjects cleanly
                $enrollment->subjects()->sync(array_unique($subjectIdsToAssign));
            }
        }
    }
}