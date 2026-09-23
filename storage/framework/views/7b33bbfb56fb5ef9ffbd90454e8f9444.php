<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to HTAShop updates</title>
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
            border-bottom: 4px solid #3b82f6;
        }
        .content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: none;
            padding: 32px;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            margin: 8px 0;
        }
        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 18px;
        }
        .unsubscribe {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 26px;">You're in! 🎉</h1>
        <p style="margin: 0; opacity: 0.92;">Welcome to the HTAShop newsletter</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hi <?php echo e($email); ?>,</p>

        <p>
            Thanks for subscribing! You'll now receive new product launches, exclusive deals, and
            industry insights straight to your inbox.
        </p>

        <p>Here's what you can expect from us:</p>
        <ul>
            <li><strong>Product launches</strong> — the latest electronics, IT, MRO and industrial supplies.</li>
            <li><strong>Exclusive deals</strong> — offers and promotions before anyone else.</li>
            <li><strong>Industry insights</strong> — buying guides and market updates.</li>
        </ul>

        <p style="margin-bottom: 0;">Prefer to browse first?</p>
        <p style="margin-top: 4px;">
            <a href="<?php echo e($siteUrl); ?>" class="btn">Visit <?php echo e(parse_url($siteUrl, PHP_URL_HOST) ?: 'the store'); ?></a>
        </p>
    </div>

    <div class="footer">
        <p style="margin: 0 0 6px 0;">
            <a href="<?php echo e($unsubscribeUrl); ?>" class="unsubscribe">Unsubscribe from these emails</a>
        </p>
        <p style="margin: 0;">© <?php echo e(date('Y')); ?> HTAShop · <?php echo e(parse_url($siteUrl, PHP_URL_HOST)); ?></p>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/emails/newsletter-welcome.blade.php ENDPATH**/ ?>