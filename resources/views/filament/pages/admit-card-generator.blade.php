<x-filament-panels::page>
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            html,
            body {
                background-color: #ffffff !important;
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10px !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .fi-sidebar,
            .fi-topbar,
            .fi-header,
            .fi-breadcrumbs,
            nav,
            form,
            button,
            .no-print {
                display: none !important;
            }

            .fi-main,
            main {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                background-color: #ffffff !important;
            }

            .admit-card-wrapper {
                box-shadow: none !important;
                border: 1.5px solid #000000 !important;
                padding: 8px 12px !important;
                background-color: #ffffff !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* When Routine IS ATTACHED: Force 1 card per page, vertically centered */
            .with-routine-break {
                page-break-after: always !important;
                break-after: page !important;
            }

            .card-loop-item:has(.with-routine-break) {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                min-height: 277mm !important;
            }

            /* When Routine IS NOT ATTACHED: 3 Cards per Page */
            .card-only-mode {
                margin-bottom: 0 !important;
            }

            /* Hide the separator cut-line on the 3rd card of every page */
            .card-loop-item:nth-child(2n) .cut-line-container {
                display: none !important;
            }

            /* Force page break after every 3rd card */
            .card-loop-item:nth-child(2n) {
                page-break-after: always !important;
                break-after: page !important;
            }

            .school-logo-img {
                height: 38px !important;
                max-height: 38px !important;
                width: auto !important;
                object-fit: contain !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            table {
                background-color: #ffffff !important;
                border-collapse: collapse !important;
                width: 100% !important;
                font-size: 8.5pt !important;
            }

            th,
            td {
                background-color: #ffffff !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
                padding: 2px 4px !important;
                line-height: 1.1 !important;
            }

            th {
                font-weight: bold !important;
                text-transform: uppercase !important;
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

            /* Belt-and-braces: nothing should paint gray behind the page */
            * {
                box-shadow: none !important;
            }
        }
    </style>

    <div class="space-y-6">
        <!-- Form Section (Hidden when printing) -->
        <div class="no-print">
            <form wire:submit.prevent="generateAdmitCards" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-end gap-3 mt-4">
                    <x-filament::button type="submit" color="primary">
                        Generate Admit Cards
                    </x-filament::button>

                    @if (!empty($enrollments) && count($enrollments) > 0)
                        <x-filament::button type="button" color="warning" icon="heroicon-o-printer"
                            onclick="window.print()">
                            Print Admit Cards
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>

        <!-- Printable Cards Section -->
        @if (!empty($enrollments) && count($enrollments) > 0)
            <div>
                @foreach ($enrollments as $enrollment)
                    <div class="card-loop-item"
                        style="{{ $includeRoutine ? 'display: flex; flex-direction: column; justify-content: center; min-height: 277mm;' : '' }}">
                        <div class="admit-card-wrapper bg-white border-2 border-black rounded-lg shadow-sm text-black {{ $includeRoutine ? 'with-routine-break p-4' : 'card-only-mode p-4' }}"
                            style="{{ !$includeRoutine ? 'min-height: 122mm;' : '' }}">

                            <!-- 🌟 CENTERED HEADER 🌟 -->
                            <div class="text-center border-b border-black"
                                style="display: flex; flex-direction: column; gap: 6px; padding-bottom: 8px; margin-bottom: 10px;">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($schoolLogo)
                                        <img src="{{ $schoolLogo }}"
                                            class="school-logo-img h-9 w-auto object-contain mx-auto" />
                                    @endif
                                </div>

                                <h1 class="text-base font-extrabold uppercase tracking-wide leading-tight text-black">
                                    {{ $schoolName ?? 'Harimohan Govt. High School' }}
                                </h1>

                                <h2 class="text-[11px] font-semibold text-gray-800 uppercase leading-tight">
                                    {{ $examName ?? 'EXAM' }} - {{ $academicYearName ?? '2026' }}
                                </h2>

                                <div>
                                    <span
                                        class="inline-block border border-black px-3 py-0.5 text-[9px] font-bold uppercase tracking-widest bg-gray-100">
                                        Admit Card
                                    </span>
                                </div>
                            </div>

                            <!-- 🌟 STUDENT INFORMATION TABLE 🌟 -->
                            <table class="w-full text-[9.5px] mb-3" style="border-collapse: collapse;">
                                <tr>
                                    <!-- LEFT COLUMN: Personal Info -->
                                    <td style="width: 42%; vertical-align: top; padding-right: 8px;">
                                        <p style="margin-bottom: 5px;"><strong>Student Name:</strong>
                                            {{ strtoupper($enrollment->user->name ?? 'N/A') }}</p>
                                        <p style="margin-bottom: 5px;"><strong>Student ID:</strong>
                                            {{ $enrollment->user->student_id ?? 'N/A' }}</p>
                                        <p style="margin-bottom: 5px;"><strong>Student Father Name:</strong>
                                            {{ strtoupper($enrollment->user->father_name ?? ($enrollment->father_name ?? 'N/A')) }}
                                        </p>
                                        <p><strong>Student Mother Name:</strong>
                                            {{ strtoupper($enrollment->user->mother_name ?? ($enrollment->mother_name ?? 'N/A')) }}
                                        </p>
                                    </td>

                                    <!-- RIGHT COLUMN: Academic Info -->
                                    <td style="width: 42%; vertical-align: top;">
                                        <p style="margin-bottom: 5px;"><strong>Class:</strong>
                                            {{ $enrollment->schoolClass->name ?? 'N/A' }}</p>
                                        <p style="margin-bottom: 5px;"><strong>Section:</strong>
                                            {{ $enrollment->section->name ?? 'N/A' }}</p>
                                        <p style="margin-bottom: 5px;"><strong>Study Group:</strong>
                                            {{ $enrollment->study_group ?? 'General' }}</p>
                                        <p><strong>Section Roll:</strong>
                                            {{ sprintf('%02d', $enrollment->roll_number) }}</p>
                                    </td>

                                    <!-- Photo Box -->
                                    <td style="width: 16%; vertical-align: top; text-align: right;">
                                        @if (!empty($enrollment->user->avatar))
                                            <img src="{{ asset('storage/' . $enrollment->user->avatar) }}"
                                                class="w-12 h-14 object-cover border border-black rounded"
                                                style="display:inline-block;" />
                                        @else
                                            <div class="w-12 h-14 border border-dashed border-black flex items-center justify-center text-[7px] text-center p-0.5 uppercase font-semibold"
                                                style="display:inline-block;">
                                                [ Paste Photo ]
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- INSTRUCTIONS -->
                            <div
                                class="border border-dashed border-gray-400 p-1.5 rounded text-[8.5px] mb-2 bg-gray-50 leading-tight">
                                <p class="font-bold uppercase mb-0.5">General Instructions for Candidates:</p>
                                <ol class="list-decimal list-inside space-y-0.5 text-gray-700">
                                    <li>Candidates must bring this Admit Card to the examination hall daily.</li>
                                    <li>No mobile phones or electronic smart devices are permitted inside.</li>
                                    <li>Candidates must enter the hall at least 15 minutes prior to start time.</li>
                                </ol>
                            </div>

                            <!-- 🌟 GROUPED EXAM ROUTINE (IF TOGGLED ON) 🌟 -->
                            @if ($includeRoutine && !empty($routines) && count($routines) > 0)
                                @php
                                    $groupedRoutines = $routines->groupBy(function ($item) {
                                        return \Carbon\Carbon::parse($item->exam_date)->format('Y-m-d');
                                    });
                                @endphp

                                <div class="mt-2">
                                    <p
                                        class="text-[9px] font-bold uppercase mb-0.5 text-center bg-gray-200 py-0.5 border border-black">
                                        Examination Schedule & Time Table
                                    </p>
                                    <table class="w-full text-center border-collapse border border-black text-[8.5pt]">
                                        <thead>
                                            <tr class="bg-gray-100 font-bold uppercase">
                                                <th class="border border-black px-1 py-0.5 w-5">#</th>
                                                <th class="border border-black px-1.5 py-0.5 w-20">Date</th>
                                                <th class="border border-black px-1 py-0.5 w-12">Day</th>
                                                <th class="border border-black px-2 py-0.5 text-left">Subject</th>
                                                <th class="border border-black px-1.5 py-0.5 w-32">Time</th>
                                                <th class="border border-black px-1 py-0.5 w-12">Room</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $index = 1; @endphp
                                            @foreach ($groupedRoutines as $date => $items)
                                                @php
                                                    $firstItem = $items->first();
                                                    $subjectNames = $items
                                                        ->map(fn($i) => $i->subject->name ?? 'N/A')
                                                        ->unique()
                                                        ->implode(' / ');
                                                @endphp
                                                <tr class="border-b border-black">
                                                    <td class="border border-black px-1 py-0.5 font-medium">
                                                        {{ sprintf('%02d', $index++) }}</td>
                                                    <td
                                                        class="border border-black px-1.5 py-0.5 font-semibold whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($firstItem->exam_date)->format('d M, Y') }}
                                                    </td>
                                                    <td class="border border-black px-1 py-0.5 whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($firstItem->exam_date)->format('D') }}
                                                    </td>
                                                    <td class="border border-black px-2 py-0.5 text-left font-bold">
                                                        {{ $subjectNames }}
                                                    </td>
                                                    <td class="border border-black px-1.5 py-0.5 whitespace-nowrap">
                                                        {{ \Carbon\Carbon::parse($firstItem->start_time)->format('h:i A') }}
                                                        -
                                                        {{ \Carbon\Carbon::parse($firstItem->end_time)->format('h:i A') }}
                                                    </td>
                                                    <td class="border border-black px-1 py-0.5">
                                                        {{ $firstItem->room_number ?? 'N/A' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <!-- 🌟 INCREASED SIGNATURE CLEARANCE (mt-14) 🌟 -->
                            <div style="margin-top: {{ $includeRoutine ? '90px' : '70px' }}; margin-bottom: 4px;"
                                class="flex justify-between items-end px-2 text-[9px] font-bold uppercase">
                                <div class="border-t border-black pt-1.5 w-28 text-center">Class Teacher</div>
                                <div class="border-t border-black pt-1.5 w-28 text-center">Exam Controller</div>
                                <div class="border-t border-black pt-1.5 w-28 text-center">Headmaster</div>
                            </div>

                        </div>

                        <!-- 🌟 EXPANDED GAP BETWEEN CARDS (my-5) 🌟 -->
                        @if (!$includeRoutine)
                            <div class="cut-line-container"
                                style="margin: 35px 0; display: flex; align-items: center; justify-content: center; position: relative;">
                                <div style="width: 100%; border-top: 2px dashed #9ca3af;"></div>
                                <span
                                    style="position: absolute; background: #fff; padding: 0 12px; font-size: 9px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">
                                    ✂ Cut Here
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
