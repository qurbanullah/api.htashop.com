<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Updated - <?php echo e(config('app.name', 'Htashop')); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #334155;
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            max-width: 460px;
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .header {
            background: #15803d;
            color: white;
            padding: 32px 28px;
            text-align: center;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .body {
            padding: 32px 28px;
            text-align: center;
        }

        .icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #f0fdf4;
            color: #16a34a;
            font-size: 26px;
            line-height: 56px;
            margin: 0 auto 20px;
        }

        .message {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 8px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 13px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1e3a8a;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        .footer a {
            color: #60a5fa;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Subscription Updated</h1>
        </div>

        <div class="body">
            <?php if(session('success')): ?>
                <div style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:20px;font-size:14px;">
                    <?php echo e(session('success')); ?>

                </div>
            <?php endif; ?>

            <div class="icon">✓</div>

            <p class="message">
                Your subscription preferences for <strong><?php echo e($subscription->type); ?></strong> have been updated.
            </p>

            <p class="message">
                Email: <span style="font-weight:600;color:#0f172a;"><?php echo e($subscription->email ?? $user?->email ?? '—'); ?></span>
            </p>

            <div class="actions">
                <a href="<?php echo e(config('app.frontend_url', 'https://htashop.com')); ?>" class="btn btn-primary">Continue to <?php echo e(config('app.name', 'Htashop')); ?></a>

                <?php if(!$subscription->is_subscribed): ?>
                    <form method="POST" action="<?php echo e(route('unsubscribe.resubscribe', $subscription->unsubscribe_token)); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-secondary">Resubscribe</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p><a href="<?php echo e(config('app.frontend_url', 'https://htashop.com')); ?>"><?php echo e(config('app.name', 'Htashop')); ?></a></p>
        </div>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/unsubscribe/success.blade.php ENDPATH**/ ?>