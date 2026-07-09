<!DOCTYPE html>
@php
    $settings = \App\Models\SiteSetting::first();
    $copies = [
        'Student Copy (শিক্ষার্থীর কপি)', 
        'Office Copy (অফিস কপি)'
    ];
@endphp
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <style>
        body { 
            font-family: 'kalpurush', sans-serif; 
            font-size: 13px; /* Slightly reduced to ensure both copies fit on one page */
            margin: 0; 
            padding: 10px 20px; 
        }

        .copy-badge {
            text-align: right;
            margin-bottom: -25px; /* Pulls it up so it sits parallel to the logo */
        }
        .copy-badge span {
            border: 1px solid #000;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            background-color: #f3f4f6;
            font-size: 12px;
        }

        .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px; }
        .school-name { font-size: 22px; font-weight: bold; margin: 0; padding-top: 5px; }
        .contact-info { font-size: 12px; margin-top: 5px; }
        .details-table { width: 100%; margin-bottom: 10px; }
        .details-table td { padding: 3px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 6px; text-align: left; }
        .items-table th { background-color: #f3f4f6; }
        .text-right { text-align: right !important; }
        
        /* --- SEAL CSS --- */
        .seal {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            text-align: center;
            padding: 6px 12px;
            border-radius: 5px;
            display: inline-block;
            letter-spacing: 2px;
            font-family: sans-serif;
            transform: rotate(-5deg); 
        }
        .seal-paid { color: #16a34a; border: 3px double #16a34a; }
        .seal-partial { color: #ea580c; border: 3px double #ea580c; }
        .seal-unpaid { color: #dc2626; border: 3px double #dc2626; }

        /* --- CUT LINE CSS --- */
        .cut-line {
            border-top: 1px dashed #000;
            margin: 25px 0;
            text-align: center;
            position: relative;
        }
        .cut-line span {
            position: absolute;
            top: -12px;
            background: #fff;
            padding: 0 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>

    {{-- We loop exactly twice: Once for Student, Once for Office! --}}
    @foreach($copies as $index => $copyType)
        
        <div class="receipt-wrapper">
            
            {{-- Copy Type Badge (Top Right) --}}
            <div class="copy-badge">
                <span>{{ $copyType }}</span>
            </div>

            <div class="header">
                @if($settings && $settings->logo)
                    <img src="{{ public_path('storage/' . $settings->logo) }}" alt="School Logo" style="height: 60px;">
                @endif

                <p class="school-name">
                    {{ $settings->school_name_bn ?? ($settings->school_name_en ?? 'কৃষ্ণগোবিন্দপুর উচ্চ বিদ্যালয়') }}
                </p> 

                <p style="margin: 3px 0;">
                    {{ $settings->address_bn ?? ($settings->address_en ?? 'ডাকঘর: রামচন্দ্রপুর হাট, জেলা: চাঁপাইনবাবগঞ্জ') }}
                </p>

                @if($settings && ($settings->phone || $settings->email))
                    <p class="contact-info">
                        @if($settings->phone) মোবাইল: {{ $settings->phone }} @endif
                        @if($settings->phone && $settings->email) | @endif
                        @if($settings->email) ইমেইল: {{ $settings->email }} @endif
                    </p>
                @endif

                <h3 style="margin: 10px 0 5px 0;">বেতন আদায়ের রশিদ (Fee Receipt)</h3>
            </div>

            <table class="details-table" width="100%" cellpadding="5" cellspacing="0" style="margin-bottom: 15px; font-size: 14px;">
                <tr>
                    <td width="50%" align="left"><strong>Receipt No:</strong> {{ $receipt->receipt_number }}</td>
                    <td width="50%" align="right"><strong>Date:</strong> {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td align="left"><strong>Student ID:</strong> {{ $receipt->enrollment->user->student_id ?? 'N/A' }}</td>
                    <td align="right"><strong>Class:</strong> {{ $receipt->enrollment->schoolClass->name ?? '' }}</td>
                </tr>
                <tr>
                    <td align="left"><strong>Student Name:</strong> {{ $receipt->enrollment->user->name ?? '' }}</td>
                    <td align="right"><strong>Roll No:</strong> {{ $receipt->enrollment->roll_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td align="left"><strong>Month & Year:</strong> {{ $receipt->paid_for_month }} {{ $receipt->paid_for_year }}</td>
                    <td align="right"></td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Sl.</th>
                        <th style="width: 60%;">Description (বিবরণ)</th>
                        <th style="width: 30%;" class="text-right">Amount (টাকা)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipt->items as $itemIndex => $item)
                        <tr>
                            <td>{{ $itemIndex + 1 }}</td>
                            
                            <td>
                                {{ $item->feeCategory->name_bn ?? '' }} ({{ $item->feeCategory->name }})
                                
                                @if(!empty($item->related_month))
                                    @php
                                        $months = is_array($item->related_month) ? $item->related_month : [$item->related_month];
                                        $shortMonths = array_map(fn($m) => substr($m, 0, 3), $months);
                                    @endphp
                                    ({{ implode(', ', $shortMonths) }})
                                @endif
                            </td>
                            
                            <td class="text-right">{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    
                    <tr>
                        <td colspan="2" class="text-right" style="font-weight: bold;">Grand Total (মোট):</td>
                        <td class="text-right" style="font-weight: bold; font-size: 15px;">{{ number_format($receipt->total_amount, 2) }} ৳</td>
                    </tr>

                    <tr>
                        <td colspan="2" class="text-right" style="font-weight: bold;">Paid Amount (জমা):</td>
                        <td class="text-right" style="font-weight: bold; font-size: 15px;">{{ number_format($receipt->paid_amount, 2) }} ৳</td>
                    </tr>

                    @if($receipt->due_amount > 0)
                    <tr>
                        <td colspan="2" class="text-right" style="font-weight: bold; color: red;">Remaining Due (বকেয়া):</td>
                        <td class="text-right" style="font-weight: bold; font-size: 15px; color: red;">{{ number_format($receipt->due_amount, 2) }} ৳</td>
                    </tr>
                    @endif
                </tbody>
            </table>

            <table style="width: 100%; margin-top: 30px; border: none;">
                <tr>
                    <td style="width: 33%; text-align: left; vertical-align: bottom; border: none; padding: 0;">
                        <p style="margin: 0;">Collected By: {{ $receipt->collector->name ?? 'System Admin' }}</p>
                    </td>
                    
                    <td style="width: 34%; text-align: center; vertical-align: bottom; border: none; padding: 0;">
                        @if($receipt->paid_amount >= $receipt->total_amount)
                            <span class="seal seal-paid">PAID</span>
                        @elseif($receipt->paid_amount > 0)
                            <span class="seal seal-partial">PARTIAL PAID</span>
                        @else
                            <span class="seal seal-unpaid">UNPAID</span>
                        @endif
                    </td>

                    <td style="width: 33%; text-align: right; vertical-align: bottom; border: none; padding: 0;">
                        <div style="border-top: 1px dashed #000; width: 180px; display: inline-block; text-align: center; padding-top: 5px;">
                            Signature of Collector
                        </div>
                    </td>
                </tr>
            </table>

        </div>

        {{-- If it's the first copy (Student), draw the dashed cut-line underneath it! --}}
        @if($index === 0)
            <div class="cut-line">
                <span>✂</span>
            </div>
        @endif

    @endforeach

</body>
</html>