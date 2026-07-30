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

            // 1. FIX SCHOOL NAME FETCHING
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();

            $schoolName = $siteSetting?->school_name 
                ?? $siteSetting?->title 
                ?? $siteSetting?->site_name 
                ?? $siteSetting?->name 
                ?? 'Nayagola High School';

            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;

            // DYNAMIC SECTION LOOKUP
            $sectionName = !empty($this->data['section_id']) 
                ? \App\Models\Section::find($this->data['section_id'])?->name 
                : null;

            // 2. CALCULATE GLOBAL MERIT POSITIONS
            $allStudentMetrics = collect();

            foreach ($students as $enrollment) {
                $student = $enrollment->user;
                if (!$student) continue;

                $studentReligion = strtolower(trim($student->religion ?? ''));
                $grandTotalMarks = 0;
                $failedAnySubject = false;
                $totalGpaSum = 0;
                $subjectCount = 0;

                foreach ($subjects as $subject) {
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

                    if (!$religionMismatch && !$optionalMismatch) {
                        $mark = \App\Models\Mark::where('student_id', $enrollment->user_id)
                            ->where('exam_id', $this->data['exam_id'])
                            ->where('subject_id', $subject->id)
                            ->first();

                        if ($mark) {
                            $grandTotalMarks += $mark->marks_obtained;
                            $totalGpaSum += $mark->gpa;
                            $subjectCount++;
                            if ($mark->grade === 'F') {
                                $failedAnySubject = true;
                            }
                        } else {
                            $failedAnySubject = true;
                        }
                    }
                }

                $avgGpa = $subjectCount > 0 ? ($totalGpaSum / $subjectCount) : 0;

                $allStudentMetrics->push([
                    'enrollment_id' => $enrollment->id,
                    'user_id'       => $enrollment->user_id,
                    'failed'        => $failedAnySubject,
                    'gpa'           => $failedAnySubject ? 0.00 : $avgGpa,
                    'total_marks'   => $grandTotalMarks,
                ]);
            }

            // Order by Passed first, then highest GPA, then highest Total Marks
            $sortedMeritList = $allStudentMetrics->sort(function ($a, $b) {
                if ($a['failed'] !== $b['failed']) {
                    return $a['failed'] ? 1 : -1;
                }
                if ($a['gpa'] != $b['gpa']) {
                    return $b['gpa'] <=> $a['gpa'];
                }
                return $b['total_marks'] <=> $a['total_marks'];
            })->values();

            // Build position lookup table
            $positionLookup = [];
            $rankCounter = 1;

            foreach ($sortedMeritList as $item) {
                if ($item['failed']) {
                    $positionLookup[$item['enrollment_id']] = 'Fail';
                } else {
                    $positionLookup[$item['enrollment_id']] = $rankCounter++;
                }
            }
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
                    <h1 class="notice-school-title">{{ $schoolName }}</h1>
                    <h2 class="notice-sheet-title">Official Exam Result (Notice Board Copy)</h2>
                    <div class="notice-metadata">
                        Class: <strong>{{ \App\Models\SchoolClass::find($this->data['school_class_id'])?->name }}{{ $sectionName ? " (Section {$sectionName})" : "" }}</strong>
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
                        <th style="width: 160px;" class="text-left px-2">Student Name</th>
                        <th style="width: 75px;">Student ID</th>
                        
                        @foreach($subjects as $subject)
                            <th style="padding: 4px; font-size: 10px;">{{ $subject->name }}</th>
                        @endforeach
                        
                        <th style="width: 55px;" class="bg-gray-100 font-bold">Total</th>
                        <th style="width: 50px;" class="bg-gray-100 font-bold">GPA</th>
                        <th style="width: 50px;" class="bg-gray-100 font-bold">Grade</th>
                        <th style="width: 55px;" class="bg-gray-100 font-bold">Position</th>
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
                                {{ $failedAnySubject ? 'F' : 'A' }}
                            </td>

                            <td class="font-mono font-bold text-center bg-gray-50/50 {{ ($positionLookup[$enrollment->id] ?? '') === 'Fail' ? 'text-danger-600 font-black' : 'text-gray-900 dark:text-white' }}">
                                {{ $positionLookup[$enrollment->id] ?? '-' }}
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
            background-color: #ffffff;
        }
        .notice-board-table th {
            border: 1px solid #000000;
            background-color: #f1f5f9;
            color: #000000;
            font-weight: bold;
            padding: 4px 2px;
            text-align: center;
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

        /* CORRECTED PRINT STYLES */
        @media print {
            @page { 
                size: A4 landscape; 
                margin: 6mm 4mm; 
            }

            /* 1. Hide ONLY the form, sidebar, and topbar */
            form.no-print, 
            .fi-sidebar, 
            .fi-topbar, 
            header {
                display: none !important;
            }

            /* 2. Force all underlying Filament wrappers to pure white */
            html, 
            body, 
            .fi-layout, 
            .fi-main, 
            .fi-content, 
            .fi-page {
                background-color: #ffffff !important;
                background-image: none !important;
                color: #000000 !important;
                color-scheme: light !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* 3. Strip dark mode borders/shadows from the actual content */
            .print-container {
                background-color: #ffffff !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                color: #000000 !important;
            }

            /* Text & Table overrides for Dark Mode */
            .notice-school-title, .notice-sheet-title, .notice-metadata { 
                color: #000000 !important; 
            }

            .notice-board-table { 
                font-size: 9.5px !important; 
                background-color: #ffffff !important;
            }

            .notice-board-table th { 
                background-color: #f1f5f9 !important; 
                color: #000000 !important; 
                border: 0.5px solid #000000 !important; 
            }

            .notice-board-table td, 
            .notice-board-table td * { 
                background-color: #ffffff !important;
                border: 0.5px solid #000000 !important; 
                color: #000000 !important; 
            }

            .notice-signature-row { 
                display: flex !important; 
                margin-top: 60px !important; 
            }

            .text-danger-600 { 
                color: #dc2626 !important; 
                font-weight: 900 !important; 
            }

            /* Kill any Filament overlay/backdrop layers left in the DOM */
            .fi-sidebar-close-overlay,
            .fi-modal-close-overlay,
            .fi-modal-overlay,
            .fi-modal,
            .fi-dropdown-panel,
            [x-show="sidebarOpen"],
            div[class*="overlay"],
            div[class*="backdrop"] {
                display: none !important;
            }

            /* Force light mode even if <html> has the dark class */
            html.dark,
            html.dark body,
            .dark {
                background-color: #ffffff !important;
                color-scheme: light !important;
            }

            /* Belt-and-braces: nothing should paint gray behind the page */
            * {
                box-shadow: none !important;
            }
        }
    </style>
</x-filament-panels::page>