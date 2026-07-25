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
            $examId = $this->data['exam_id'];
            $academicYearId = $this->data['academic_year_id'];
            $classId = $this->data['school_class_id'];

            $peerTotals = \App\Models\Mark::where('academic_year_id', $academicYearId)
                ->where('school_class_id', $classId)
                ->where('exam_id', $examId)
                ->select('student_id', \DB::raw('SUM(marks_obtained) as aggregate_score'))
                ->groupBy('student_id')
                ->orderBy('aggregate_score', 'DESC')
                ->get();

            // DYNAMIC SITE SETTINGS
            $siteSetting = \App\Models\SiteSetting::first() 
                ?? \Illuminate\Support\Facades\DB::table('site_settings')->first();
            
            $schoolName = !empty($siteSetting?->school_name_en) 
                ? $siteSetting->school_name_en 
                : 'Shankarbati High School';

            $schoolAddress = !empty($siteSetting?->address_en) 
                ? $siteSetting->address_en 
                : 'Chapai Nawabganj, Bangladesh';

            $logoPath = (!empty($siteSetting?->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;

            $words = explode(' ', $schoolName);
            $initials = count($words) >= 2 
                ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) 
                : strtoupper(substr($schoolName, 0, 2));

            $rowsPerPage = (int) ($this->data['rows_per_page'] ?? 7);

            $examModel = \App\Models\Exam::find($examId);
            $yearModel = \App\Models\AcademicYear::find($academicYearId);
            $classModel = \App\Models\SchoolClass::find($classId);
            $sectionModel = !empty($this->data['section_id']) ? \App\Models\Section::find($this->data['section_id']) : null;
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tabulation Matrix</h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Tabulation Sheet
                </x-filament::button>
            </div>

            <!-- 🌟 1. PRINT COVER PAGE (PAGE 1) 🌟 -->
            <div class="tabulation-cover-page bg-white dark:bg-gray-900 border-2 border-black dark:border-gray-700">
                <div class="cover-content">
                    <div class="cover-logo-wrapper mb-4">
                        @if($logoPath)
                            <img src="{{ $logoPath }}" alt="School Logo" class="cover-school-logo">
                        @else
                            <div class="cover-logo-badge">{{ $initials }}</div>
                        @endif
                    </div>

                    <h1 class="cover-school-name text-gray-900 dark:text-white">{{ $schoolName }}</h1>
                    <p class="cover-subheading text-gray-600 dark:text-gray-400">{{ $schoolAddress }}</p>

                    <div class="cover-divider my-6 border-black dark:border-gray-700"></div>

                    <h2 class="cover-doc-title text-gray-900 dark:text-white">TABULATION SHEET</h2>
                    <h3 class="cover-exam-name text-gray-800 dark:text-gray-200">{{ $examModel?->name ?? 'ACADEMIC' }} EXAMINATION - {{ $yearModel?->name ?? '2026' }}</h3>

                    <div class="cover-meta-grid my-8">
                        <div class="meta-card border-black dark:border-gray-700">
                            <span class="meta-label text-gray-500 dark:text-gray-400">Class</span>
                            <span class="meta-value text-gray-900 dark:text-white">{{ $classModel?->name ?? 'N/A' }}</span>
                        </div>
                        @if($sectionModel)
                            <div class="meta-card border-black dark:border-gray-700">
                                <span class="meta-label text-gray-500 dark:text-gray-400">Section</span>
                                <span class="meta-value text-gray-900 dark:text-white">{{ $sectionModel->name }}</span>
                            </div>
                        @endif
                        @if(!empty($this->data['study_group']))
                            <div class="meta-card border-black dark:border-gray-700">
                                <span class="meta-label text-gray-500 dark:text-gray-400">Group</span>
                                <span class="meta-value text-gray-900 dark:text-white">{{ $this->data['study_group'] }}</span>
                            </div>
                        @endif
                        <div class="meta-card border-black dark:border-gray-700">
                            <span class="meta-label text-gray-500 dark:text-gray-400">Total Students</span>
                            <span class="meta-value text-gray-900 dark:text-white">{{ count($students) }}</span>
                        </div>
                    </div>

                    <div class="cover-signatures mt-16">
                        <div class="sig-box text-gray-900 dark:text-white">
                            <div class="sig-line border-black dark:border-gray-700"></div>
                            <span>Prepared By</span>
                        </div>
                        <div class="sig-box text-gray-900 dark:text-white">
                            <div class="sig-line border-black dark:border-gray-700"></div>
                            <span>Exam Committee Controller</span>
                        </div>
                        <div class="sig-box text-gray-900 dark:text-white">
                            <div class="sig-line border-black dark:border-gray-700"></div>
                            <span>Headmaster Signature</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 2. TABULATION MATRIX 🌟 -->
            <table class="gazette-tabulation-table border-black dark:border-gray-800">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-bold uppercase border-b border-black">
                    <tr>
                        <th style="width: 35px;">Sl</th>
                        <th style="width: 150px;" class="text-left">Name / ID / Roll</th>
                        
                        @foreach($subjects as $subject)
                            <th style="width: 90px; padding: 4px; word-wrap: break-word;">
                                <div class="font-extrabold text-[11px] leading-tight">{{ $subject->name }}</div>
                            </th>
                        @endforeach

                        <th style="width: 50px;" class="bg-gray-100 dark:bg-gray-800 font-bold">Total</th>
                        <th style="width: 45px;" class="bg-gray-100 dark:bg-gray-800 font-bold">GPA</th>
                        <th style="width: 45px;" class="bg-gray-100 dark:bg-gray-800 font-bold">Grade</th>
                        <th style="width: 45px;" class="bg-gray-100 dark:bg-gray-800 font-bold">Position</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $loopIndex => $enrollment)
                        @php
                            $student = $enrollment->user;
                            $studentReligion = strtolower(trim($student->religion ?? ''));
                            
                            $studentGrandTotal = 0;
                            $hasFailed = false;
                            $subjectCount = 0;
                            $gpaSum = 0;

                            $rankIndex = $peerTotals->search(fn($item) => $item->student_id == $student->id);
                            $position = ($rankIndex !== false) ? ($rankIndex + 1) : '--';

                            $isPageBreak = (($loopIndex + 1) % $rowsPerPage === 0) && !$loop->last;
                        @endphp
                        <tr class="{{ $isPageBreak ? 'print-page-break' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="font-mono font-bold text-center text-gray-900 dark:text-gray-200 text-xs">
                                {{ sprintf('%02d', $loopIndex + 1) }}
                            </td>
                            
                            <td class="text-left px-2 leading-tight py-2 border-r border-black dark:border-gray-800">
                                <div class="font-extrabold text-gray-900 dark:text-white uppercase text-[11px] truncate">{{ $student->name }}</div>
                                <div class="text-[9.5px] text-blue-600 dark:text-blue-400 font-mono font-bold mt-0.5">ID : {{ $student->student_id }}</div>
                                <div class="text-[9.5px] text-gray-900 dark:text-gray-200 font-bold font-mono mt-0.5">Roll : {{ $enrollment->roll_number }}</div>
                            </td>

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
                                        ? \App\Models\Mark::where('student_id', $enrollment->user_id)
                                            ->where('exam_id', $this->data['exam_id'])
                                            ->where('subject_id', $subject->id)
                                            ->first()
                                        : null;

                                    if ($mark && !$religionMismatch && !$optionalMismatch) {
                                        $studentGrandTotal += $mark->marks_obtained;
                                        $gpaSum += (float)$mark->gpa;
                                        $subjectCount++;
                                        if ($mark->grade === 'F') {
                                            $hasFailed = true;
                                        }
                                    }
                                @endphp

                                @if($religionMismatch || $optionalMismatch)
                                    <td class="bg-gray-50/40 dark:bg-gray-800/40 text-gray-400 dark:text-gray-600 text-center font-mono">-</td>
                                @else
                                    <td class="px-1.5 py-1 text-left font-mono text-[10px] font-bold leading-snug bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                                        @if($subject->written_total > 0)
                                            <div>Writ: {{ $mark ? (int)$mark->written_mark : 0 }}</div>
                                        @endif
                                        @if($subject->mcq_total > 0)
                                            <div>MCQ: {{ $mark ? (int)$mark->mcq_mark : 0 }}</div>
                                        @endif
                                        @if($subject->practical_total > 0)
                                            <div>Prac: {{ $mark ? (int)$mark->practical_mark : 0 }}</div>
                                        @endif
                                        <div class="font-extrabold border-t border-black dark:border-gray-700 mt-0.5 pt-0.5">Tot: {{ $mark ? (int)$mark->marks_obtained : 0 }}</div>
                                        
                                        <div class="{{ $mark && $mark->grade === 'F' ? 'text-danger-600 dark:text-danger-400 font-extrabold' : 'font-extrabold' }}">
                                            GP: {{ $mark ? number_format($mark->gpa, 2) : '0.00' }}/{{ $mark ? $mark->grade : 'F' }}
                                        </div>
                                    </td>
                                @endif
                            @endforeach

                            @php
                                $finalGPA = ($hasFailed || $subjectCount === 0) ? '0.00' : number_format($gpaSum / $subjectCount, 2);
                                $finalGrade = $hasFailed ? 'F' : ($finalGPA == '5.00' ? 'A+' : ($finalGPA >= '4.00' ? 'A' : ($finalGPA >= '3.50' ? 'A-' : ($finalGPA >= '3.00' ? 'B' : ($finalGPA >= '2.00' ? 'C' : 'D')))));
                            @endphp

                            <td class="font-mono font-extrabold text-center bg-gray-50/50 dark:bg-gray-800/50 text-gray-900 dark:text-white text-sm">
                                {{ $studentGrandTotal }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 dark:bg-gray-800/50 text-sm {{ $hasFailed ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                                {{ $finalGPA }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 dark:bg-gray-800/50 text-sm {{ $hasFailed ? 'text-danger-600 dark:text-danger-400 font-black' : 'text-success-600 dark:text-success-400' }}">
                                {{ $finalGrade }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 dark:bg-gray-800/50 text-blue-600 dark:text-blue-400 text-sm">
                                {{ $position }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        /* --- COVER PAGE WEB STYLES --- */
        .tabulation-cover-page {
            border-width: 2px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            font-family: 'Times New Roman', Times, serif;
        }
        .cover-school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            object-fit: contain;
        }
        .cover-logo-badge {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #0f172a;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin: 0 auto;
        }
        .cover-school-name {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0.5px;
        }
        .cover-subheading {
            font-size: 14px;
        }
        .cover-divider {
            height: 2px;
            width: 60%;
            margin: 15px auto;
        }
        .cover-doc-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .cover-exam-name {
            font-size: 16px;
            font-weight: 700;
            margin-top: 5px;
        }
        .cover-meta-grid {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .meta-card {
            border-width: 1px;
            padding: 10px 20px;
            min-width: 130px;
            border-radius: 4px;
        }
        .meta-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .meta-value {
            font-size: 16px;
            font-weight: bold;
        }
        .cover-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 30px;
        }
        .sig-box {
            width: 25%;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
        }
        .sig-line {
            border-top-width: 1px;
            margin-bottom: 6px;
        }

        /* --- DISPLAY TABLE STYLES --- */
        .gazette-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            border-width: 1px;
            table-layout: fixed;
            font-family: 'Times New Roman', Times, serif !important;
        }
        .gazette-tabulation-table th, 
        .gazette-tabulation-table td {
            border-width: 1px;
            font-family: 'Times New Roman', Times, serif !important;
        }

        /* 🌟 STRICT PRINT RESET (OVERRIDE DARK THEME FOR PREVIEW & PRINTER) 🌟 */
        @media print {
            :root, html, body {
                color-scheme: light !important;
            }

            @page {
                size: A4 landscape;
                margin: 4mm 3mm;
            }

            .no-print, 
            nav, 
            header, 
            aside, 
            .fi-sidebar, 
            .fi-topbar, 
            .fi-header, 
            .fi-breadcrumbs, 
            .fi-actions { 
                display: none !important; 
            }

            /* FORCE PURE WHITE BACKGROUND FOR ALL PRINT ELEMENTS */
            html, body, main, .fi-main, .fi-content, .fi-page, .print-container, 
            .print-container *, .tabulation-cover-page, .meta-card, 
            .gazette-tabulation-table, tr, td, th { 
                background-color: #ffffff !important; 
                background: #ffffff !important;
                color: #000000 !important;
                border-color: #000000 !important;
                box-shadow: none !important;
                text-shadow: none !important;
                font-family: 'Times New Roman', Times, serif !important;
            }

            .print-container {
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            /* COVER PAGE */
            .tabulation-cover-page {
                page-break-after: always !important;
                break-after: page !important;
                height: 95vh !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 20px !important;
                border: 2px solid #000000 !important;
            }

            .meta-card {
                border: 1px solid #000000 !important;
            }

            .cover-divider {
                border-top: 2px solid #000000 !important;
            }

            .sig-line {
                border-top: 1px solid #000000 !important;
            }

            /* TABLE */
            .gazette-tabulation-table {
                width: 100% !important;
                font-size: 8.5px !important;
                border-collapse: collapse !important;
                table-layout: auto !important;
            }

            .gazette-tabulation-table thead {
                display: table-header-group !important;
            }

            .gazette-tabulation-table tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .print-page-break {
                page-break-after: always !important;
                break-after: page !important;
            }

            .gazette-tabulation-table th {
                border: 0.5px solid #000000 !important;
                padding: 3px 1px !important;
                font-size: 8.5px !important;
                font-weight: 800 !important;
                word-break: break-word !important;
                background-color: #f1f5f9 !important;
            }

            .gazette-tabulation-table td {
                border: 0.5px solid #000000 !important;
                padding: 2px 1px !important;
                font-size: 8.5px !important;
                font-weight: 800 !important;
                line-height: 1.15 !important;
            }
        }
    </style>
</x-filament-panels::page>
