<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - {{ config('app.name', 'Htashop') }}</title>
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
            background: #1e3a8a;
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
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 26px;
            line-height: 56px;
            margin: 0 auto 20px;
        }

        .message {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
        }

        .email {
            font-weight: 600;
            color: #0f172a;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
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

        .btn-danger {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .note {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
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
            <h1>Unsubscribe</h1>
        </div>

        <div class="body">
            @if (session('error'))
                <div style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:20px;font-size:14px;">
                    {{ session('error') }}
                </div>
            @endif

            <div class="icon">✉️</div>

            <p class="message">
                You are currently subscribed to <strong>{{ $subscription->type }}</strong> notifications
                for <span class="email">{{ $subscription->email ?? $user?->email ?? 'your account' }}</span>.
            </p>

            <p class="message" style="margin-bottom: 28px;">
                You can unsubscribe below. You'll stop receiving {{ $subscription->type }} emails, but you can
                resubscribe at any time.
            </p>

            <form method="POST" action="{{ route('unsubscribe', $token) }}" class="actions">
                @csrf
                <button type="submit" class="btn btn-danger">Unsubscribe</button>
            </form>

            <form method="POST" action="{{ route('unsubscribe.resubscribe', $token) }}" class="actions" style="margin-top: 10px;">
                @csrf
                <button type="submit" class="btn btn-secondary">Keep my subscription</button>
            </form>

            <p class="note">
                You can also manage your preferences anytime from your account settings.
            </p>
        </div>

        <div class="footer">
            <p><a href="{{ config('app.frontend_url', 'https://htashop.com') }}">{{ config('app.name', 'Htashop') }}</a></p>
        </div>
    </div>
</body>
</html>
