<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Head Wise Fee Collection Report</title>
    <style>
        body { 
            font-family: 'kalpurush', sans-serif; 
            font-size: 14px; 
            color: #000; 
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-danger { color: #dc2626; }
        
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .data-table th, .data-table td { 
            border: 1px solid #000; 
            padding: 8px 10px; 
        }
        .data-table th { 
            background-color: #e6f2ff;
            font-weight: bold; 
        }
    </style>
</head>
<body>

    @php
        $amount = $report['grand_total'];
        $inWords = number_format($amount, 2) . ' BDT';
        if (class_exists('NumberFormatter')) {
            $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
            $inWords = ucwords($f->format($amount)) . ' Taka Only';
        }
    @endphp

    <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px;">
        <tr>
            <td width="15%" class="text-left" valign="top">
                @if($settings && $settings->logo)
                    <img src="{{ public_path('storage/' . $settings->logo) }}" alt="Logo" height="70">
                @endif
            </td>
            <td width="70%" class="text-center" valign="top">
                <h2 style="margin: 0 0 5px 0; font-size: 24px;">{{ $settings->school_name_bn ?? ($settings->school_name_en ?? 'হরিমোহন সরকারি উচ্চ বিদ্যালয়') }}</h2>
                <p style="margin: 0 0 5px 0; font-size: 15px;">{{ $settings->address_bn ?? ($settings->address_en ?? 'নিউ মার্কেট, চাঁপাই নবাবগঞ্জ') }}</p>
                <p style="margin: 0; font-size: 13px;">
                    @if($settings && $settings->phone) মোবাইল: {{ $settings->phone }} @endif
                    @if($settings && $settings->phone && $settings->email) | @endif
                    @if($settings && $settings->email) ইমেইল: {{ $settings->email }} @endif
                </p>
            </td>
            <td width="15%"></td>
        </tr>
    </table>
    
    <div class="text-center" style="margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 20px;">হেড অনুযায়ী ফি সংগ্রহ প্রতিবেদন</h3>
        <p style="margin: 5px 0 0 0; font-size: 16px;">(Head Wise Fee Collection Report)</p>
    </div>
    
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px; font-size: 14px;">
        <tr>
            <td width="33%" class="text-left" valign="top">
                <strong>শ্রেণী (Class):</strong> <u>{{ $className }}</u>
                
                @if(isset($studentName) && $studentName)
                    <br><strong style="margin-top: 5px; display: inline-block;">শিক্ষার্থী (Student):</strong> <u>{{ $studentName }}</u>
                @endif
            </td>
            <td width="33%" class="text-center" valign="top">
                <strong>তারিখ হতে:</strong> <u>{{ $startDate->format('d/m/Y') }}</u><br>
                <strong style="margin-top: 5px; display: inline-block;">তারিখ পর্যন্ত:</strong> <u>{{ $endDate->format('d/m/Y') }}</u>
            </td>
            <td width="34%" class="text-right" valign="top">
                <strong>প্রিন্ট তারিখ:</strong> {{ now()->format('d/m/Y h:i A') }}
            </td>
        </tr>
    </table>
    
    <table class="data-table">
        <thead>
            <tr>
                <th width="10%">ক্রমিক নং<br><span style="font-size: 11px; font-weight: normal;">Sl. No.</span></th>
                <th width="60%">ফির নাম (Head Name)</th>
                <th width="30%">সংগৃহিত অর্থ (Amount in BDT)</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 1; @endphp
            @forelse($report['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $i++ }}</td>
                    <td>{{ $row['name_bn'] ? $row['name_bn'] . ' (' . $row['name'] . ')' : $row['name'] }}</td>
                    <td class="text-right {{ $row['amount'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding: 20px;">No collections found for this period and student.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="2" class="text-right" style="background-color: #e6f2ff;"><strong>মোট সংগৃহিত অর্থ (Net Total)</strong></td>
                <td class="text-right" style="background-color: #e6f2ff; font-size: 16px;"><strong>{{ number_format($report['grand_total'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
    
    <p style="font-size: 14px;"><strong>কথায় (In Words):</strong> {{ $inWords }}</p>
    
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 60px;">
        <tr>
            <td width="25%" class="text-center" style="border-top: 1px dashed #000; padding-top: 5px;">
                প্রস্তুতকারীর স্বাক্ষর
            </td>
            <td width="50%"></td>
            <td width="25%" class="text-center" style="border-top: 1px dashed #000; padding-top: 5px;">
                অনুমোদনকারীর স্বাক্ষর
            </td>
        </tr>
    </table>
    
</body>
</html>