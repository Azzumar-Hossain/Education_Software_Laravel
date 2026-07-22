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

            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();
            
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;

            $rowsPerPage = (int) ($this->data['rows_per_page'] ?? 7);

            $examModel = \App\Models\Exam::find($examId);
            $yearModel = \App\Models\AcademicYear::find($academicYearId);
            $classModel = \App\Models\SchoolClass::find($classId);
            $sectionModel = !empty($this->data['section_id']) ? \App\Models\Section::find($this->data['section_id']) : null;
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Tabulation Matrix</h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Tabulation Sheet
                </x-filament::button>
            </div>

            <!-- 🌟 1. DEDICATED PRINT COVER PAGE (PAGE 1) 🌟 -->
            <div class="tabulation-cover-page">
                <div class="cover-content">
                    <div class="cover-logo-wrapper mb-4">
                        @if($logoPath)
                            <img src="{{ $logoPath }}" alt="School Logo" class="cover-school-logo">
                        @else
                            <div class="cover-logo-badge">HM</div>
                        @endif
                    </div>

                    <h1 class="cover-school-name">Harimohan Govt. High School</h1>
                    <p class="cover-subheading">Chapai Nawabganj, Bangladesh</p>

                    <div class="cover-divider my-6"></div>

                    <h2 class="cover-doc-title">TABULATION SHEET</h2>
                    <h3 class="cover-exam-name">{{ $examModel?->name ?? 'ACADEMIC' }} EXAMINATION - {{ $yearModel?->name ?? '2026' }}</h3>

                    <div class="cover-meta-grid my-8">
                        <div class="meta-card">
                            <span class="meta-label">Class</span>
                            <span class="meta-value">{{ $classModel?->name ?? 'N/A' }}</span>
                        </div>
                        @if($sectionModel)
                            <div class="meta-card">
                                <span class="meta-label">Section</span>
                                <span class="meta-value">{{ $sectionModel->name }}</span>
                            </div>
                        @endif
                        @if(!empty($this->data['study_group']))
                            <div class="meta-card">
                                <span class="meta-label">Group</span>
                                <span class="meta-value">{{ $this->data['study_group'] }}</span>
                            </div>
                        @endif
                        <div class="meta-card">
                            <span class="meta-label">Total Students</span>
                            <span class="meta-value">{{ count($students) }}</span>
                        </div>
                    </div>

                    <div class="cover-signatures mt-16">
                        <div class="sig-box">
                            <div class="sig-line"></div>
                            <span>Prepared By</span>
                        </div>
                        <div class="sig-box">
                            <div class="sig-line"></div>
                            <span>Exam Committee Controller</span>
                        </div>
                        <div class="sig-box">
                            <div class="sig-line"></div>
                            <span>Headmaster Signature</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 3. TABULATION MATRIX (STARTS EXACTLY AT THE TOP OF PAGE 2) 🌟 -->
            <table class="gazette-tabulation-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th style="width: 35px;">Sl</th>
                        <th style="width: 150px;" class="text-left">Name / ID / Roll</th>
                        
                        @foreach($subjects as $subject)
                            <th style="width: 90px; padding: 4px; word-wrap: break-word;">
                                <div class="font-extrabold text-[11px] leading-tight">{{ $subject->name }}</div>
                            </th>
                        @endforeach

                        <th style="width: 50px;" class="bg-gray-100 font-bold">Total</th>
                        <th style="width: 45px;" class="bg-gray-100 font-bold">GPA</th>
                        <th style="width: 45px;" class="bg-gray-100 font-bold">Grade</th>
                        <th style="width: 45px;" class="bg-gray-100 font-bold">Position</th>
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
                        <tr class="{{ $isPageBreak ? 'print-page-break' : '' }}">
                            <td class="font-mono font-bold text-center text-gray-700 dark:text-gray-300 text-xs">
                                {{ sprintf('%02d', $loopIndex + 1) }}
                            </td>
                            
                            <td class="text-left px-2 leading-tight py-2 border-r border-black">
                                <div class="font-extrabold text-gray-900 dark:text-white uppercase text-[11px] truncate">{{ $student->name }}</div>
                                <div class="text-[9.5px] text-blue-600 font-mono font-bold mt-0.5">ID : {{ $student->student_id }}</div>
                                <div class="text-[9.5px] text-gray-900 font-bold font-mono mt-0.5">Roll : {{ $enrollment->roll_number }}</div>
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
                                    <td class="bg-gray-50/40 text-gray-300 text-center font-mono">-</td>
                                @else
                                    <td class="px-1.5 py-1 text-left font-mono text-[10px] font-bold leading-snug bg-white dark:bg-gray-900 text-black dark:text-white">
                                        @if($subject->written_total > 0)
                                            <div>Writ: {{ $mark ? (int)$mark->written_mark : 0 }}</div>
                                        @endif
                                        @if($subject->mcq_total > 0)
                                            <div>MCQ: {{ $mark ? (int)$mark->mcq_mark : 0 }}</div>
                                        @endif
                                        @if($subject->practical_total > 0)
                                            <div>Prac: {{ $mark ? (int)$mark->practical_mark : 0 }}</div>
                                        @endif
                                        <div class="font-extrabold border-t border-black mt-0.5 pt-0.5">Tot: {{ $mark ? (int)$mark->marks_obtained : 0 }}</div>
                                        
                                        <!-- 🌟 COMBINED GP AND GRADE IN ONE LINE 🌟 -->
                                        <div class="{{ $mark && $mark->grade === 'F' ? 'text-danger-600 font-extrabold' : 'font-extrabold' }}">
                                            GP: {{ $mark ? number_format($mark->gpa, 2) : '0.00' }}/{{ $mark ? $mark->grade : 'F' }}
                                        </div>
                                    </td>
                                @endif
                            @endforeach

                            @php
                                $finalGPA = ($hasFailed || $subjectCount === 0) ? '0.00' : number_format($gpaSum / $subjectCount, 2);
                                $finalGrade = $hasFailed ? 'F' : ($finalGPA == '5.00' ? 'A+' : ($finalGPA >= '4.00' ? 'A' : ($finalGPA >= '3.50' ? 'A-' : ($finalGPA >= '3.00' ? 'B' : ($finalGPA >= '2.00' ? 'C' : 'D')))));
                            @endphp

                            <td class="font-mono font-extrabold text-center bg-gray-50/50 text-gray-900 dark:text-white text-sm">
                                {{ $studentGrandTotal }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 text-sm {{ $hasFailed ? 'text-danger-600' : 'text-success-600' }}">
                                {{ $finalGPA }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 text-sm {{ $hasFailed ? 'text-danger-600 font-black' : 'text-success-600' }}">
                                {{ $finalGrade }}
                            </td>
                            <td class="font-mono font-extrabold text-center bg-gray-50/50 text-blue-600 text-sm">
                                {{ $position }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        /* --- COVER PAGE STYLES --- */
        .tabulation-cover-page {
            border: 2px solid #000;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            background: #fff;
            color: #000;
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
            color: #444;
        }
        .cover-divider {
            height: 2px;
            background: #000;
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
            border: 1px solid #000;
            padding: 10px 20px;
            min-width: 130px;
            border-radius: 4px;
        }
        .meta-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
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
            border-top: 1px solid #000;
            margin-bottom: 6px;
        }

        /* --- DISPLAY HEADER STYLES --- */
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

        /* --- DISPLAY TABLE STYLES --- */
        .gazette-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            border: 1px solid #000000;
            table-layout: fixed;
            font-family: 'Times New Roman', Times, serif !important;
        }
        .gazette-tabulation-table th, 
        .gazette-tabulation-table td {
            border: 1px solid #000000;
            color: #000;
            font-family: 'Times New Roman', Times, serif !important;
        }

        /* --- PHYSICAL PRINT MEDIA SPECIFICATIONS --- */
        @media print {
            @page {
                size: A4 landscape;
                margin: 4mm 3mm;
            }
            .no-print, header, sidebar, nav, .fi-sidebar, .fi-topbar, form, .no-print-header { 
                display: none !important; 
            }
            body, .fi-main, .fi-content, main, .fi-layout { 
                background: white !important; 
                padding: 0 !important; 
                margin: 0 !important;
                width: 100% !important;
                font-family: 'Times New Roman', Times, serif !important;
            }
            .print-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }

            /* 🌟 ISOLATE COVER PAGE ON PAGE 1 ONLY 🌟 */
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
            }

            /* TABLE STARTS CLEANLY AT TOP OF PAGE 2 */
            .gazette-tabulation-table {
                width: 100% !important;
                font-size: 8.5px !important;
                font-family: 'Times New Roman', Times, serif !important;
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
                color: #000000 !important;
                font-size: 8.5px !important;
                font-weight: 800 !important;
                word-break: break-word !important;
            }
            .gazette-tabulation-table td {
                border: 0.5px solid #000000 !important;
                padding: 2px 1px !important;
                color: #000000 !important;
                font-size: 8.5px !important;
                font-weight: 800 !important;
                line-height: 1.15 !important;
            }
        }
    </style>
</x-filament-panels::page>