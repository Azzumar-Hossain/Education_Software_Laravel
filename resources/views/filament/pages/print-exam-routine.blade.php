<x-filament-panels::page>
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm;
            }

            html, body {
                background-color: #ffffff !important;
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 11px !important;
            }

            .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs, nav, form, button, .no-print {
                display: none !important;
            }

            .fi-main, main {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                background-color: #ffffff !important;
            }

            .printable-content {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background-color: #ffffff !important;
                box-shadow: none !important;
                border: none !important;
            }

            table {
                background-color: #ffffff !important;
                border-collapse: collapse !important;
                width: 100% !important;
                font-size: 10px !important;
            }

            th, td {
                background-color: #ffffff !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
                padding: 3px 6px !important;
                line-height: 1.2 !important;
            }

            th {
                font-weight: bold !important;
                text-transform: uppercase !important;
            }

            .header-container {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
                margin-bottom: 8px !important;
            }

            .school-logo {
                height: 48px !important;
                max-height: 48px !important;
                width: auto !important;
                object-fit: contain !important;
            }

            .signature-section {
                margin-top: 40px !important;
            }
        }
    </style>

    <div class="space-y-6">
        <!-- Form Section (Hidden when printing) -->
        <div class="no-print">
            <form wire:submit.prevent="generateRoutine" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-end gap-3 mt-4">
                    <x-filament::button type="submit" color="primary">
                        Generate Routine
                    </x-filament::button>

                    @if(!empty($routines) && count($routines) > 0)
                        <x-filament::button type="button" color="warning" icon="heroicon-o-printer" onclick="window.print()">
                            Print Routine
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>

        <!-- Printable Routine Table Section -->
        @if(!empty($routines) && count($routines) > 0)
            <div class="printable-content bg-white p-4 rounded-xl shadow-sm border border-gray-200 text-black">
                <!-- HEADER -->
                <div class="header-container flex items-center justify-center gap-3 mb-4 text-center">
                    @if($schoolLogo)
                        <img src="{{ $schoolLogo }}" alt="School Logo" class="school-logo h-12 w-auto object-contain" />
                    @endif
                    
                    <div class="text-left">
                        <h1 class="text-xl font-bold uppercase tracking-wide leading-tight">
                            {{ $schoolName ?? 'Harimohan Govt. High School' }}
                        </h1>
                        <h2 class="text-xs font-semibold text-gray-800 uppercase tracking-wider">
                            {{ $routines->first()->exam->name ?? 'EXAM' }} ROUTINE - {{ $routines->first()->academicYear->name ?? '' }}
                        </h2>
                        <p class="text-xs font-medium text-gray-700">
                            Class: <span class="font-bold">{{ $routines->first()->schoolClass->name ?? '' }}</span>
                        </p>
                    </div>
                </div>

                <!-- GROUP ROUTINES BY DATE -->
                @php
                    $groupedRoutines = $routines->groupBy(function($item) {
                        return \Carbon\Carbon::parse($item->exam_date)->format('Y-m-d');
                    });

                    // 🌟 FETCH THE FIRST NON-EMPTY NOTE FROM THE ROUTINE RECORDS
                    $routineNote = $routines->pluck('note')->filter()->first();
                @endphp

                <!-- Schedule Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse border border-black">
                        <thead>
                            <tr class="bg-gray-100 text-black font-bold uppercase text-center">
                                <th class="border border-black px-2 py-1 w-8">#</th>
                                <th class="border border-black px-2 py-1 w-28">Date</th>
                                <th class="border border-black px-2 py-1 w-24">Day</th>
                                <th class="border border-black px-3 py-1 text-left">Subject</th>
                                <th class="border border-black px-2 py-1 w-44">Time</th>
                                <th class="border border-black px-2 py-1 w-16">Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $index = 1; @endphp
                            @foreach($groupedRoutines as $date => $items)
                                @php
                                    $firstItem = $items->first();
                                    $subjectNames = $items->map(fn($i) => $i->subject->name ?? 'N/A')->unique()->implode(' / ');
                                @endphp
                                <tr class="text-center border-b border-black">
                                    <td class="border border-black px-2 py-1 font-medium">{{ sprintf('%02d', $index++) }}</td>
                                    <td class="border border-black px-2 py-1 font-semibold whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($firstItem->exam_date)->format('d M, Y') }}
                                    </td>
                                    <td class="border border-black px-2 py-1 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($firstItem->exam_date)->format('l') }}
                                    </td>
                                    <td class="border border-black px-3 py-1 text-left font-bold">
                                        {{ $subjectNames }}
                                    </td>
                                    <td class="border border-black px-2 py-1 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($firstItem->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($firstItem->end_time)->format('h:i A') }}
                                    </td>
                                    <td class="border border-black px-2 py-1">
                                        {{ $firstItem->room_number ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 🌟 PRINT NOTE / বি:দ্র: SECTION 🌟 -->
                @if(!empty($routineNote))
                    <div class="mt-4 text-xs font-semibold leading-relaxed text-black">
                        <p class="text-red-600">
                            {!! nl2br(e($routineNote)) !!}
                        </p>
                    </div>
                @endif

                <!-- Footer Signatures -->
                <div class="signature-section mt-12 flex justify-between items-end px-4 text-xs font-semibold uppercase">
                    <div>
                        <div class="border-t border-black pt-1 w-36 text-center">Prepared By</div>
                    </div>
                    <div>
                        <div class="border-t border-black pt-1 w-36 text-center">Headmaster</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>