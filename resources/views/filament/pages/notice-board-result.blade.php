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
            // FETCH SCHOOL DETAILS
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
                    @foreach($students as $loopIndex => $studentData)
                        @php
                            // Extract Data provided purely by the Controller
                            $enrollment = is_array($studentData) ? $studentData['enrollment'] : $studentData;
                            $student    = $enrollment->user;

                            $grandTotalMarks = is_array($studentData) ? $studentData['grand_total'] : 0;
                            $calculatedGPA   = is_array($studentData) ? $studentData['gpa'] : '0.00';
                            $calculatedGrade = is_array($studentData) ? $studentData['grade'] : 'F';
                            $position        = is_array($studentData) ? $studentData['position'] : '--';
                            $studentMarks    = is_array($studentData) ? $studentData['marks'] : collect();
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <!-- Roll Number -->
                            <td class="text-center font-bold font-mono px-2 py-1.5 border border-gray-300 dark:border-gray-700">
                                {{ sprintf('%02d', $enrollment->roll_number) }}
                            </td>

                            <!-- Student Name -->
                            <td class="text-left font-bold text-gray-900 dark:text-white px-2 py-1.5 border border-gray-300 dark:border-gray-700">
                                {{ $student->name }}
                            </td>

                            <!-- Student ID -->
                            <td class="text-center font-mono text-gray-600 dark:text-gray-300 px-2 py-1.5 border border-gray-300 dark:border-gray-700">
                                {{ $student->student_id }}
                            </td>

                            <!-- Subject Grade Grid -->
                            @foreach($subjects as $subject)
                                @php
                                    $mark = $studentMarks->get($subject->id);
                                    
                                    // Filter out Religion mismatches
                                    $subName    = strtolower($subject->name ?? '');
                                    $studentRel = strtolower(trim($student->religion ?? ''));
                                    
                                    $isReligionMismatch = false;
                                    if (str_contains($subName, 'islam') && $studentRel !== 'islam') $isReligionMismatch = true;
                                    if (str_contains($subName, 'hindu') && $studentRel !== 'hinduism' && $studentRel !== 'hindu') $isReligionMismatch = true;
                                    if (str_contains($subName, 'christian') && $studentRel !== 'christianity' && $studentRel !== 'christian') $isReligionMismatch = true;
                                @endphp

                                <td class="text-center font-extrabold text-xs px-1 py-1.5 border border-gray-300 dark:border-gray-700">
                                    @if($isReligionMismatch)
                                        <span class="text-gray-400">-</span>
                                    @elseif($mark)
                                        <span class="{{ $mark->grade === 'F' ? 'text-red-600 font-black' : 'text-gray-800 dark:text-gray-200' }}">
                                            {{ $mark->grade }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <!-- Total Marks -->
                            <td class="text-center font-extrabold text-gray-900 dark:text-white px-2 py-1.5 border border-gray-300 dark:border-gray-700">
                                {{ number_format($grandTotalMarks, 0) }}
                            </td>

                            <!-- GPA -->
                            <td class="text-center font-bold text-blue-600 dark:text-blue-400 px-2 py-1.5 border border-gray-300 dark:border-gray-700">
                                {{ $calculatedGPA }}
                            </td>

                            <!-- Final Grade -->
                            <td class="text-center font-extrabold px-2 py-1.5 border border-gray-300 dark:border-gray-700 {{ $calculatedGrade === 'F' ? 'text-red-600' : 'text-green-600' }}">
                                {{ $calculatedGrade }}
                            </td>

                            <!-- Position -->
                            <td class="text-center font-black px-2 py-1.5 border border-gray-300 dark:border-gray-700 {{ $position === 'Fail' ? 'text-red-600' : 'text-blue-700 dark:text-blue-300' }}">
                                {{ $position }}
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

            /* Hide ONLY the form, sidebar, and topbar */
            form.no-print, 
            .fi-sidebar, 
            .fi-topbar, 
            header {
                display: none !important;
            }

            /* Force all underlying Filament wrappers to pure white */
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

            /* Strip dark mode borders/shadows from the actual content */
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

            .notice-board-table td { 
                background-color: #ffffff !important;
                border: 0.5px solid #000000 !important; 
                color: #000000 !important; 
            }

            /* Remove borders from spans/divs inside the table cells */
            .notice-board-table td * { 
                background-color: transparent !important;
                border: none !important; 
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

            * {
                box-shadow: none !important;
            }
        }
    </style>
</x-filament-panels::page>