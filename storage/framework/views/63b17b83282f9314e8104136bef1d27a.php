<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($originalMessage->subject); ?></title>
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
            border-bottom: 4px solid #16a34a;
        }
        .content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 32px;
        }
        .reply-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            border-radius: 8px;
            padding: 18px;
            margin: 22px 0;
            white-space: pre-wrap;
        }
        .original-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
            margin-top: 24px;
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
        <h1 style="margin: 0 0 8px 0; font-size: 28px;">Reply to Your Contact Message</h1>
        <p style="margin: 0; opacity: 0.92;"><?php echo e(config('app.name')); ?> has responded to your inquiry.</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hello <?php echo e($originalMessage->name); ?>,</p>

        <p><?php echo e($senderName); ?> replied to your contact request:</p>

        <div class="reply-box"><?php echo e($replyMessage); ?></div>

        <div class="original-box">
            <strong>Your original message</strong>
            <div style="margin-top: 10px;"><?php echo e($originalMessage->message); ?></div>
        </div>

        <p style="margin-bottom: 0;">Regards,<br><strong><?php echo e($senderName); ?></strong><br><?php echo e(config('app.name')); ?></p>
    </div>

    <div class="footer">
        <p style="margin: 0;">Please reply to this email if you need further assistance.</p>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/emails/contact-reply.blade.php ENDPATH**/ ?>