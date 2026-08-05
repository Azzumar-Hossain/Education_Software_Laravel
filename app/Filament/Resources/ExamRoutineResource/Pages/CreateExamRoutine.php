<?php

namespace App\Filament\Resources\ExamRoutineResource\Pages;

use App\Filament\Resources\ExamRoutineResource;
use App\Models\ExamRoutine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExamRoutine extends CreateRecord
{
    protected static string $resource = ExamRoutineResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $academicYearId = $data['academic_year_id'];
        $schoolClassId = $data['school_class_id'];
        $examId = $data['exam_id'];
        $schedules = $data['schedules'] ?? [];

        $firstCreatedRecord = null;

        foreach ($schedules as $schedule) {
            $record = ExamRoutine::create([
                'academic_year_id' => $academicYearId,
                'school_class_id'  => $schoolClassId,
                'exam_id'          => $examId,
                'subject_id'       => $schedule['subject_id'],
                'exam_date'        => $schedule['exam_date'],
                'start_time'       => $schedule['start_time'],
                'end_time'         => $schedule['end_time'],
                'room_number'      => $schedule['room_number'] ?? null,
            ]);

            if (!$firstCreatedRecord) {
                $firstCreatedRecord = $record;
            }
        }

        return $firstCreatedRecord ?? ExamRoutine::create($data);
    }
}