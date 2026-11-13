<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your quote has expired</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 680px; margin: 0 auto; padding: 20px; background: #f8fafc; }
        .header { background: #0f172a; color: #ffffff; padding: 28px 32px; border-radius: 12px 12px 0 0; border-bottom: 4px solid #64748b; }
        .content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 32px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 22px 0; }
        .footer { text-align: center; color: #64748b; font-size: 13px; padding: 18px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 24px;">Your quote has expired</h1>
        <p style="margin: 0; opacity: 0.92;">{{ config('app.name') }} — quotation {{ $quoteRequest->quote_number }}</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">Hello {{ $quoteRequest->first_name }},</p>

        <p>
            The offer we sent for <strong>{{ $quoteRequest->quote_number }}</strong> expired on
            <strong>{{ $quoteResponse->expires_at?->format('d M Y') ?? 'its validity date' }}</strong>.
        </p>

        <div class="box">
            <p style="margin: 0;">
                Prices and availability may have changed. Reply to this email and we will be happy to
                prepare a fresh quotation for you.
            </p>
        </div>

        <p style="margin-bottom: 0;">Regards,<br><strong>{{ config('app.name') }}</strong></p>

        <p style="text-align: center; margin: 24px 0 8px 0;">
            <a href="{{ $portalUrl }}"
               style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 8px; font-weight: 600;">
                Open the quotation
            </a>
        </p>
    </div>

    <div class="footer">
        <p style="margin: 0;">No action is required if you no longer need this quotation.</p>
    </div>
</body>
</html>
