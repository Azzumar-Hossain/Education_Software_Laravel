<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Final Cumulative Marksheets</title>
    <style>
        body { margin: 0; padding: 0; background: #fff; }
        
        @media print {
            .page-break {
                page-break-after: always;
                break-after: page;
            }
            .page-break:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>
<body>

    @if($enrollments->isEmpty())
        <h2 style="padding: 20px; font-family: sans-serif; color: red;">Error: No students found.</h2>
    @endif

    @foreach($enrollments as $enrollment)
        <div class="page-break">
            <!-- 🌟 This calls your Landscape Final Marksheet 🌟 -->
            @include('pdf.final-marksheet', [
                'enrollment' => $enrollment,
                'allMarks'   => $allMarks->where('student_id', $enrollment->user_id),
                'is_batch'   => true 
            ])
        </div>
    @endforeach

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 1500); 
        };
    </script>
</body>
</html>