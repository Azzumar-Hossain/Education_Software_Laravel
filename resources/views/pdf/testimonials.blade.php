<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Testimonials</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .certificate-container {
            width: 100%;
            height: 99vh; /* 🌟 Pulled back slightly from 100vh to prevent overflow */
            padding: 15mm;
            box-sizing: border-box;
            background: #fff;
            overflow: hidden; /* 🌟 Hides any microscopic pixel spills */
            page-break-inside: avoid;
        }

        .border-outer {
            border: 6px solid #222;
            height: 100%;
            padding: 6px;
            box-sizing: border-box;
        }
        
        .border-inner {
            border: 2px solid #222;
            height: 100%;
            padding: 25px 35px;
            box-sizing: border-box;
            text-align: center;
            display: flex;
            flex-direction: column;
            position: relative; /* Required to keep the watermark contained */
        }

        /* 🌟 Watermark Styling 🌟 */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            height: 450px;
            opacity: 0.08; /* Very faint so text remains readable */
            z-index: -1; /* Pushes it behind the text */
            pointer-events: none;
        }

        /* Ensures content stays above the watermark */
        .content-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        /* 🌟 New Side-by-Side Header 🌟 */
        .header-section { 
            text-align: center;
            margin-bottom: 25px; 
        }
        
        .logo-title-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .school-logo { 
            height: 75px; 
            margin: 0;
        }

        .school-title { 
            font-size: 32px; 
            font-weight: bold; 
            color: #166534; 
            margin: 0; 
        }

        .school-address {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 6px;
        }

        .banner-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            width: 100%;
        }

        .meta-side {
            flex: 1;
            font-size: 16px;
            font-weight: bold;
        }

        .title-banner {
            background-color: #d4af37;
            color: #000;
            font-size: 30px;
            font-weight: bold;
            font-family: 'Georgia', serif;
            text-transform: uppercase;
            padding: 6px 40px;
            display: inline-block;
            border: 2px solid #000;
            box-shadow: 4px 4px 0px rgba(0,0,0,0.15);
            letter-spacing: 2px;
            flex: 0 1 auto;
        }

        .content-text {
            font-size: 19.5px;
            line-height: 1.8;
            text-align: justify;
        }
        
        .content-text strong {
            font-size: 20px;
            text-transform: uppercase;
            border-bottom: 1px dotted #333;
            padding: 0 4px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 18px;
            font-weight: bold;
            margin-top: auto;
            width: 100%;
        }
        
        .sig-box { text-align: center; }
        .sig-line { border-top: 1px dashed #000; padding-top: 5px; margin-bottom: 5px; }

        @media print {
            @page { size: A4 landscape; margin: 0; }
            
            html, body { 
                padding: 0; 
                margin: 0; 
                height: 100%; 
            }
            
            .page-break { 
                page-break-after: always; 
                break-after: page; 
            }
            
            /* 🌟 Ensures the very last certificate doesn't create a blank page */
            .page-break:last-of-type { 
                page-break-after: auto; 
                break-after: auto; 
            }
        }
    </style>
</head>
<body>

    @foreach($testimonials as $testimonial)
        <div class="certificate-container page-break">
            <div class="border-outer">
                <div class="border-inner">

                    <!-- Watermark Image -->
                    @if($schoolLogo)
                        <img src="{{ $schoolLogo }}" class="watermark" alt="Watermark">
                    @endif

                    <!-- Main Content Wrapper (Stays above watermark) -->
                    <div class="content-wrapper">
                        
                        <!-- Side-by-Side Logo & Name + Address underneath -->
                        <div class="header-section">
                            <div class="logo-title-row">
                                @if($schoolLogo)
                                    <img src="{{ $schoolLogo }}" class="school-logo" alt="School Logo">
                                @endif
                                <h1 class="school-title">{{ strtoupper($schoolName) }}</h1>
                            </div>
                            <div class="school-address">
                                {{ $schoolAddress }}
                            </div>
                        </div>

                        <div class="banner-wrapper">
                            <div class="meta-side" style="text-align: left;">
                                Serial: {{ $testimonial->serial_no }}
                            </div>
                            
                            <div class="title-banner">Testimonial</div>
                            
                            <div class="meta-side" style="text-align: right;">
                                Date: {{ date('d/m/Y') }}
                            </div>
                        </div>

                        <div class="content-text">
                            This is to certify that <strong>{{ $testimonial->name }}</strong>, 
                            son / daughter of <strong>{{ $testimonial->father_name }}</strong> 
                            and <strong>{{ $testimonial->mother_name }}</strong>, 
                            Registration no. <strong>{{ $testimonial->registration_number }}</strong>, 
                            Roll no. <strong>{{ $testimonial->roll_number }}</strong>, 
                            Session <strong>{{ $testimonial->session }}</strong> 
                            student of <strong>{{ strtoupper($testimonial->school_name) }}</strong> 
                            appeared in the <strong>{{ $testimonial->exam_name }}</strong> 
                            under the Board of Intermediate and Secondary Education, <strong>{{ ucfirst($testimonial->education_board) }}</strong>, Bangladesh 
                            and obtained GPA <strong>{{ $testimonial->result }}</strong> 
                            in the <strong>{{ strtoupper($testimonial->study_group) }}</strong> Group. 
                            His / Her date of birth is <strong>{{ \Carbon\Carbon::parse($testimonial->birth_date)->format('d F, Y') }}</strong>. 
                            His / Her permanent address is <strong>{{ $testimonial->address }}</strong>. 
                            He / She bears a good moral character. To the best of my knowledge he / she did not involve in any anti-state activities. 
                            <br>
                            I wish him / her overall progress in life.
                        </div>

                        <div class="signatures">
                            <div class="sig-box">
                                <div style="text-align: left;">Checked by: ........................................</div>
                            </div>
                            <div class="sig-box">
                                <div class="sig-line" style="width: 250px;">Headmaster</div>
                                <div style="font-size: 14px; font-weight: normal;">{{ $schoolName }}</div>
                            </div>
                        </div>

                    </div> <!-- End Content Wrapper -->
                </div>
            </div>
        </div>
    @endforeach

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500); 
        };
    </script>
</body>
</html>