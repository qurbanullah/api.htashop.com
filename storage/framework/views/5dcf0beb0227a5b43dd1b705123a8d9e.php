<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote performance</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.55; color: #333333; max-width: 720px; margin: 0 auto; padding: 20px; background: #f1f5f9; }
        .header { background: #0f172a; color: #ffffff; padding: 26px 30px; border-radius: 12px 12px 0 0; border-bottom: 4px solid #2563eb; }
        .content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 28px 30px; }
        h2 { font-size: 15px; color: #0f172a; margin: 26px 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        .kpi td { padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 13px; }
        .kpi .label { color: #64748b; width: 55%; }
        .kpi .value { font-weight: 700; color: #0f172a; text-align: right; }
        .data th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; padding: 7px 8px; border-bottom: 2px solid #e2e8f0; }
        .data td { font-size: 13px; padding: 7px 8px; border-bottom: 1px solid #f1f5f9; }
        .data .num { text-align: right; }
        .muted { color: #64748b; font-size: 12px; }
        .button { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 11px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .footer { text-align: center; color: #94a3b8; font-size: 12px; padding: 16px; }
    </style>
</head>
<body>
    <?php
        $kpis = $report['kpis'];
        $trend = array_slice($report['trend'], -6);
        $topAccounts = array_slice($report['top_organizations'], 0, 5);
        $team = array_slice($report['assignees'], 0, 5);
    ?>

    <div class="header">
        <h1 style="margin: 0 0 6px 0; font-size: 22px;">Quote performance</h1>
        <p style="margin: 0; opacity: 0.9;">
            <?php echo e($days === 0 ? 'All time' : 'Last '.$days.' days'); ?> · generated <?php echo e(now()->format('d M Y')); ?>

        </p>
    </div>

    <div class="content">
        <h2>Key numbers</h2>
        <table class="kpi">
            <tr>
                <td class="label">Quote requests</td>
                <td class="value"><?php echo e($kpis['total']); ?></td>
            </tr>
            <tr>
                <td class="label">Open quotes</td>
                <td class="value"><?php echo e($kpis['open']); ?></td>
            </tr>
            <tr>
                <td class="label">Past SLA</td>
                <td class="value" style="color: <?php echo e($kpis['overdue'] > 0 ? '#b91c1c' : '#0f172a'); ?>;"><?php echo e($kpis['overdue']); ?></td>
            </tr>
            <tr>
                <td class="label">Win rate (accepted ÷ decided)</td>
                <td class="value"><?php echo e($kpis['win_rate'] !== null ? $kpis['win_rate'].'%' : '—'); ?></td>
            </tr>
            <tr>
                <td class="label">Average first response</td>
                <td class="value"><?php echo e($avgFirstResponse); ?></td>
            </tr>
            <tr>
                <td class="label">Average time to decision</td>
                <td class="value"><?php echo e($avgDecision); ?></td>
            </tr>
            <tr>
                <td class="label">Pipeline value (<?php echo e($currency); ?>)</td>
                <td class="value"><?php echo e(number_format($pipelineValue, 0)); ?></td>
            </tr>
            <tr>
                <td class="label">Won value (<?php echo e($currency); ?>)</td>
                <td class="value"><?php echo e(number_format($wonValue, 0)); ?></td>
            </tr>
        </table>

        <h2>Monthly trend</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="num">Received</th>
                    <th class="num">Accepted</th>
                    <th class="num">Declined</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $trend; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($month['label']); ?></td>
                        <td class="num"><?php echo e($month['received']); ?></td>
                        <td class="num"><?php echo e($month['accepted']); ?></td>
                        <td class="num"><?php echo e($month['declined']); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <h2>Top accounts by volume</h2>
        <?php if(count($topAccounts) === 0): ?>
            <p class="muted">No account activity in this period.</p>
        <?php else: ?>
            <table class="data">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="num">Quotes</th>
                        <th class="num">Won</th>
                        <th class="num">Win rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $topAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td>
                                <?php echo e($account['name']); ?>

                                <div class="muted"><?php echo e(ucfirst($account['type'])); ?></div>
                            </td>
                            <td class="num"><?php echo e($account['total']); ?></td>
                            <td class="num"><?php echo e($account['accepted']); ?></td>
                            <td class="num"><?php echo e($account['win_rate'] !== null ? $account['win_rate'].'%' : '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Team performance</h2>
        <?php if(count($team) === 0): ?>
            <p class="muted">No quotes have been assigned in this period.</p>
        <?php else: ?>
            <table class="data">
                <thead>
                    <tr>
                        <th>Owner</th>
                        <th class="num">Assigned</th>
                        <th class="num">Accepted</th>
                        <th class="num">Avg. first response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $team; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($member['name']); ?></td>
                            <td class="num"><?php echo e($member['assigned']); ?></td>
                            <td class="num"><?php echo e($member['accepted']); ?></td>
                            <td class="num">
                                <?php if($member['avg_first_response_minutes'] === null): ?>
                                    —
                                <?php elseif($member['avg_first_response_minutes'] < 60): ?>
                                    <?php echo e(round($member['avg_first_response_minutes'])); ?>m
                                <?php elseif($member['avg_first_response_minutes'] < 1440): ?>
                                    <?php echo e(rtrim(rtrim(number_format($member['avg_first_response_minutes'] / 60, 1), '0'), '.')); ?>h
                                <?php else: ?>
                                    <?php echo e(rtrim(rtrim(number_format($member['avg_first_response_minutes'] / 1440, 1), '0'), '.')); ?>d
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p style="text-align: center; margin: 28px 0 8px 0;">
            <a class="button" href="<?php echo e($adminUrl); ?>">Open the full report</a>
        </p>
    </div>

    <div class="footer">
        <p style="margin: 0;">Internal digest for administrators. Win rate is accepted ÷ (accepted + declined + expired).</p>
    </div>
</body>
</html>
<?php /**PATH /home/qurban/public_html/htashop.com/api/resources/views/emails/quote-report-digest.blade.php ENDPATH**/ ?>