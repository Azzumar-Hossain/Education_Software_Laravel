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
            $examName = \App\Models\Exam::find($this->data['exam_id'])?->name ?? 'Examination';
            $roomName = $this->data['room_number'];
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

            <div id="notice-board-view" class="print-target-section bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl p-6">
                <div class="text-center border-b-2 border-black pb-4 mb-6 notice-header-print">
                    <h1 class="text-2xl font-black uppercase tracking-tight text-black dark:text-white serif-font">Harimohan Govt. High School</h1>
                    <p class="text-sm font-bold text-gray-600 tracking-wide uppercase">{{ $examName }} — Seating Arrangement</p>
                    <div class="inline-block mt-3 bg-black text-white px-4 py-1 text-xs font-black tracking-widest rounded uppercase">
                        ROOM: {{ $roomName }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="master-notice-table">
                        <thead>
                            <tr>
                                <th style="width: 15%">Bench No.</th>
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
                                    <td class="font-bold font-mono text-center bg-slate-50 border-r-2 border-slate-300 bench-num-col" style="color:#000; vertical-align: middle;">
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

            <div id="desk-slips-view" class="print-target-section hidden-on-screen">
                <div class="slips-flex-wrap">
                    @foreach($previewSeats as $benchNo => $seats)
                        @foreach($seats as $seatDetails)
                            <div class="individual-desk-slip">
                                <div class="slip-header-brand">Harimohan Govt. High School</div>
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

        /* --- DESK SLIP SCILING GRAPHICS --- */
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

        /* --- 🌟 COMPLETE OVERHAUL PRINT INJECTION RULES --- */
        @media print {
            /* 1. Hide EVERY default Filament page block wrapper instantly */
            html, body, div, section, main, header, nav, aside {
                visibility: hidden !important;
                background: transparent !important;
                box-shadow: none !important;
                border: none !important;
            }

            /* 2. Un-hide ONLY our target elements container stack */
            .print-active-target, 
            .print-active-target * {
                visibility: visible !important;
            }

            /* 3. Force target block to anchor absolute top-left boundary corner */
            .print-active-target {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                display: block !important;
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* 4. Formatting variables override map for Notice List */
            table.master-notice-table { width: 100% !important; border-collapse: collapse !important; }
            table.master-notice-table th { background: #e2e8f0 !important; color: #000000 !important; border: 1.5px solid #000000 !important; font-weight: bold !important; visibility: visible !important; }
            table.master-notice-table td { background: #ffffff !important; color: #000000 !important; border: 1.5px solid #000000 !important; visibility: visible !important; }
            .bench-num-col { background: #f8fafc !important; border-right: 2px solid #000000 !important; color: #000000 !important; font-weight: bold !important; }
            .c-badge { border: 1px solid #000000 !important; background: transparent !important; color: #000000 !important; }
            .vacant-text { color: #cbd5e1 !important; }

            /* 5. Formatting variables override map for Desk Slips (Fits 8 tags cleanly per page grid) */
            .print-active-slips-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 10mm !important;
                width: 100% !important;
            }
            .print-active-slips-grid .individual-desk-slip {
                border: 1.5px dashed #000000 !important;
                height: 60mm !important;
                page-break-inside: avoid !important;
                display: block !important;
            }
        }
    </style>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('refreshComponent', () => {});
        });

        function printSection(sectionId) {
            // Drop any tracking flags across targets
            document.querySelectorAll('.print-target-section').forEach(el => {
                el.classList.remove('print-active-target', 'print-active-slips-grid');
            });

            const target = document.getElementById(sectionId);
            if (!target) return;

            target.classList.add('print-active-target');

            if (sectionId === 'desk-slips-view') {
                target.classList.add('print-active-slips-grid');
            }

            // Small timeout delay ensures browser execution threads sync up smoothly before firing frame
            setTimeout(() => {
                window.print();
            }, 50);
        }
    </script>
</x-filament-panels::page>