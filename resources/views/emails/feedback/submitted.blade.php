<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Received</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #1e293b;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            border-bottom: 4px solid #667eea;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .feedback-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .detail-row {
            margin: 10px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #6b7280;
            display: inline-block;
            width: 150px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-feature { background: #dbeafe; color: #1e40af; }
        .badge-bug { background: #fee2e2; color: #991b1b; }
        .badge-feedback { background: #e0e7ff; color: #3730a3; }
        .badge-suggestion { background: #ddd6fe; color: #5b21b6; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Feedback Received</h1>
        <p>Your submission has been received.</p>
    </div>

    <div class="content">
        <p>Hello <strong>{{ $feedback->name }}</strong>,</p>

        <p>We have received your feedback and will review it with the relevant team.</p>

        <div class="feedback-details">
            <h3 style="margin-top: 0; color: #667eea;">Feedback Details</h3>

            <div class="detail-row">
                <span class="detail-label">Type:</span>
                @if($feedback->type === 'feature_request')
                    <span class="badge badge-feature">Feature Request</span>
                @elseif($feedback->type === 'bug_report')
                    <span class="badge badge-bug">Bug Report</span>
                @elseif($feedback->type === 'suggestion')
                    <span class="badge badge-suggestion">Suggestion</span>
                @else
                    <span class="badge badge-feedback">General Feedback</span>
                @endif
            </div>

            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                {{ $feedback->subject }}
            </div>

            <div class="detail-row">
                <span class="detail-label">Priority:</span>
                {{ ucfirst($feedback->priority) }}
            </div>

            @if($feedback->software_name)
            <div class="detail-row">
                <span class="detail-label">Software:</span>
                {{ $feedback->software_name }}
                @if($feedback->software_version)
                    (v{{ $feedback->software_version }})
                @endif
            </div>
            @endif

            <div class="detail-row">
                <span class="detail-label">Submitted:</span>
                {{ $feedback->created_at->format('F j, Y \a\t g:i A') }}
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #f3f4f6; border-radius: 6px;">
                <strong>Your Message:</strong>
                <p style="margin: 10px 0 0 0; white-space: pre-wrap;">{{ $feedback->message }}</p>
            </div>
        </div>

        <p><strong>Next steps</strong></p>
        <ul>
            <li>Our team will review your feedback</li>
            <li>We may reach out if we need additional information</li>
            <li>You may receive updates if follow-up is required</li>
        </ul>

        <p>Thank you for taking the time to contact us.</p>

        <p>Best regards,<br>
        <strong>{{ config('app.name') }} Team</strong></p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>
