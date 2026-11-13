<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote {{ $decision }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 680px; margin: 0 auto; padding: 20px; background: #f8fafc; }
        .header { background: {{ $decision === 'accepted' ? '#065f46' : '#991b1b' }}; color: #ffffff; padding: 28px 32px; border-radius: 12px 12px 0 0; }
        .content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 32px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 22px 0; }
        .box p { margin: 4px 0; }
        .footer { text-align: center; color: #64748b; font-size: 13px; padding: 18px; }
        a.button { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0 0 8px 0; font-size: 24px;">Quote {{ $decision }}</h1>
        <p style="margin: 0; opacity: 0.92;">{{ $quoteRequest->quote_number }} · {{ $quoteRequest->organization }}</p>
    </div>

    <div class="content">
        <p style="margin-top: 0;">
            {{ $quoteRequest->first_name }} {{ $quoteRequest->last_name }}
            has {{ $decision }} the quotation for
            <strong>{{ $quoteRequest->quote_number }}</strong>.
        </p>

        <div class="box">
            <p><strong>Customer:</strong> {{ $quoteRequest->first_name }} {{ $quoteRequest->last_name }}</p>
            <p><strong>Email:</strong> {{ $quoteRequest->email }}</p>
            @if ($quoteRequest->phone)<p><strong>Phone:</strong> {{ $quoteRequest->phone }}</p>@endif
            @if ($quoteResponse)
                <p><strong>Revision:</strong> #{{ $quoteResponse->revision }}</p>
                <p><strong>Total:</strong> {{ number_format((float) $quoteResponse->total_amount, 2) }} {{ $quoteResponse->currency }}</p>
            @endif
            @if ($reason)
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        </div>

        <a class="button" href="{{ rtrim((string) config('app.manage_url'), '/') }}/quotes/{{ $quoteRequest->uuid }}">
            Open in manage
        </a>

        <p style="margin-bottom: 0; margin-top: 24px;">{{ config('app.name') }}</p>
    </div>

    <div class="footer">
        <p style="margin: 0;">This is an internal notification.</p>
    </div>
</body>
</html>
