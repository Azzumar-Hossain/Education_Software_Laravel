<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicYear;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class StudentsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Support both old 'student_name' and new 'student_name_en' column names
        $studentName = $row['student_name_en'] ?? $row['student_name'] ?? null;

        if (empty($studentName)) {
            return null;
        }

        // 1. Get current active academic year
        $activeYear = AcademicYear::where('is_active', true)->first() 
            ?? AcademicYear::latest()->first();

        $yearName = $activeYear ? $activeYear->name : date('Y');

        // 2. Fetch Class and Section IDs from Excel names
        $class = SchoolClass::where('name', $row['class_name'] ?? '')->first();
        $section = Section::where('name', $row['section_name'] ?? '')->first();

        // 3. Format Roll Number into numeric Student ID (e.g., 20260103)
        $rollNumber = trim($row['roll_number']);
        $studentId = $yearName . sprintf('%04d', $rollNumber);

        // 4. Format Date of Birth safely
        $dob = null;
        if (!empty($row['date_of_birth'])) {
            try {
                if (is_numeric($row['date_of_birth'])) {
                    $dob = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date_of_birth'])->format('Y-m-d');
                } else {
                    $dob = Carbon::parse($row['date_of_birth'])->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $dob = null;
            }
        }

        // Helper function to format phone numbers (adds leading 0 if dropped by Excel)
        $formatPhone = function ($phone) {
            if (empty($phone)) return null;
            $phoneStr = trim((string)$phone);
            if (strlen($phoneStr) == 10 && str_starts_with($phoneStr, '1')) {
                return '0' . $phoneStr;
            }
            return $phoneStr;
        };

        // 5. Create Student User with full Personal and Parents details
        $user = User::create([
            'name'                 => $studentName,
            'name_bn'              => $row['student_name_bn'] ?? null,
            'student_id'           => $studentId, // Pure numeric ID (20260103)
            'religion'             => !empty($row['religion']) ? ucfirst(strtolower(trim($row['religion']))) : null,
            'birth_reg_no'         => $row['birth_reg_no'] ?? null,
            'phone'                => $formatPhone($row['phone'] ?? null),
            'gender'               => !empty($row['gender']) ? ucfirst(strtolower(trim($row['gender']))) : null,
            'dob'                  => $dob,
            
            // Father's Information
            'father_name'          => $row['father_name_en'] ?? $row['father_name'] ?? null,
            'father_name_bn'       => $row['father_name_bn'] ?? null,
            'father_phone'         => $formatPhone($row['father_phone'] ?? null),
            'father_nid'           => $row['father_nid'] ?? null,

            // Mother's Information
            'mother_name'          => $row['mother_name_en'] ?? $row['mother_name'] ?? null,
            'mother_name_bn'       => $row['mother_name_bn'] ?? null,
            'mother_phone'         => $formatPhone($row['mother_phone'] ?? null),
            'mother_nid'           => $row['mother_nid'] ?? null,

            'email'                => 'student_' . $studentId . '@school.com',
            'password'             => bcrypt('password123'),
            'type'                 => 'student',
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('Student');
        }

        // 6. Create Enrollment Record
        return new Enrollment([
            'user_id'          => $user->id,
            'academic_year_id' => $activeYear?->id,
            'school_class_id'  => $class?->id,
            'section_id'       => $section?->id,
            'roll_number'      => $rollNumber,
        ]);
    }
}