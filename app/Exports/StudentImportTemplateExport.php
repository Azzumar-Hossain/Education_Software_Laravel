<?php

namespace App\Exports;

use App\Models\SchoolClass;
use App\Models\Section;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class StudentImportTemplateExport implements FromArray, WithHeadings, WithTitle
{
    protected ?int $classId;
    protected ?int $sectionId;

    public function __construct(?int $classId = null, ?int $sectionId = null)
    {
        $this->classId = $classId;
        $this->sectionId = $sectionId;
    }

    public function title(): string
    {
        return 'Student Import Template';
    }

    public function headings(): array
    {
        return [
            'roll_number',
            'student_name_en',
            'student_name_bn',
            'religion',
            'birth_reg_no',
            'phone',
            'gender',
            'date_of_birth',
            'father_name_en',
            'father_name_bn',
            'father_phone',
            'father_nid',
            'mother_name_en',
            'mother_name_bn',
            'mother_phone',
            'mother_nid',
            'class_name',
            'section_name',
        ];
    }

    public function array(): array
    {
        $className = $this->classId ? SchoolClass::find($this->classId)?->name : 'Class 06';
        $sectionName = $this->sectionId ? Section::find($this->sectionId)?->name : 'A';

        return [
            [
                '103',                          // roll_number
                'Mahmuda Akhter Jui',           // student_name_en
                'মাহমুদা আক্তার জুঁই',             // student_name_bn
                'Islam',                        // religion
                '20131234567890123',            // birth_reg_no
                '01735287787',                  // phone
                'Female',                       // gender
                '2013-01-15',                   // date_of_birth
                'Md. Mukhlesur Rahman',         // father_name_en
                'মোঃ মোখলেছুর রহমান',            // father_name_bn
                '01711000000',                  // father_phone
                '1980123456789',                // father_nid
                'Nesful Begum',                 // mother_name_en
                'নেসফুল বেগম',                   // mother_name_bn
                '01722000000',                  // mother_phone
                '1985123456789',                // mother_nid
                $className,                     // class_name
                $sectionName,                   // section_name
            ]
        ];
    }
}