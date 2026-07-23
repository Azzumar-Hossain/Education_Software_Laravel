<x-filament-panels::page>
    <form wire:submit.prevent="generateMeritList" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="warning" icon="heroicon-m-trophy">
                Generate Merit Rankings
            </x-filament::button>
        </div>
    </form>

    @if(count($meritRecords) > 0)
        @php
            $siteSetting = \App\Models\SiteSetting::first() 
                ?? \Illuminate\Support\Facades\DB::table('site_settings')->first();
            
            $schoolName = !empty($siteSetting?->school_name_en) 
                ? $siteSetting->school_name_en 
                : 'Nayagola High School';

            $logoPath = (!empty($siteSetting?->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;

            $academicYear = \App\Models\AcademicYear::find($this->data['academic_year_id'] ?? null)?->name ?? '2026';
            
            $classModel = \App\Models\SchoolClass::find($this->data['school_class_id'] ?? null);
            $schoolClass = $classModel?->name ?? 'N/A';
            
            $sectionModel = !empty($this->data['section_id']) ? \App\Models\Section::find($this->data['section_id']) : null;
            $section = $sectionModel?->name ?? 'N/A';
            
            $scope = $this->data['merit_scope'] ?? 'section';

            // Filter passed students only
            $passedRecords = array_filter($meritRecords, function($record) {
                return !($record['is_failed'] ?? false) && ($record['final_grade'] ?? 'F') !== 'F';
            });
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm mt-6 print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Scope Target: {{ ucfirst($scope) }}-wise Merit List
                </span>
                <x-filament::button onclick="window.print()" color="warning" icon="heroicon-m-printer">
                    Print Merit List
                </x-filament::button>
            </div>

            <!-- 🌟 DARK & LIGHT ADAPTIVE SCHOOL HEADER 🌟 -->
            <div class="text-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800 print-header">
                <div class="flex justify-center items-center gap-3 mb-1">
                    @if($logoPath)
                        <img 
                            src="{{ $logoPath }}" 
                            alt="Logo" 
                            style="width: 50px !important; height: 50px !important; max-width: 50px !important; object-fit: contain !important;"
                        >
                    @endif
                    
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white font-serif tracking-tight print-school-title">
                        {{ $schoolName }}
                    </h1>
                </div>

                <h2 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mt-1">
                    Merit List REPORT: {{ $academicYear }}
                </h2>

                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mt-1">
                    Class: <span class="font-bold text-gray-900 dark:text-gray-200">{{ $schoolClass }}</span> 
                    | Section: <span class="font-bold text-gray-900 dark:text-gray-200">{{ $section }}</span> 
                    | Scope Filter: <span class="font-bold text-gray-900 dark:text-gray-200">{{ ucfirst($scope) }}</span>
                </div>
            </div>

            <!-- 🌟 ADAPTIVE MERIT TABLE 🌟 -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse border border-gray-200 dark:border-gray-800">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-bold uppercase border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Rank</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Student ID</th>
                            <th class="py-2.5 px-2 border-r border-gray-200 dark:border-gray-700">Student Name</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Roll No.</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Section</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Group</th>
                            <th class="py-2.5 px-2 text-right border-r border-gray-200 dark:border-gray-700">Combined Total Marks</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Final GPA</th>
                            <th class="py-2.5 px-2 text-center border-r border-gray-200 dark:border-gray-700">Grade</th>
                            <th class="py-2.5 px-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 font-mono text-gray-900 dark:text-gray-100">
                        @forelse($passedRecords as $index => $record)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-2 px-2 text-center font-extrabold border-r border-gray-200 dark:border-gray-800">{{ $index + 1 }}</td>
                                <td class="py-2 px-2 text-center border-r border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400">{{ $record['student_id'] }}</td>
                                <td class="py-2 px-2 font-bold border-r border-gray-200 dark:border-gray-800 font-sans">{{ $record['student_name'] }}</td>
                                <td class="py-2 px-2 text-center border-r border-gray-200 dark:border-gray-800">{{ $record['roll_number'] }}</td>
                                <td class="py-2 px-2 text-center border-r border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400">{{ $record['section_name'] }}</td>
                                <td class="py-2 px-2 text-center font-bold border-r border-gray-200 dark:border-gray-800">{{ $record['group_name'] }}</td>
                                <td class="py-2 px-2 text-right font-extrabold border-r border-gray-200 dark:border-gray-800">{{ number_format($record['total_marks'], 2) }}</td>
                                <td class="py-2 px-2 text-center font-extrabold text-success-600 dark:text-success-400 border-r border-gray-200 dark:border-gray-800">{{ $record['final_gpa'] }}</td>
                                <td class="py-2 px-2 text-center font-extrabold text-success-600 dark:text-success-400 border-r border-gray-200 dark:border-gray-800">{{ $record['final_grade'] }}</td>
                                <td class="py-2 px-2 text-center font-bold font-sans text-success-600 dark:text-success-400">☑ Passed</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-4 text-center text-gray-500 dark:text-gray-400 font-sans">
                                    No passed students found for this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    @endif

    <style>
        /* --- PRINT MEDIA SPECIFICATIONS (PURE BLACK & WHITE FOR PAPER) --- */
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            .no-print, form, header, sidebar, nav, .fi-sidebar, .fi-topbar, .fi-header, .fi-actions { 
                display: none !important; 
            }

            body, html, .fi-main, .fi-content, main, .fi-layout, .print-container { 
                background: white !important; 
                padding: 0 !important; 
                margin: 0 !important;
                width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                overflow: visible !important;
                color: #000000 !important;
            }

            .print-header {
                display: block !important;
                margin-bottom: 15px !important;
                border-bottom: 1px solid #000000 !important;
            }

            .print-school-title, h1, h2, div, span {
                color: #000000 !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9.5px !important;
            }

            th, td {
                border: 0.5px solid #000000 !important;
                padding: 3px 5px !important;
                color: #000000 !important;
                background: transparent !important;
            }

            thead {
                display: table-header-group !important;
                background-color: #f1f5f9 !important;
            }
        }
    </style>
</x-filament-panels::page>