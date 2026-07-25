<x-filament-panels::page>
    <form wire:submit.prevent="processArrangement" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="success" icon="heroicon-m-squares-plus">
                Generate Smart Seat Plan
            </x-filament::button>
        </div>
    </form>

    @if(count($previewSeats) > 0)
        @php
            // 🌟 DYNAMIC SITE SETTINGS FETCH 🌟
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() 
                ?? \App\Models\Setting::first();
            
            $schoolName = !empty($siteSetting?->school_name_en) 
                ? $siteSetting->school_name_en 
                : 'Shankarbati High School';

            $schoolAddress = !empty($siteSetting?->address_en) 
                ? $siteSetting->address_en 
                : 'Chapai Nawabganj, Bangladesh';

            $logoPath = ($siteSetting && !empty($siteSetting->logo)) 
                ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) 
                : null;

            $examName = \App\Models\Exam::find($this->data['exam_id'])?->name ?? 'Examination';
            $roomName = $this->data['room_name'] ?? $this->data['room_number'] ?? 'Auto Allocated';
        @endphp

        <div class="space-y-8 no-print-wrapper-container">
            <div class="flex justify-between items-center bg-gray-50 dark:bg-white/5 p-4 rounded-xl border border-gray-100 dark:border-white/10 no-print">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Print Options</span>
                <div class="flex gap-3">
                    <x-filament::button onclick="printSection('notice-board-view')" color="gray" icon="heroicon-m-document-text">
                        Print Notice Board List
                    </x-filament::button>
                    <x-filament::button onclick="printSection('desk-slips-view')" color="info" icon="heroicon-m-tag">
                        Print Bench Desk Slips
                    </x-filament::button>
                </div>
            </div>

            <!-- 🌟 NOTICE BOARD VIEW 🌟 -->
            <div id="notice-board-view" class="print-target-section bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl p-6">
                <div class="text-center border-b-2 border-black pb-4 mb-6 notice-header-print">
                    @if($logoPath)
                        <div class="flex justify-center mb-3">
                            <img src="{{ $logoPath }}" alt="School Logo" class="h-16 w-16 object-contain" style="max-height: 60px; max-width: 60px;">
                        </div>
                    @endif
                    <h1 class="text-2xl font-black uppercase tracking-tight text-black dark:text-white serif-font">{{ $schoolName }}</h1>
                    <p class="text-xs text-gray-600 dark:text-gray-400 font-medium mb-1">{{ $schoolAddress }}</p>
                    <p class="text-sm font-bold text-gray-600 tracking-wide uppercase">{{ $examName }} — Seating Arrangement</p>
                    <div class="inline-block mt-3 bg-black text-white px-4 py-1 text-xs font-black tracking-widest rounded uppercase">
                        ROOM: {{ $roomName }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="master-notice-table">
                        <thead>
                            <tr>
                                <th class="print-hide-column" style="width: 15%">Bench No.</th>
                                <th class="text-center">Seat Position 1</th>
                                <th class="text-center">Seat Position 2</th>
                                @if((int)$this->data['formation'] === 3)
                                    <th class="text-center">Seat Position 3</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previewSeats as $benchNo => $seats)
                                <tr>
                                    <td class="font-bold font-mono text-center bg-slate-50 border-r-2 border-slate-300 bench-num-col print-hide-column" style="color:#000; vertical-align: middle;">
                                        Bench {{ sprintf('%02d', $benchNo) }}
                                    </td>
                                    @for($p = 1; $p <= (int)$this->data['formation']; $p++)
                                        @php 
                                            $seatDetails = collect($seats)->firstWhere('position', $p); 
                                        @endphp
                                        <td>
                                            @if($seatDetails)
                                                <div class="seat-cell-inner">
                                                    <span class="c-badge">{{ $seatDetails['class_name'] }}</span>
                                                    <span class="s-name font-bold text-black">{{ $seatDetails['student_name'] }}</span>
                                                    <div class="flex justify-between items-center mt-1 text-xxs font-mono text-gray-700 data-row-meta">
                                                        <span>ID: {{ $seatDetails['student_id'] }}</span>
                                                        <span class="font-bold">Roll: {{ $seatDetails['roll'] }}</span>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-gray-300 text-xs italic tracking-wider block text-center py-2 vacant-text">Vacant Position</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 🌟 DESK SLIPS VIEW 🌟 -->
            <div id="desk-slips-view" class="print-target-section hidden-on-screen">
                <div class="slips-flex-wrap">
                    @foreach($previewSeats as $benchNo => $seats)
                        @foreach($seats as $seatDetails)
                            <div class="individual-desk-slip">
                                <div class="slip-header-brand">{{ $schoolName }}</div>
                                <div class="slip-exam-sub">{{ $examName }}</div>
                                
                                <table class="slip-data-matrix">
                                    <tr>
                                        <td class="sl-lbl">Name:</td>
                                        <td class="sl-val font-bold uppercase text-sm" colspan="3">{{ $seatDetails['student_name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td class="sl-lbl">ID:</td>
                                        <td class="sl-val font-mono">{{ $seatDetails['student_id'] }}</td>
                                        <td class="sl-lbl">Roll:</td>
                                        <td class="sl-val font-mono font-black text-base">{{ sprintf('%02d', $seatDetails['roll']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="sl-lbl">Class:</td>
                                        <td class="sl-val font-bold">{{ $seatDetails['class_name'] }}</td>
                                        <td class="sl-lbl">Room:</td>
                                        <td class="sl-val font-bold text-slate-800">{{ $roomName }}</td>
                                    </tr>
                                </table>
                                
                                <div class="slip-footer-meta">Bench: #{{ sprintf('%02d', $benchNo) }} — Pos: {{ $seatDetails['position'] }}</div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <style>
        /* --- CORE SCREEN PREVIEW GRAPHICS --- */
        .serif-font { font-family: serif; }
        .text-xxs { font-size: 0.65rem; }
        .hidden-on-screen { display: none; }
        
        .master-notice-table { width: 100%; border-collapse: collapse; color: #000000; }
        .master-notice-table th { background: #0f172a; color: #ffffff; font-size: 11px; text-transform: uppercase; font-weight: bold; padding: 10px; border: 1px solid #1e293b; }
        .master-notice-table td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; background: #fff; }
        
        .seat-cell-inner { display: flex; flex-direction: column; text-align: left; }
        .c-badge { font-size: 9px; font-weight: 800; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 1px 5px; border-radius: 4px; display: inline-block; width: max-content; margin-bottom: 3px; color: #1e293b; text-transform: uppercase; }
        .s-name { font-size: 13px; color: #000000; line-height: 1.2; }

        /* --- DESK SLIP STYLING --- */
        .slips-flex-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; width: 100%; }
        .individual-desk-slip { 
            background: #ffffff; 
            border: 2px dashed #000000; 
            border-radius: 6px; 
            padding: 12px; 
            color: #000000;
            box-sizing: border-box;
        }
        .slip-header-brand { font-family: serif; font-size: 13px; font-weight: 900; text-align: center; border-bottom: 1px solid #000; padding-bottom: 2px; text-transform: uppercase; color: #000; }
        .slip-exam-sub { font-size: 10px; font-weight: bold; text-align: center; color: #000; margin: 3px 0; text-transform: uppercase; }
        .slip-data-matrix { width: 100%; margin-top: 5px; }
        .slip-data-matrix td { padding: 2px 4px; border: none !important; font-size: 11px; vertical-align: middle; color: #000 !important; text-align: left; }
        .sl-lbl { font-weight: bold; width: 15%; }
        .slip-footer-meta { margin-top: 10px; border-top: 1px dotted #000; padding-top: 3px; font-size: 9px; font-weight: 900; text-transform: uppercase; text-align: right; color: #000; }

        /* --- 🌟 BULLETPROOF PRINT RESET 🌟 --- */
        @media print {
            @page { size: A4 portrait; margin: 8mm 6mm; }

            /* 1. Force the absolute base to be pure white */
            :root { color-scheme: light !important; }
            html, body {
                background: #ffffff !important;
                background-color: #ffffff !important;
            }

            /* 2. Hide Filament UI entirely and inactive print sections */
            .no-print, header, aside, nav, .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs, form,
            .print-target-section:not(.print-active-target) { 
                display: none !important; 
            }

            /* 3. Strip ALL backgrounds from EVERY wrapper element to kill the gray overlay */
            *, *::before, *::after {
                background: transparent !important;
                background-color: transparent !important;
                color: #000000 !important; /* Force ALL text to black */
                border-color: #000000 !important; /* Force ALL borders to black */
                box-shadow: none !important;
                text-shadow: none !important;
            }

            /* 4. Destroy scroll-locks preventing multi-page print */
            html, body, .fi-layout, .fi-main, .fi-content, .fi-page, .no-print-wrapper-container, .print-active-target {
                height: auto !important;
                min-height: 0 !important;
                overflow: visible !important;
                position: static !important;
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* ------------------------------------- */
            /* NOTICE BOARD PRINT SPECS              */
            /* ------------------------------------- */
            .print-hide-column { display: none !important; }
            
            table.master-notice-table { width: 100% !important; border-collapse: collapse !important; }
            table.master-notice-table th { 
                background: #e2e8f0 !important; /* Restore header gray box */
                background-color: #e2e8f0 !important;
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                font-weight: bold !important; 
                border: 1.5px solid #000000 !important; 
            }
            table.master-notice-table td { 
                border: 1.5px solid #000000 !important; 
            }
            
            .c-badge { border: 1px solid #000000 !important; }
            .vacant-text { color: #888888 !important; } /* Soften empty slots */

            /* ------------------------------------- */
            /* DESK SLIPS PRINT SPECS                */
            /* ------------------------------------- */
            .print-active-slips-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 10mm !important;
                width: 100% !important;
            }
            .individual-desk-slip {
                border: 1.5px dashed #000000 !important;
                height: 60mm !important;
                page-break-inside: avoid !important;
            }
        }
    </style>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('refreshComponent', () => {});
        });

        function printSection(sectionId) {
            document.querySelectorAll('.print-target-section').forEach(el => {
                el.classList.remove('print-active-target', 'print-active-slips-grid');
            });

            const target = document.getElementById(sectionId);
            if (!target) return;

            target.classList.add('print-active-target');

            if (sectionId === 'desk-slips-view') {
                target.classList.add('print-active-slips-grid');
            }

            setTimeout(() => {
                window.print();
            }, 50);
        }
    </script>
</x-filament-panels::page>
