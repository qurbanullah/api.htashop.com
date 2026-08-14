<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reply</title>
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
            border-bottom: 4px solid #10b981;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .reply-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .original-feedback {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Feedback Response</h1>
        <p>We have reviewed your feedback.</p>
    </div>

    <div class="content">
        <p>Hello <strong>{{ $feedback->name }}</strong>,</p>

        <p>Thank you for your patience. Our team has reviewed your feedback and provided the response below.</p>

        <div class="reply-box">
            <h3 style="margin-top: 0; color: #10b981;">Our Response</h3>
            <p style="white-space: pre-wrap; margin: 0;">{{ $replyMessage }}</p>
        </div>

        <div class="original-feedback">
            <h4 style="margin-top: 0; color: #6b7280;">Your Original Feedback:</h4>
            <p><strong>Subject:</strong> {{ $feedback->subject }}</p>
            <p><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $feedback->type)) }}</p>
            <p style="white-space: pre-wrap;"><strong>Message:</strong><br>{{ $feedback->message }}</p>
        </div>

        <p>If you need to provide additional information, you may reply to this email.</p>

        <p>Best regards,<br>
        <strong>{{ $senderName }}</strong><br>
        {{ config('app.name') }} Team</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
