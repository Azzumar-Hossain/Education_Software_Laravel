<x-filament-panels::page>
    <form wire:submit.prevent="generateNoticeSheet" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="success" icon="heroicon-m-document-magnifying-glass">
                Generate Notice Sheet
            </x-filament::button>
        </div>
    </form>

    @if(count($students) > 0)
        @php
            if (!function_exists('getShortSubjectLabel')) {
                function getShortSubjectLabel($fullName) {
                    $cleanName = trim(preg_replace('/\(.*\)/u', '', $fullName));
                    $words = explode(' ', $cleanName);
                    if (empty($words) || !$words[0]) return 'SUB';
                    
                    $prefix = ucfirst(strtolower(substr($words[0], 0, 3)));
                    foreach ($words as $word) {
                        if (str_contains($word, '1st')) return $prefix . ' 1';
                        if (str_contains($word, '2nd')) return $prefix . ' 2';
                    }
                    return $prefix;
                }
            }

            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() ?? \App\Models\Setting::first();
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) : null;
        @endphp

        <div class="p-8 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-6 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Notice Board Preview</h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Official Notice Sheet
                </x-filament::button>
            </div>

            <div class="notice-header">
                <div class="notice-logo-wrapper">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="School Logo" class="school-logo-img">
                    @else
                        <div class="logo-fallback">HM</div>
                    @endif
                </div>
                <div class="notice-school-details">
                    <h1 class="notice-school-title">Harimohan Govt. High School</h1>
                    <h2 class="notice-sheet-title">Official Exam Result (Notice Board Copy)</h2>
                    <div class="notice-metadata">
                        Class: <strong>{{ \App\Models\SchoolClass::find($this->data['school_class_id'])?->name }}</strong>
                        @if(!empty($this->data['study_group'])) | Group: <strong>{{ $this->data['study_group'] }}</strong> @endif
                        | Exam: <strong>{{ \App\Models\Exam::find($this->data['exam_id'])?->name }}</strong>
                        | Session: <strong>{{ \App\Models\AcademicYear::find($this->data['academic_year_id'])?->name }}</strong>
                    </div>
                </div>
            </div>

            <table class="notice-board-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">Roll</th>
                        <th style="width: 160px;" class="text-left">Student Name</th>
                        <th style="width: 75px;">Student ID</th>
                        
                        {{-- 🌟 FIXED: Displays exact name (with parenthesis code), no abbreviations --}}
                        @foreach($subjects as $subject)
                            <th style="padding: 4px; font-size: 10px;">{{ $subject->name }}</th>
                        @endforeach
                        
                        <th style="width: 55px;" class="bg-gray-100 font-bold">Total</th>
                        <th style="width: 50px;" class="bg-gray-100 font-bold">GPA</th>
                        <th style="width: 50px;" class="bg-gray-100 font-bold">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $enrollment)
                        @php
                            $student = $enrollment->user;
                            $studentReligion = strtolower(trim($student->religion ?? ''));
                            $grandTotalMarks = 0;
                            $failedAnySubject = false;
                        @endphp
                        <tr>
                            <td class="font-mono font-bold text-center text-sm">{{ sprintf('%02d', $enrollment->roll_number) }}</td>
                            <td class="text-left px-3 font-semibold text-gray-900 dark:text-white uppercase truncate">{{ $student->name }}</td>
                            <td class="font-mono text-gray-500 text-center text-[10px]">{{ $student->student_id }}</td>

                            @foreach($subjects as $subject)
                                @php
                                    $subNameLower = strtolower($subject->name);
                                    
                                    $isReligionPaper = str_contains($subNameLower, 'islam') || str_contains($subNameLower, 'hindu') || str_contains($subNameLower, 'christian') || str_contains($subNameLower, 'buddhi');
                                    $religionMismatch = ($isReligionPaper && (
                                        (str_contains($subNameLower, 'islam') && $studentReligion !== 'islam') ||
                                        (str_contains($subNameLower, 'hindu') && !str_contains($studentReligion, 'hindu')) ||
                                        (str_contains($subNameLower, 'christian') && !str_contains($studentReligion, 'christian')) ||
                                        (str_contains($subNameLower, 'buddhi') && !str_contains($studentReligion, 'buddhi'))
                                    ));

                                    $isOptionalSubject = ($subject->subject_type === 'Optional' || $subject->type === 'Optional');
                                    $optionalMismatch = ($isOptionalSubject && (int)$enrollment->optional_subject_id !== (int)$subject->id);

                                    $mark = (!$religionMismatch && !$optionalMismatch)
                                        ? \App\Models\Mark::where('student_id', $enrollment->user_id)->where('exam_id', $this->data['exam_id'])->where('subject_id', $subject->id)->first()
                                        : null;

                                    if($mark) {
                                        $grandTotalMarks += $mark->marks_obtained;
                                        if($mark->grade === 'F') $failedAnySubject = true;
                                    }
                                @endphp

                                @if($religionMismatch || $optionalMismatch)
                                    <td class="bg-gray-50 text-gray-300 font-mono text-center">-</td>
                                @else
                                    <td class="font-mono font-bold text-center text-xs {{ $mark && $mark->grade === 'F' ? 'text-danger-600 font-black' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $mark ? $mark->grade : 'F' }}
                                    </td>
                                @endif
                            @endforeach

                            <td class="font-mono font-bold text-center bg-gray-50/50 text-gray-900 dark:text-white">{{ $grandTotalMarks }}</td>
                            <td class="font-mono font-bold text-center bg-gray-50/50 {{ $failedAnySubject ? 'text-danger-600' : 'text-success-600' }}">
                                {{ $failedAnySubject ? '0.00' : number_format(\App\Models\Mark::where('student_id', $enrollment->user_id)->where('exam_id', $this->data['exam_id'])->avg('gpa') ?? 0, 2) }}
                            </td>
                            <td class="font-mono font-bold text-center bg-gray-50/50 {{ $failedAnySubject ? 'text-danger-600 font-black' : 'text-success-600' }}">
                                {{ $failedAnySubject ? 'F' : 'A+' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="notice-signature-row">
                <div class="sig-space">
                    <div class="sig-line"></div>
                    <p class="sig-title">Prepared By</p>
                </div>
                <div class="sig-space">
                    <div class="sig-line"></div>
                    <p class="sig-title">Exam Controller</p>
                </div>
                <div class="sig-space">
                    <div class="sig-line"></div>
                    <p class="sig-title">Headmaster Signature</p>
                </div>
            </div>
            
            <div class="notice-publish-date font-mono">
                Result Published Date: <span>{{ now()->format('F d, Y') }}</span>
            </div>
        </div>
    @endif

    <style>
        /* BRAND METADATA CONTAINER STYLES */
        .notice-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            width: 100%;
            border-bottom: 2.5px double #000000;
            padding-bottom: 16px;
        }
        .notice-logo-wrapper { flex: 0 0 auto; margin-right: 20px; }
        .school-logo-img { width: 65px; height: 65px; object-fit: contain; }
        .logo-fallback { width: 60px; height: 60px; border-radius: 50%; background-color: #000000; color: #fff; font-weight: bold; display: flex; align-items: center; justify-content: center; }
        .notice-school-details { text-align: center; }
        .notice-school-title { font-size: 24px; font-weight: 900; color: #000000; font-family: serif; text-transform: uppercase; }
        .notice-sheet-title { font-size: 13px; font-weight: 700; color: #334155; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; }
        .notice-metadata { font-size: 11px; color: #475569; margin-top: 5px; }

        /* THE COMPACT GRID SYSTEM STYLES */
        .notice-board-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            border: 1px solid #000000;
            table-layout: fixed;
        }
        .notice-board-table th {
            border: 1px solid #000000;
            background-color: #f1f5f9;
            color: #000000;
            font-weight: bold;
            padding: 4px 2px; /* Slightly reduced padding to give text more room */
            text-align: center;
            /* 🌟 ADDED THESE THREE LINES: */
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        .notice-board-table td {
            border: 1px solid #000000;
            padding: 6px 2px;
            vertical-align: middle;
        }

        /* SIGNATURE SECTION LAYOUT BOUNDS */
        .notice-signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 20px;
            width: 100%;
        }
        .sig-space { text-align: center; width: 180px; }
        .sig-line { border-top: 1px dashed #000000; width: 100%; margin-bottom: 6px; }
        .sig-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1e293b; }
        .notice-publish-date { font-size: 9px; text-transform: uppercase; color: #64748b; margin-top: 30px; text-align: left; padding-left: 20px; }

        @media print {
            @page { size: A4 landscape; margin: 6mm 4mm; }
            .no-print, header, sidebar, nav, .fi-sidebar, .fi-topbar, form { display: none !important; }
            body, .fi-main, .fi-content, main, .fi-layout { background: white !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .print-container { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .notice-header { display: flex !important; }
            .notice-board-table { font-size: 9.5px !important; }
            .notice-board-table th, .notice-board-table td { border: 0.5px solid #000000 !important; padding: 5px 2px !important; color: #000000 !important; }
            .notice-signature-row { display: flex !important; margin-top: 80px !important; }
            .text-danger-600 { color: #000000 !important; font-weight: 900 !important; }
        }
    </style>
</x-filament-panels::page>