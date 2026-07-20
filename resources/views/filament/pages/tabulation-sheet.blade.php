<x-filament-panels::page>
    <form wire:submit.prevent="submit" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="success" icon="heroicon-m-magnifying-glass">
                Generate Tabulation Ledger
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
                        if (str_contains($word, '1st')) return $prefix . ' 1st';
                        if (str_contains($word, '2nd')) return $prefix . ' 2nd';
                    }
                    return $prefix;
                }
            }

            // 🌟 FETCH REAL-TIME LOGO PATH DYNAMICALLY FROM SITE SETTINGS 🌟
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();
            
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Tabulation Matrix</h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Tabulation Sheet
                </x-filament::button>
            </div>

            <div class="gazette-header-container">
                <div class="gazette-logo-wrapper">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="School Logo" class="school-live-logo">
                    @else
                        <div class="logo-fallback-badge">HM</div>
                    @endif
                </div>
                
                <div class="gazette-school-details">
                    <h1 class="gazette-school-title">Harimohan Govt. High School</h1>
                    <h2 class="gazette-exam-title">
                        Tabulation Sheet | {{ \App\Models\Exam::find($this->data['exam_id'])?->name ?? 'Academic' }} Exam: 
                        <span class="font-mono font-bold">{{ \App\Models\AcademicYear::find($this->data['academic_year_id'])?->name ?? '2026' }}</span>
                    </h2>
                    <div class="gazette-class-metadata">
                        Class: <span class="font-bold text-gray-900 dark:text-white">{{ \App\Models\SchoolClass::find($this->data['school_class_id'])?->name ?? 'N/A' }}</span>
                        @if(!empty($this->data['study_group']))
                            | Study Group: <span class="font-bold text-gray-900 dark:text-white">{{ $this->data['study_group'] }}</span>
                        @endif
                        @if(!empty($this->data['section_id']))
                            | Section: <span class="font-bold text-gray-900 dark:text-white">{{ \App\Models\Section::find($this->data['section_id'])?->name }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <table class="gazette-tabulation-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th style="width: 35px;">Sl</th>
                        <th style="width: 160px;" class="text-left">Name / ID / Roll</th>
                        
                        @foreach($subjects as $subject)
                            <th style="width: 90px; padding: 4px;">
                                <!-- 🌟 FIXED: Displays full name with parenthesis code, removes extra code row -->
                                <div class="font-bold text-[11px] leading-tight">{{ $subject->name }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $loopIndex => $enrollment)
                        @php
                            $student = $enrollment->user;
                            $studentReligion = strtolower(trim($student->religion ?? ''));
                        @endphp
                        <tr>
                            <td class="font-mono font-bold text-center text-gray-500 text-xs">
                                {{ sprintf('%02d', $loopIndex + 1) }}
                            </td>
                            
                            <td class="text-left px-2.5 leading-tight py-2 border-r border-black">
                                <div class="font-bold text-gray-900 dark:text-white uppercase text-[10px]">{{ $student->name }}</div>
                                <div class="text-[9px] text-gray-400 font-mono mt-0.5">ID : {{ $student->student_id }}</div>
                                <div class="text-[9px] text-gray-900 font-bold font-mono mt-0.5">Roll : {{ $enrollment->roll_number }}</div>
                            </td>

                            @foreach($subjects as $subject)
                                @php
                                    $subNameLower = strtolower($subject->name);
                                    
                                    // Religion Assignment Filter Valve
                                    $isReligionPaper = str_contains($subNameLower, 'islam') || str_contains($subNameLower, 'hindu') || str_contains($subNameLower, 'christian') || str_contains($subNameLower, 'buddhi');
                                    $religionMismatch = ($isReligionPaper && (
                                        (str_contains($subNameLower, 'islam') && $studentReligion !== 'islam') ||
                                        (str_contains($subNameLower, 'hindu') && !str_contains($studentReligion, 'hindu')) ||
                                        (str_contains($subNameLower, 'christian') && !str_contains($studentReligion, 'christian')) ||
                                        (str_contains($subNameLower, 'buddhi') && !str_contains($studentReligion, 'buddhi'))
                                    ));

                                    // Optional Subject Choice Filter Valve
                                    $isOptionalSubject = ($subject->subject_type === 'Optional' || $subject->type === 'Optional');
                                    $optionalMismatch = ($isOptionalSubject && (int)$enrollment->optional_subject_id !== (int)$subject->id);

                                    $mark = (!$religionMismatch && !$optionalMismatch)
                                        ? \App\Models\Mark::where('student_id', $enrollment->user_id)
                                            ->where('exam_id', $this->data['exam_id'])
                                            ->where('subject_id', $subject->id)
                                            ->first()
                                        : null;
                                @endphp

                                @if($religionMismatch || $optionalMismatch)
                                    <td class="bg-gray-50/40 text-gray-300 text-center font-mono">-</td>
                                @else
                                    <td class="px-2 py-1.5 text-left font-mono text-[9px] leading-relaxed bg-white dark:bg-gray-900">
                                        <div>Marks:{{ $mark ? (int)$mark->marks_obtained : 0 }}</div>
                                        <div>GPA: {{ $mark ? number_format($mark->gpa, 2) : '0.00' }}</div>
                                        <div class="{{ $mark && $mark->grade === 'F' ? 'text-danger-600 font-bold' : '' }}">
                                            Grade: {{ $mark ? $mark->grade : 'F' }}
                                        </div>
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        /* --- BRANDED GAZETTE HEADER STYLES --- */
        .gazette-header-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            width: 100%;
            border-bottom: 2px solid #000000;
            padding-bottom: 14px;
        }
        .gazette-logo-wrapper {
            flex: 0 0 auto;
            margin-right: 20px;
        }
        .school-live-logo {
            width: 65px;
            height: 65px;
            object-fit: contain;
        }
        .logo-fallback-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .gazette-school-details {
            text-align: center;
        }
        .gazette-school-title {
            font-size: 24px;
            font-weight: 800;
            color: #000000;
            letter-spacing: -0.5px;
            line-height: 1.1;
            font-family: serif;
        }
        .gazette-exam-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .gazette-class-metadata {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-top: 4px;
            letter-spacing: 0.25px;
        }

        /* --- DISPLAY GRID SYSTEM CSS --- */
        .gazette-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            border: 1px solid #000000;
            table-layout: fixed;
        }
        .gazette-tabulation-table th {
            border: 1px solid #000000;
            background-color: #f8fafc;
            color: #000000;
            font-weight: bold;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .gazette-tabulation-table td {
            border: 1px solid #000000;
            vertical-align: middle;
        }

        /* --- SYSTEM PHYSICAL PRINT FORMAT SPECIFICATIONS --- */
        @media print {
            @page {
                size: A4 landscape;
                margin: 6mm 4mm;
            }
            .no-print, header, sidebar, nav, .fi-sidebar, .fi-topbar, form { 
                display: none !important; 
            }
            body, .fi-main, .fi-content, main, .fi-layout { 
                background: white !important; 
                padding: 0 !important; 
                margin: 0 !important;
                width: 100% !important;
            }
            .print-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .gazette-header-container {
                display: flex !important;
                border-bottom: 1.5px solid #000000 !important;
            }
            .gazette-school-title {
                color: #000000 !important;
            }
            .gazette-tabulation-table {
                font-size: 8.5px !important;
            }
            .gazette-tabulation-table th, 
            .gazette-tabulation-table td {
                border: 0.5px solid #000000 !important;
                padding: 4px 3px !important;
                color: #000000 !important;
            }
            .text-danger-600 {
                color: #b91c1c !important;
                font-weight: bold !important;
            }
        }
    </style>
</x-filament-panels::page>