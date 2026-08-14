<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message</title>
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
        .panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        .detail-row {
            margin: 10px 0;
        }
        .detail-label {
            display: inline-block;
            width: 140px;
            font-weight: bold;
            color: #475569;
            vertical-align: top;
        }
        .detail-value {
            color: #111827;
        }
        .message-box {
            margin-top: 18px;
            padding: 16px;
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 18px;
        }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: bold;
        }
        a {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 28px;">New Contact Message</h1>
        <p style="margin: 0; opacity: 0.92;">A visitor submitted the website contact form.</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hello{{ $admin?->name ? ' ' . $admin->name : '' }},</p>

        <p>A new contact submission has been received and is available in the admin panel under Contact Messages.</p>

        <div class="panel">
            <div class="detail-row">
                <span class="detail-label">From:</span>
                <span class="detail-value">{{ $contactMessage->name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $contactMessage->email }}</span>
            </div>
            @if($contactMessage->phone)
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">{{ $contactMessage->phone }}</span>
                </div>
            @endif
            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                <span class="detail-value">{{ $contactMessage->subject }}</span>
            </div>
            @if(data_get($contactMessage->metadata, 'subject_key'))
                <div class="detail-row">
                    <span class="detail-label">Category:</span>
                    <span class="pill">{{ data_get($contactMessage->metadata, 'subject_key') }}</span>
                </div>
            @endif
            @if(data_get($contactMessage->metadata, 'source_page'))
                @php $sourcePage = data_get($contactMessage->metadata, 'source_page'); @endphp
                <div class="detail-row">
                    <span class="detail-label">Source Page:</span>
                    <span class="detail-value">
                        @if(str_starts_with($sourcePage, 'http://') || str_starts_with($sourcePage, 'https://'))
                            <a href="{{ $sourcePage }}">{{ $sourcePage }}</a>
                        @else
                            {{ $sourcePage }}
                        @endif
                    </span>
                </div>
            @endif
            <div class="detail-row">
                <span class="detail-label">Submitted:</span>
                <span class="detail-value">{{ $contactMessage->created_at?->format('F j, Y \a\t g:i A') }}</span>
            </div>

            <div class="message-box">
                <strong>Message</strong>
                <div style="margin-top: 10px;">{{ $contactMessage->message }}</div>
            </div>
        </div>

        <p>You can reply directly to this email to contact the sender, or review the full submission in the admin panel.</p>

        <p style="margin-bottom: 0;">Regards,<br><strong>{{ config('app.name') }}</strong></p>
    </div>

    <div class="footer">
        <p style="margin: 0;">{{ config('app.name') }} notification email</p>
    </div>
</body>
</html>
