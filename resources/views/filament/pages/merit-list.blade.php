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
            // 🌟 FETCH LOGO AND PARAMETERS FOR UNIFIED SYSTEM BRANDING
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();
            
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">
                    Scope Target: {{ ucfirst($this->data['merit_scope'] ?? 'Class') }}-wise Standings Ledger
                </h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Merit Standings Gazette
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
                        Final Merit Standings Report: 
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
                        | Scope Filter: <span class="font-bold text-gray-900 dark:text-white uppercase">{{ $this->data['merit_scope'] }}</span>
                    </div>
                </div>
            </div>

            <table class="w-full text-center text-sm border-collapse border border-black text-gray-900 dark:text-gray-100">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-bold">
                        <th class="border border-black p-2.5">Rank</th>
                        <th class="border border-black p-2.5">Student ID</th>
                        <th class="border border-black p-2.5 text-left">Student Name</th>
                        <th class="border border-black p-2.5">Roll No.</th>
                        <th class="border border-black p-2.5">Section</th>
                        <th class="border border-black p-2.5">Group</th>
                        <th class="border border-black p-2.5">Combined Total Marks</th>
                        <th class="border border-black p-2.5">Final GPA</th>
                        <th class="border border-black p-2.5">Grade</th>
                        <th class="border border-black p-2.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    @foreach($meritRecords as $index => $row)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 {{ $row['is_failed'] ? 'bg-rose-50/10 dark:bg-rose-950/10' : '' }}">
                            <td class="border border-black p-2.5 font-bold font-mono">
                                {{ $row['is_failed'] ? '-' : ($index + 1) }}
                            </td>
                            <td class="border border-black p-2.5 font-mono text-gray-500">{{ $row['student_id'] }}</td>
                            <td class="border border-black p-2.5 text-left font-semibold uppercase">{{ $row['student_name'] }}</td>
                            <td class="border border-black p-2.5 font-mono">{{ $row['roll_number'] }}</td>
                            <td class="border border-black p-2.5 text-gray-600 dark:text-gray-400 font-medium">{{ $row['section_name'] }}</td>
                            <td class="border border-black p-2.5 font-bold">{{ $row['group_name'] }}</td>
                            <td class="border border-black p-2.5 font-bold font-mono">{{ $row['total_marks'] }}</td>
                            <td class="border border-black p-2.5 font-bold font-mono {{ $row['is_failed'] ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $row['final_gpa'] }}
                            </td>
                            <td class="border border-black p-2.5 font-bold font-mono">{{ $row['final_grade'] }}</td>
                            <td class="border border-black p-2.5">
                                @if($row['is_failed'])
                                    <span class="text-rose-600 font-bold uppercase text-xs">❌ Retained</span>
                                @else
                                    <span class="text-emerald-600 font-bold uppercase text-xs">✅ Promoted</span>
                                @endif
                            </td>
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

        @media print {
            .no-print, header, sidebar, nav, .fi-sidebar, .fi-topbar, form { display: none !important; }
            body, .fi-main, .fi-content, main, .fi-layout { background: white !important; padding: 0 !important; margin: 0 !important; }
            .print-container { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .gazette-header-container { display: flex !important; border-bottom: 1.5px solid #000000 !important; }
            table { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000000 !important; }
            th, td { padding: 4px 6px !important; border: 0.5px solid #000000 !important; color: #000000 !important; font-size: 10px !important; }
        }
    </style>
</x-filament-panels::page>