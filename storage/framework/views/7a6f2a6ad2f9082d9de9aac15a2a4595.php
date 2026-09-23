<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($post->title); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8fafc;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: #1e3a8a;
            color: white;
            padding: 36px 30px;
            text-align: center;
            border-bottom: 4px solid #3b82f6;
        }

        .header .brand {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.85;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        .content {
            padding: 32px 30px;
        }

        .greeting {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .featured-image {
            width: 100%;
            max-height: 320px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .post-body {
            font-size: 15px;
            color: #4a5568;
            line-height: 1.75;
            word-break: break-word;
        }

        .post-body h1, .post-body h2, .post-body h3, .post-body h4 {
            color: #1e293b;
            margin: 24px 0 12px;
            line-height: 1.3;
        }

        .post-body h1 { font-size: 22px; }
        .post-body h2 { font-size: 19px; }
        .post-body h3 { font-size: 16px; }

        .post-body p {
            margin-bottom: 16px;
        }

        .post-body a {
            color: #2563eb;
            text-decoration: underline;
        }

        .post-body img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
        }

        .post-body blockquote {
            border-left: 3px solid #bfdbfe;
            padding-left: 16px;
            color: #64748b;
            margin: 16px 0;
        }

        .post-body pre {
            background: #f1f5f9;
            padding: 14px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
        }

        .post-body ul, .post-body ol {
            padding-left: 24px;
            margin-bottom: 16px;
        }

        .actions {
            text-align: center;
            margin: 28px 0 8px;
            padding: 8px 0;
        }

        .btn {
            display: inline-block;
            padding: 13px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            text-align: center;
        }

        .btn-primary {
            background: #1e3a8a;
            color: #ffffff !important;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155 !important;
            border: 1px solid #cbd5e1;
        }

        .footer {
            background-color: #1e293b;
            color: #94a3b8;
            padding: 28px 30px;
            text-align: center;
            border-top: 1px solid #334155;
        }

        .footer p {
            margin-bottom: 8px;
            font-size: 13px;
        }

        .footer strong {
            color: #e2e8f0;
            font-weight: 600;
        }

        .footer a {
            color: #60a5fa;
            text-decoration: none;
        }

        .footer .unsubscribe {
            display: inline-block;
            margin-top: 14px;
            font-size: 12px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }

            .header, .content, .footer {
                padding: 20px;
            }

            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="brand"><?php echo e(config('app.name', 'Htashop')); ?></div>
            <h1><?php echo e($post->title); ?></h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello <?php echo e($recipientName ?? $user?->name ?? 'there'); ?>,</p>

            <?php if($post->featured_image_url): ?>
                <img src="<?php echo e($post->featured_image_url); ?>" alt="<?php echo e($post->title); ?>" class="featured-image">
            <?php endif; ?>

            <div class="post-body">
                <?php echo $post->content; ?>

            </div>

            <div class="actions">
                <a href="<?php echo e($viewOnlineUrl); ?>" class="btn btn-primary">View in browser</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong><?php echo e(config('app.name', 'Htashop')); ?> Team</strong></p>
            <p><a href="<?php echo e(config('app.frontend_url', 'https://htashop.com')); ?>"><?php echo e(preg_replace('#^https?://#', '', config('app.frontend_url', 'https://htashop.com'))); ?></a></p>
            <p style="margin-top: 14px; font-size: 12px; opacity: 0.85;">
                You are receiving this email because you are subscribed to <?php echo e(config('app.name', 'Htashop')); ?> updates.
            </p>
            <a href="<?php echo e($unsubscribeUrl); ?>" class="unsubscribe">Unsubscribe from these emails</a>
        </div>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/emails/post.blade.php ENDPATH**/ ?>