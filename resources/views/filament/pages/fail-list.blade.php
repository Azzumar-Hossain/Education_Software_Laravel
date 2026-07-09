<x-filament-panels::page>
    <form wire:submit.prevent="generateFailList" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="danger" icon="heroicon-m-x-circle">
                Generate Fail List
            </x-filament::button>
        </div>
    </form>

    @if(count($failedRecords) > 0)
        @php
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();
            
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;
        @endphp

        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-x-auto print-container">
            
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">
                    Target: {{ ucfirst($this->data['merit_scope'] ?? 'Class') }}-wise Failed Students Ledger
                </h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Fail List Gazette
                </x-filament::button>
            </div>

            <!-- OFFICIAL GAZETTE BRAND HEADER -->
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
                        Unsuccessful Students Ledger Report: 
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

            <table class="w-full text-center text-sm border-collapse border border-black text-gray-900 dark:text-gray-100">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-bold">
                        <th class="border border-black p-2.5 w-12">Sl</th>
                        <th class="border border-black p-2.5 w-28">Student ID</th>
                        <th class="border border-black p-2.5 text-left w-52">Student Name</th>
                        <th class="border border-black p-2.5 w-20">Roll No.</th>
                        <th class="border border-black p-2.5 w-20">Section</th>
                        <th class="border border-black p-2.5 w-24">Group</th>
                        <th class="border border-black p-2.5 text-left text-danger-600 font-bold">Failed Subjects (Count)</th>
                        <th class="border border-black p-2.5 w-28">Total Marks</th>
                        <th class="border border-black p-2.5 w-20">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    @foreach($failedRecords as $index => $row)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 bg-rose-50/10">
                            <td class="border border-black p-2.5 font-bold font-mono">{{ sprintf('%02d', $index + 1) }}</td>
                            <td class="border border-black p-2.5 font-mono text-gray-500">{{ $row['student_id'] }}</td>
                            <td class="border border-black p-2.5 text-left font-semibold uppercase">{{ $row['student_name'] }}</td>
                            <td class="border border-black p-2.5 font-mono font-bold">{{ $row['roll_number'] }}</td>
                            <td class="border border-black p-2.5 text-gray-600 font-medium">{{ $row['section_name'] }}</td>
                            <td class="border border-black p-2.5 font-medium">{{ $row['group_name'] }}</td>
                            <td class="border border-black p-2.5 text-left font-mono font-bold text-danger-600 text-xs">
                                <span class="bg-rose-100 dark:bg-rose-950 px-1.5 py-0.5 rounded text-rose-700 mr-2 font-sans font-black">
                                    {{ $row['failed_count'] }}
                                </span>
                                {{ $row['failed_list'] }}
                            </td>
                            <td class="border border-black p-2.5 font-bold font-mono text-gray-600">{{ $row['total_marks'] }}</td>
                            <td class="border border-black p-2.5">
                                <span class="text-danger-600 font-black uppercase text-xs tracking-wider">Retained</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        @if(count($data) > 0)
            <div class="p-4 bg-emerald-50 text-emerald-800 font-bold text-center rounded-xl border border-emerald-200">
                🎉 No student failed within the selected filtering criteria. 100% Pass Rate!
            </div>
        @endif
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
            table { width: 100% !important; border-collapse: collapse !important; border: 0.5px solid #000000 !important; }
            th, td { padding: 4px 6px !important; border: 0.5px solid #000000 !important; color: #000000 !important; font-size: 10px !important; }
        }
    </style>
</x-filament-panels::page>