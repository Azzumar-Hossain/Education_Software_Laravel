<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Bring back your Admin User and Academic Setup!
        $this->call([
            RoleAndUserSeeder::class,
            AcademicSetupSeeder::class,
        ]);

        // 2. Add the completely separated Fee Categories
        $fees = [
            ['name' => 'Tuition Fee', 'name_bn' => 'বেতন'],
            ['name' => 'Arrears / Previous Dues', 'name_bn' => 'বকেয়া'],
            ['name' => 'Advance', 'name_bn' => 'অগ্রিম'],
            ['name' => 'Admission / Transfer', 'name_bn' => 'ভর্তি/পুন: ভর্তি/বিদায়'],
            ['name' => 'Sports Fund Fee', 'name_bn' => 'ক্রীড়া তহবিল ফি'],
            ['name' => 'Building Fee', 'name_bn' => 'গৃহ নির্মাণ ফি'],
            ['name' => 'Library + Magazine', 'name_bn' => 'পাঠাগার + পত্রিকা'],
            ['name' => 'Poor Fund Fee', 'name_bn' => 'দরিদ্র ফি'],
            ['name' => 'Scout Fee', 'name_bn' => 'স্কাউট ফি'],
            
            // The newly separated fee heads!
            ['name' => 'Fine', 'name_bn' => 'জরিমানা'],
            ['name' => 'Science Fee', 'name_bn' => 'বিজ্ঞান ফি'],
            ['name' => 'Absence Fee', 'name_bn' => 'অনুপস্থিত'],
            ['name' => 'Exam Fee', 'name_bn' => 'পরীক্ষা'],
            ['name' => 'Prize Fee', 'name_bn' => 'প্রাইজ'],
            ['name' => 'Marksheet Fee', 'name_bn' => 'মার্কসীট ফি'],
            
            ['name' => 'Red Crescent', 'name_bn' => 'রেডক্রিসেন্ট'],
            ['name' => 'Others', 'name_bn' => 'অন্যান্য'],
        ];

        foreach ($fees as $fee) {
            \App\Models\FeeCategory::firstOrCreate(['name_bn' => $fee['name_bn']], $fee);
        }
    }
}