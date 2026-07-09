<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
    <style>
        body { font-family: 'kalpurush', sans-serif; font-size: 13px; color: #333; }
        
        /* --- NEW HEADER STYLES --- */
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; }
        .logo { height: 70px; margin-bottom: 8px; }
        .school-name-bn { font-size: 28px; font-weight: bold; color: #000; margin: 0; }
        .address-bn { font-size: 16px; color: #000; margin: 5px 0; }
        .contact-info { font-size: 14px; color: #000; margin: 5px 0; }
        
        .report-title { font-size: 18px; font-weight: bold; color: #16a085; margin: 15px 0 0 0; text-transform: uppercase; }
        .date { font-size: 12px; color: #7f8c8d; margin-top: 3px; }
        
        /* --- TABLE STYLES --- */
        .section-title { background-color: #ecf0f1; padding: 5px 10px; font-weight: bold; border-left: 4px solid; margin-top: 20px; font-size: 14px; }
        .title-income { border-color: #27ae60; color: #27ae60; }
        .title-expense { border-color: #e74c3c; color: #e74c3c; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border: 1px solid #bdc3c7; text-align: left; }
        th { background-color: #f9f9f9; font-weight: bold; }
        .text-right { text-align: right; }
        
        .summary-box { width: 40%; float: right; margin-top: 30px; border: 2px solid #2c3e50; border-collapse: collapse; }
        .summary-box td { padding: 10px; font-size: 14px; border: 1px solid #2c3e50; }
        .summary-box .label { font-weight: bold; background-color: #f9f9f9; }
        .net-profit { background-color: #e8f8f5; color: #27ae60; font-weight: bold; }
        .net-loss { background-color: #fdedec; color: #e74c3c; font-weight: bold; }
        
        .clear { clear: both; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #7f8c8d; }
        .signature-line { margin-top: 60px; width: 100%; }
        .signature-line td { border: none; text-align: center; }
        .signature-line td span { border-top: 1px solid #000; padding-top: 5px; display: inline-block; width: 200px; }
    </style>
</head>
<body>

    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" alt="School Logo" class="logo">
        
        <p class="school-name-bn">{{ $settings->school_name_bn ?? 'হরিমোহন সরকারি উচ্চ বিদ্যালয়' }}</p>
        <p class="address-bn">{{ $settings->address_bn ?? 'নিউ মার্কেট, চাঁপাইনবাবগঞ্জ' }}</p>
        <p class="contact-info">
            মোবাইল: {{ $settings->phone ?? '01736007998' }} | ইমেইল: {{ $settings->email ?? 'harimohan@gmail.com' }}
        </p>

        <p class="report-title">General Ledger & Financial Statement</p>
        <p class="date">Report Generated On: {{ date('d F, Y') }}</p>
    </div>

    <div class="section-title title-income">REVENUE / INCOME</div>
    <table>
        <thead>
            <tr>
                <th width="15%">Date</th>
                <th width="30%">Category</th>
                <th width="40%">Description</th>
                <th width="15%" class="text-right">Amount (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($incomes as $income)
            <tr>
                <td>{{ \Carbon\Carbon::parse($income->date)->format('d-M-Y') }}</td>
                <td>{{ $income->category }}</td>
                <td>{{ $income->description ?? '--' }}</td>
                <td class="text-right">{{ number_format($income->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align: center;">No income records found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title title-expense">EXPENDITURES / EXPENSES</div>
    <table>
        <thead>
            <tr>
                <th width="15%">Date</th>
                <th width="30%">Category</th>
                <th width="40%">Description</th>
                <th width="15%" class="text-right">Amount (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
            <tr>
                <td>{{ \Carbon\Carbon::parse($expense->date)->format('d-M-Y') }}</td>
                <td>{{ $expense->category }}</td>
                <td>{{ $expense->description ?? '--' }}</td>
                <td class="text-right">{{ number_format($expense->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align: center;">No expense records found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-box">
        <tr>
            <td class="label">Total Income:</td>
            <td class="text-right">৳ {{ number_format($totalIncome, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total Expense:</td>
            <td class="text-right">৳ {{ number_format($totalExpense, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Net Balance:</td>
            <td class="text-right {{ $net >= 0 ? 'net-profit' : 'net-loss' }}">
                ৳ {{ number_format(abs($net), 2) }} 
                ({{ $net >= 0 ? 'Profit' : 'Loss' }})
            </td>
        </tr>
    </table>
    
    <div class="clear"></div>

    <table class="signature-line">
        <tr>
            <td><span>Accountant Signature</span></td>
            <td><span>Headmaster / Principal</span></td>
        </tr>
    </table>

    <div class="footer">
        Generated by EduSphere School Management System
    </div>

</body>
</html>