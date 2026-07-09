<x-filament-panels::page>
    <form wire:submit.prevent="generateAdmitCards" class="space-y-4 no-print">
        {{ $this->form }}
        <div class="text-right">
            <x-filament::button type="submit" color="warning" icon="heroicon-m-identification">
                Generate Admit Cards
            </x-filament::button>
        </div>
    </form>

    @if(count($enrollments) > 0)
        @php
            $siteSetting = \Illuminate\Support\Facades\DB::table('site_settings')->first() ?? \App\Models\Setting::first();
            $logoPath = ($siteSetting && !empty($siteSetting->logo)) ? \Illuminate\Support\Facades\Storage::url($siteSetting->logo) : null;
            $examName = \App\Models\Exam::find($this->data['exam_id'])?->name ?? 'Terminal Examination';
            $yearName = \App\Models\AcademicYear::find($this->data['academic_year_id'])?->name ?? date('Y');
        @endphp

        <div class="space-y-6 print-wrapper-main">
            <div class="flex justify-between items-center mb-4 no-print">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Admit Card Preview Batch</h3>
                <x-filament::button onclick="window.print()" color="info" icon="heroicon-m-printer">
                    Print Batch Admit Cards
                </x-filament::button>
            </div>

            <div class="admit-cards-grid">
                @foreach($enrollments as $enrollment)
                    @php
                        // 🌟 DYNAMIC PROFILE PHOTO DETECTOR
                        // Adjust 'avatar' or 'photo' to match the column name on your User table
                        $studentPhoto = (!empty($enrollment->user->avatar)) 
                            ? \Illuminate\Support\Facades\Storage::url($enrollment->user->avatar) 
                            : ((!empty($enrollment->user->photo)) ? \Illuminate\Support\Facades\Storage::url($enrollment->user->photo) : null);
                    @endphp
                    
                    <div class="admit-card-container">
                        <div class="admit-header">
                            <div class="logo-area">
                                @if($logoPath)
                                    <img src="{{ $logoPath }}" alt="School Logo" class="admit-logo">
                                @else
                                    <div class="admit-logo-fallback">HM</div>
                                @endif
                            </div>
                            <div class="title-area">
                                <h1>Harimohan Govt. High School</h1>
                                <p class="sub-school">Chapai Nawabganj, Bangladesh</p>
                                <div class="exam-badge-title">{{ $examName }} - {{ $yearName }}</div>
                            </div>
                        </div>

                        <div class="admit-type-banner">ADMIT CARD</div>

                        <div class="admit-body-wrapper">
                            <div class="student-info-section">
                                <table class="student-info-table">
                                    <tr>
                                        <td class="lbl">Student Name:</td>
                                        <td class="val font-bold uppercase" colspan="3">{{ $enrollment->user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Student ID:</td>
                                        <td class="val font-mono text-gray-700">{{ $enrollment->user->student_id }}</td>
                                        <td class="lbl">Roll Number:</td>
                                        <td class="val font-mono font-bold text-base">{{ sprintf('%02d', $enrollment->roll_number) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Class:</td>
                                        <td class="val font-bold">{{ $enrollment->schoolClass->name }}</td>
                                        <td class="lbl">Section:</td>
                                        <td class="val">{{ $enrollment->section->name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Study Group:</td>
                                        <td class="val font-semibold" colspan="3">{{ $enrollment->study_group ?? 'General' }}</td>
                                    </tr>
                                </table>
                            </div>

                            <div class="student-photo-box">
                                @if($studentPhoto)
                                    <img src="{{ $studentPhoto }}" alt="Student Photo" class="student-live-avatar">
                                @else
                                    <div class="photo-placeholder-text">
                                        <span>Paste Passport<br>Photo</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="admit-instructions">
                            <h3>General Instructions for Candidates:</h3>
                            <ul>
                                <li>1. Candidates must bring this Admit Card to the examination hall daily.</li>
                                <li>2. No mobile phones, electronic smart devices, or unauthorized scrap papers are permitted inside.</li>
                                <li>3. Candidates must enter the exam hall at least 15 minutes prior to the scheduled exam commencement time.</li>
                            </ul>
                        </div>

                        <div class="admit-footer-signatures">
                            <div class="sig-block">
                                <div class="sig-line-dashed"></div>
                                <p>Class Teacher</p>
                            </div>
                            <div class="sig-block">
                                <div class="sig-line-dashed"></div>
                                <p>Exam Controller</p>
                            </div>
                            <div class="sig-block">
                                <div class="sig-line-dashed"></div>
                                <p>Headmaster</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <style>
        /* --- ADMIT CARD LAYOUT BASE STYLES --- */
        .admit-cards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            width: 100%;
        }
        .admit-card-container {
            background: #ffffff;
            border: 2px solid #1e293b;
            border-radius: 8px;
            padding: 20px;
            position: relative;
            color: #000000;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
            max-width: 750px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* HEADER STRIP */
        .admit-header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
        }
        .logo-area { margin-right: 15px; }
        .admit-logo { width: 55px; height: 55px; object-fit: contain; }
        .admit-logo-fallback { width: 50px; height: 50px; border-radius: 50%; background: #000; color: #fff; font-weight: bold; display: flex; align-items: center; justify-content: center; }
        .title-area { text-align: left; flex-grow: 1; }
        .title-area h1 { font-size: 20px; font-weight: 900; color: #000000; line-height: 1.1; font-family: serif; }
        .sub-school { font-size: 11px; color: #475569; font-weight: 500; }
        .exam-badge-title { font-size: 12px; font-weight: bold; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-top: 4px; text-transform: uppercase; color: #0f172a; }

        .admit-type-banner {
            text-align: center;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 2px;
            margin: 12px 0;
            border-bottom: 1px double #000;
            padding-bottom: 3px;
            color: #000000;
        }

        /* 🌟 NEW STUDENT WRAPPER BODY GRAPHICS WITH PHOTO CONTAINER */
        .admit-body-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .student-info-section {
            width: 78%;
        }
        .student-photo-box {
            width: 95px;
            height: 115px;
            border: 1.5px solid #000000;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            margin-right: 5px;
        }
        .student-live-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-placeholder-text {
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .student-info-table { width: 100%; border-collapse: collapse; }
        .student-info-table td { padding: 4px 6px; vertical-align: middle; border: none !important; }
        .lbl { font-size: 11px; font-weight: 600; color: #475569; width: 22%; text-align: left; }
        .val { font-size: 13px; color: #000000; text-align: left; }

        /* INSTRUCTIONS AREA */
        .admit-instructions { margin-top: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 8px 12px; border-radius: 6px; }
        .admit-instructions h3 { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #334155; margin-bottom: 3px; }
        .admit-instructions ul { list-style: none; padding: 0; margin: 0; }
        .admit-instructions li { font-size: 10px; color: #475569; line-height: 1.3; }

        /* SIGNATURE BLOCKS */
        .admit-footer-signatures { display: flex; justify-content: space-between; margin-top: 45px; padding: 0 10px; }
        .sig-block { text-align: center; width: 140px; }
        .sig-line-dashed { border-top: 1px dashed #000000; width: 100%; margin-bottom: 4px; }
        .sig-block p { font-size: 10px; font-weight: bold; color: #1e293b; text-transform: uppercase; }

        /* --- PRINT MODIFIERS --- */
        @media print {
            .no-print, header, sidebar, nav, .fi-sidebar, .fi-topbar, form { display: none !important; }
            body, .fi-main, .fi-content, main, .fi-layout { background: white !important; padding: 0 !important; margin: 0 !important; }
            .print-wrapper-main { padding: 0 !important; margin: 0 !important; }
            .admit-cards-grid { display: block !important; }
            
            .admit-card-container {
                border: 1.5px solid #000000 !important;
                box-shadow: none !important;
                margin-bottom: 35px !important;
                page-break-inside: avoid !important;
                padding: 15px !important;
                max-width: 100% !important;
            }
            .student-photo-box { border: 1.5px solid #000000 !important; }
            .photo-placeholder-text { color: #000000 !important; }
            .admit-instructions { background: none !important; border: 1px dashed #000 !important; }
            .exam-badge-title { background: none !important; border: 1px solid #000 !important; }
            .lbl, .val, .admit-instructions li { color: #000000 !important; }
        }
    </style>
</x-filament-panels::page>