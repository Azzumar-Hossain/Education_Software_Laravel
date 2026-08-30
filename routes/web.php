<?php

use Illuminate\Support\Facades\Route;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Exam;
use Illuminate\Http\Request;

Route::redirect('/', '/admin/login');

// Route for Single Exam Marksheet
Route::get('/print/marksheet/{enrollment}/{exam}', function ($enrollmentId, $examId) {
    $enrollment = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])->findOrFail($enrollmentId);
    $exam = Exam::findOrFail($examId);
    
    $marks = Mark::with('subject')
        ->where('student_id', $enrollment->user_id)
        ->where('academic_year_id', $enrollment->academic_year_id)
        ->where('school_class_id', $enrollment->school_class_id)
        ->where('exam_id', $examId)
        ->get();

    return view('pdf.marksheet', compact('enrollment', 'marks', 'exam'));
})->name('print.marksheet');


// Route for Final Cumulative Marksheet
Route::get('/print/final-marksheet/{id}', function ($id) {
    $enrollment = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])->findOrFail($id);
    
    $allMarks = Mark::with(['subject', 'exam'])
        ->where('student_id', $enrollment->user_id)
        ->where('academic_year_id', $enrollment->academic_year_id)
        ->where('school_class_id', $enrollment->school_class_id)
        ->get();

    return view('pdf.final-marksheet', [
        'enrollment' => $enrollment,
        'allMarks'   => $allMarks,
    ]);
})->name('print.final.marksheet');


// 🌟 MISSING ROUTE ADDED: Term Exam Batch Marksheet 🌟
Route::get('/print/batch-marksheet', function (Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $examId = $request->query('exam');
    $sectionId = $request->query('section');

    $query = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId);

    if (!empty($sectionId) && $sectionId !== 'N/A' && $sectionId !== 'null') {
        $query->where('section_id', $sectionId);
    }
    $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

    if ($enrollments->isEmpty()) {
        return "No students found. Please check filters.";
    }

    $exam = Exam::with('academicYear')->findOrFail($examId);

    $allMarks = Mark::with('subject')
        ->whereIn('student_id', $enrollments->pluck('user_id'))
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId)
        ->where('exam_id', $examId)
        ->get();

    return view('pdf.batch-marksheet', compact('enrollments', 'exam', 'allMarks'));
})->name('print.batch.marksheet');


// Route for Final Cumulative Batch Marksheet
Route::get('/print/batch-final-marksheet', function (Request $request) {
    $yearId = $request->query('year');
    $classId = $request->query('class');
    $sectionId = $request->query('section');

    $query = Enrollment::with(['user', 'schoolClass', 'section', 'academicYear'])
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId);

    if (!empty($sectionId) && $sectionId !== 'N/A' && $sectionId !== 'null') {
        $query->where('section_id', $sectionId);
    }
    $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

    if ($enrollments->isEmpty()) {
        return "No students found. Please check filters.";
    }

    // Pull ALL marks for the entire academic year for these students
    $allMarks = Mark::with(['subject', 'exam'])
        ->whereIn('student_id', $enrollments->pluck('user_id'))
        ->where('academic_year_id', $yearId)
        ->where('school_class_id', $classId)
        ->get();

    return view('pdf.batch-final-marksheet', compact('enrollments', 'allMarks'));
})->name('print.batch.final.marksheet');