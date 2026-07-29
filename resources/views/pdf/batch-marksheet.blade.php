<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Batch Marksheets</title>
</head>
<body>
    @php
        // Ensure enrollments print sequentially by numerical roll number
        $sortedEnrollments = $enrollments->sortBy(function($e) {
            return (int) $e->roll_number;
        });
    @endphp

    @foreach($sortedEnrollments as $index => $enrollment)
        @php
            $student = $enrollment->user;
            $marks = \App\Models\Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->where('school_class_id', $enrollment->school_class_id)
                ->where('exam_id', $exam->id)
                ->get();

            $rawHtml = view('pdf.marksheet', [
                'enrollment' => $enrollment,
                'student'    => $student,
                'exam'       => $exam,
                'marks'      => $marks,
            ])->render();

            $cleanHtml = str_ireplace([
                '<!DOCTYPE html>', 
                '<html>', 
                '</html>', 
                '<head>', 
                '</head>', 
                '<body>', 
                '</body>'
            ], '', $rawHtml);
        @endphp

        @if($index > 0)
            <pagebreak />
        @endif

        {!! $cleanHtml !!}
        
    @endforeach
</body>
</html>