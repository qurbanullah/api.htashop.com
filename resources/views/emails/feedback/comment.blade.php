<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Comment on Your Feedback</title>
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
            border-bottom: 4px solid #3b82f6;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .comment-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3b82f6;
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
        .meta {
            color: #6b7280;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Feedback Update</h1>
        <p>A comment has been added to your feedback submission.</p>
    </div>

    <div class="content">
        <p>Hello <strong>{{ $feedback->name }}</strong>,</p>

        <p>A new comment has been added to your feedback:</p>

        <div class="comment-box">
            <h3 style="margin-top: 0; color: #3b82f6;">{{ $commenterName }}</h3>
            <p style="white-space: pre-wrap; margin: 10px 0;">{{ $comment->content }}</p>
            <div class="meta">
                Posted on {{ $comment->created_at->format('F d, Y \a\t h:i A') }}
            </div>
        </div>

        <div class="original-feedback">
            <h4 style="margin-top: 0; color: #6b7280;">Your Original Feedback:</h4>
            <p><strong>Subject:</strong> {{ $feedback->subject }}</p>
            <p><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $feedback->type)) }}</p>
            <p style="white-space: pre-wrap;"><strong>Message:</strong><br>{{ $feedback->message }}</p>
        </div>

        <p>If you need to provide additional information, you may reply to this email.</p>

        <p>Best regards,<br>
        <strong>{{ config('app.name') }} Team</strong></p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
