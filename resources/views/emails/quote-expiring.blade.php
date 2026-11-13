<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your quote is about to expire</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 680px; margin: 0 auto; padding: 20px; background: #f8fafc; }
        .header { background: #b45309; color: #ffffff; padding: 28px 32px; border-radius: 12px 12px 0 0; border-bottom: 4px solid #f59e0b; }
        .content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 32px; }
        .summary { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 18px; margin: 22px 0; }
        .summary p { margin: 4px 0; }
        .footer { text-align: center; color: #64748b; font-size: 13px; padding: 18px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 0; }
        td.right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 24px;">Your quote expires soon</h1>
        <p style="margin: 0; opacity: 0.92;">{{ config('app.name') }} — quotation {{ $quoteRequest->quote_number }}</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hello {{ $quoteRequest->first_name }},</p>

        <p>
            Our offer for <strong>{{ $quoteRequest->quote_number }}</strong> is valid until
            <strong>{{ $quoteResponse->expires_at?->format('d M Y') ?? 'soon' }}</strong>.
            If you would like to proceed, simply reply to this email and we will confirm the order.
        </p>

        <div class="summary">
            <table>
                <tr>
                    <td><strong>Revision</strong></td>
                    <td class="right">#{{ $quoteResponse->revision }}</td>
                </tr>
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="right">{{ number_format((float) $quoteResponse->total_amount, 2) }} {{ $quoteResponse->currency }}</td>
                </tr>
                <tr>
                    <td><strong>Valid until</strong></td>
                    <td class="right">{{ $quoteResponse->expires_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <p style="margin-bottom: 0;">Regards,<br><strong>{{ config('app.name') }}</strong></p>

        <p style="text-align: center; margin: 24px 0 8px 0;">
            <a href="{{ $portalUrl }}"
               style="display: inline-block; background: #b45309; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 8px; font-weight: 600;">
                Review your quote
            </a>
        </p>
    </div>

    <div class="footer">
        <p style="margin: 0;">Prices are subject to the stated validity. Reply to this email for any change.</p>
    </div>
</body>
</html>
