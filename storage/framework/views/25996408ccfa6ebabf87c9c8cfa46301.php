<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote <?php echo e($decision); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 680px; margin: 0 auto; padding: 20px; background: #f8fafc; }
        .header { background: <?php echo e($decision === 'accepted' ? '#065f46' : '#991b1b'); ?>; color: #ffffff; padding: 28px 32px; border-radius: 12px 12px 0 0; }
        .content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 32px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 22px 0; }
        .box p { margin: 4px 0; }
        .footer { text-align: center; color: #64748b; font-size: 13px; padding: 18px; }
        a.button { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 24px;">Quote <?php echo e($decision); ?></h1>
        <p style="margin: 0; opacity: 0.92;"><?php echo e($quoteRequest->quote_number); ?> · <?php echo e($quoteRequest->organization); ?></p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">
            <?php echo e($quoteRequest->first_name); ?> <?php echo e($quoteRequest->last_name); ?>

            has <?php echo e($decision); ?> the quotation for
            <strong><?php echo e($quoteRequest->quote_number); ?></strong>.
        </p>

        <div class="box">
            <p><strong>Customer:</strong> <?php echo e($quoteRequest->first_name); ?> <?php echo e($quoteRequest->last_name); ?></p>
            <p><strong>Email:</strong> <?php echo e($quoteRequest->email); ?></p>
            <?php if($quoteRequest->phone): ?><p><strong>Phone:</strong> <?php echo e($quoteRequest->phone); ?></p><?php endif; ?>
            <?php if($quoteResponse): ?>
                <p><strong>Revision:</strong> #<?php echo e($quoteResponse->revision); ?></p>
                <p><strong>Total:</strong> <?php echo e(number_format((float) $quoteResponse->total_amount, 2)); ?> <?php echo e($quoteResponse->currency); ?></p>
            <?php endif; ?>
            <?php if($reason): ?>
                <p><strong>Reason:</strong> <?php echo e($reason); ?></p>
            <?php endif; ?>
        </div>

        <a class="button" href="<?php echo e(rtrim((string) config('app.manage_url'), '/')); ?>/quotes/<?php echo e($quoteRequest->uuid); ?>">
            Open in manage
        </a>

        <p style="margin-bottom: 0; margin-top: 24px;"><?php echo e(config('app.name')); ?></p>
    </div>

    <div class="footer">
        <p style="margin: 0;">This is an internal notification.</p>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/emails/quote-decision.blade.php ENDPATH**/ ?>