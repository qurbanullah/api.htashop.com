<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quoteResponse->subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 680px;
            margin: 0 auto;
            padding: 20px;
            background: #f8fafc;
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 28px 32px;
            border-radius: 12px 12px 0 0;
            border-bottom: 4px solid #2563eb;
        }
        .content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 32px;
        }
        .quote-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
            padding: 18px;
            margin: 22px 0;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 18px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 28px;">Your Quote</h1>
        <p style="margin: 0; opacity: 0.92;">{{ config('app.name') }} has prepared a quote for you.</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hello {{ $quoteRequest->first_name }},</p>

        <p>Thank you for your inquiry. Here is our response:</p>

        <div class="quote-box">{{ $quoteResponse->message }}</div>

        @if ($quoteResponse->total_amount !== null)
            <p>
                <strong>Quoted amount:</strong>
                {{ number_format((float) $quoteResponse->total_amount, 2) }} {{ $quoteResponse->currency }}
            </p>
            <p><strong>Valid for:</strong> {{ $quoteResponse->validity_days }} days</p>
        @endif

        <p style="margin-bottom: 0;">Regards,<br><strong>{{ config('app.name') }}</strong></p>
    </div>

    <div class="footer">
        <p style="margin: 0;">Please reply to this email if you have any questions.</p>
    </div>
</body>
</html>
