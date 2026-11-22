<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation <?php echo e($quoteRequest->quote_number); ?></title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.5; }
        .page { padding: 36px 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2563eb; padding-bottom: 16px; }
        .brand h1 { font-size: 20px; font-weight: 800; color: #2563eb; letter-spacing: 0.3px; }
        .brand p { color: #6b7280; font-size: 10px; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 24px; font-weight: 700; color: #1f2937; text-transform: uppercase; }
        .doc-title .quote-no { color: #2563eb; font-weight: 600; margin-top: 4px; }
        .doc-title .revision { color: #6b7280; font-size: 11px; }
        .meta { display: flex; justify-content: space-between; gap: 32px; margin-top: 24px; }
        .meta .block { flex: 1; }
        .meta h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .meta p { font-size: 12px; color: #1f2937; }
        .meta .muted { color: #6b7280; }
        .message { margin-top: 24px; padding: 14px 16px; background: #f8fafc; border-left: 3px solid #2563eb; border-radius: 4px; white-space: pre-line; color: #374151; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.items th { background: #eff6ff; color: #2563eb; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; padding: 9px 8px; border-bottom: 2px solid #bfdbfe; }
        table.items td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items .num { text-align: right; }
        table.items .sku { color: #9ca3af; font-size: 10px; }
        .totals { margin-top: 18px; margin-left: auto; width: 290px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; color: #4b5563; }
        .totals .row.grand { border-top: 2px solid #2563eb; margin-top: 6px; padding-top: 10px; font-weight: 700; color: #1f2937; font-size: 15px; }
        .terms { margin-top: 28px; padding-top: 14px; border-top: 1px solid #e5e7eb; white-space: pre-line; color: #4b5563; font-size: 11px; }
        .terms h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .terms-grid { display: flex; gap: 32px; margin-top: 26px; }
        .terms-grid > div { flex: 1; }
        .terms-grid h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
        .terms-grid p { font-size: 11px; color: #374151; margin-bottom: 3px; }
        .terms-grid .muted { color: #6b7280; }
        .footer { margin-top: 34px; border-top: 1px solid #e5e7eb; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #9ca3af; }
        .validity { margin-top: 10px; font-size: 11px; color: #b45309; font-weight: 600; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                <h1><?php echo e($company['name']); ?></h1>
                <p><?php echo e($company['address']); ?></p>
                <p><?php echo e($company['phone']); ?> &nbsp;·&nbsp; <?php echo e($company['email']); ?> &nbsp;·&nbsp; <?php echo e($company['website']); ?></p>
            </div>
            <div class="doc-title">
                <h2>Quotation</h2>
                <div class="quote-no"><?php echo e($quoteRequest->quote_number ?? '—'); ?></div>
                <div class="revision">Revision <?php echo e($quoteResponse->revision); ?></div>
            </div>
        </div>

        <div class="meta">
            <div class="block">
                <h3>Prepared for</h3>
                <p><?php echo e($quoteRequest->first_name); ?> <?php echo e($quoteRequest->last_name); ?></p>
                <?php if($quoteRequest->organization): ?><p class="muted"><?php echo e($quoteRequest->organization); ?></p><?php endif; ?>
                <?php if($quoteRequest->job_title): ?><p class="muted"><?php echo e($quoteRequest->job_title); ?></p><?php endif; ?>
                <p><?php echo e($quoteRequest->email); ?></p>
                <?php if($quoteRequest->phone): ?><p class="muted"><?php echo e($quoteRequest->phone); ?></p><?php endif; ?>
                <p class="muted">
                    <?php echo e(collect([$quoteRequest->city, $quoteRequest->state, $quoteRequest->country])->filter()->implode(', ')); ?>

                </p>
            </div>
            <div class="block">
                <h3>Details</h3>
                <p>Issued: <span class="muted"><?php echo e(optional($quoteResponse->sent_at ?? $quoteResponse->created_at)->format('d M Y')); ?></span></p>
                <p>Valid until: <span class="muted"><?php echo e(optional($quoteResponse->expires_at)->format('d M Y') ?? '—'); ?></span></p>
                <p>Currency: <span class="muted"><?php echo e($quoteResponse->currency); ?></span></p>
                <?php if($quoteResponse->sender): ?>
                    <p>Prepared by: <span class="muted"><?php echo e($quoteResponse->sender->name); ?></span></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if($quoteResponse->subject): ?>
            <div class="message"><strong><?php echo e($quoteResponse->subject); ?></strong>
<?php echo e($quoteResponse->message); ?></div>
        <?php else: ?>
            <div class="message"><?php echo e($quoteResponse->message); ?></div>
        <?php endif; ?>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Discount</th>
                    <th class="num">Tax</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $quoteResponse->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td>
                            <?php echo e($item->name); ?>

                            <?php if($item->description): ?>
                                <div class="sku"><?php echo e($item->description); ?></div>
                            <?php endif; ?>
                            <?php if($item->lead_time_days): ?>
                                <div class="sku">Lead time: <?php echo e($item->lead_time_days); ?> days</div>
                            <?php endif; ?>
                        </td>
                        <td class="sku"><?php echo e($item->sku ?? '—'); ?></td>
                        <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.')); ?></td>
                        <td class="num"><?php echo e(number_format((float) $item->unit_price, 2)); ?></td>
                        <td class="num"><?php echo e(number_format((float) $item->discount_amount, 2)); ?></td>
                        <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $item->tax_rate, 2), '0'), '.')); ?>%</td>
                        <td class="num"><?php echo e(number_format((float) $item->line_total, 2)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span><?php echo e($quoteResponse->currency); ?> <?php echo e(number_format((float) $quoteResponse->subtotal, 2)); ?></span></div>
            <div class="row"><span>Discount</span><span>− <?php echo e($quoteResponse->currency); ?> <?php echo e(number_format((float) $quoteResponse->discount_amount, 2)); ?></span></div>
            <div class="row"><span>Tax</span><span><?php echo e($quoteResponse->currency); ?> <?php echo e(number_format((float) $quoteResponse->tax_amount, 2)); ?></span></div>
            <div class="row"><span>Shipping</span><span><?php echo e($quoteResponse->currency); ?> <?php echo e(number_format((float) $quoteResponse->shipping_amount, 2)); ?></span></div>
            <div class="row grand"><span>Total</span><span><?php echo e($quoteResponse->currency); ?> <?php echo e(number_format((float) $quoteResponse->total_amount, 2)); ?></span></div>
        </div>

        <?php if($quoteResponse->expires_at): ?>
            <p class="validity">This offer is valid until <?php echo e($quoteResponse->expires_at->format('d M Y')); ?>.</p>
        <?php endif; ?>

        <div class="terms-grid">
            <div>
                <h3>Commercial terms</h3>
                <?php if($quoteResponse->incoterm): ?>
                    <p><strong>Incoterms 2020:</strong> <?php echo e($quoteResponse->incoterm); ?><?php if($quoteResponse->incoterm_location): ?> — <?php echo e($quoteResponse->incoterm_location); ?><?php endif; ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->payment_terms): ?>
                    <p><strong>Payment:</strong> <?php echo e(\App\Enums\PaymentTerms::labelFor($quoteResponse->payment_terms)); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->lead_time_days !== null): ?>
                    <p><strong>Lead time:</strong> <?php echo e($quoteResponse->lead_time_days); ?> days</p>
                <?php endif; ?>
                <?php if($quoteResponse->estimated_ship_date): ?>
                    <p><strong>Estimated dispatch:</strong> <?php echo e($quoteResponse->estimated_ship_date->format('d M Y')); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->warranty_months): ?>
                    <p><strong>Warranty:</strong> <?php echo e($quoteResponse->warranty_months); ?> months</p>
                <?php endif; ?>
                <?php if($quoteResponse->partial_shipment_allowed === false): ?>
                    <p class="muted">Partial shipments not allowed.</p>
                <?php endif; ?>
            </div>
            <div>
                <h3>Logistics &amp; packing</h3>
                <?php if($quoteResponse->shipping_method): ?>
                    <p><strong>Method:</strong> <?php echo e(\App\Enums\ShippingMethod::labelFor($quoteResponse->shipping_method)); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->country_of_origin): ?>
                    <p><strong>Origin:</strong> <?php echo e($quoteResponse->country_of_origin); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->hs_code): ?>
                    <p><strong>HS code:</strong> <?php echo e($quoteResponse->hs_code); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->port_of_loading): ?>
                    <p><strong>Port of loading:</strong> <?php echo e($quoteResponse->port_of_loading); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->port_of_discharge): ?>
                    <p><strong>Port of discharge:</strong> <?php echo e($quoteResponse->port_of_discharge); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->package_count): ?>
                    <p><strong>Packages:</strong> <?php echo e($quoteResponse->package_count); ?></p>
                <?php endif; ?>
                <?php
                    $weights = [];
                    if ($quoteResponse->gross_weight_kg !== null) {
                        $weights[] = number_format((float) $quoteResponse->gross_weight_kg, 2).' kg gross';
                    }
                    if ($quoteResponse->net_weight_kg !== null) {
                        $weights[] = number_format((float) $quoteResponse->net_weight_kg, 2).' kg net';
                    }
                ?>
                <?php if($weights !== []): ?>
                    <p><strong>Weight:</strong> <?php echo e(implode(' · ', $weights)); ?></p>
                <?php endif; ?>
                <?php if($quoteResponse->package_dimensions): ?>
                    <p><strong>Dimensions:</strong> <?php echo e($quoteResponse->package_dimensions); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if($quoteResponse->shipping_notes || $quoteResponse->packaging_notes): ?>
            <div class="terms">
                <?php if($quoteResponse->shipping_notes): ?>
                    <h3>Shipping notes</h3>
                    <?php echo e($quoteResponse->shipping_notes); ?>

                <?php endif; ?>
                <?php if($quoteResponse->packaging_notes): ?>
                    <h3 style="margin-top: 8px;">Packaging notes</h3>
                    <?php echo e($quoteResponse->packaging_notes); ?>

                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($quoteResponse->terms): ?>
            <div class="terms">
                <h3>Terms &amp; conditions</h3>
                <?php echo e($quoteResponse->terms); ?>

            </div>
        <?php endif; ?>

        <div class="footer">
            <span><?php echo e($company['name']); ?> · <?php echo e($company['website']); ?></span>
            <span>Generated <?php echo e(now()->format('d M Y H:i')); ?></span>
        </div>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/pdf/quote.blade.php ENDPATH**/ ?>