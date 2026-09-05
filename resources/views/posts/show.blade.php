<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->title }} - {{ config('app.name', 'Htashop') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.7;
            color: #334155;
            background-color: #f8fafc;
        }

        .topbar {
            background: #1e3a8a;
            color: white;
            padding: 14px 20px;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }

        .page {
            max-width: 720px;
            margin: 32px auto 48px;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .featured-image {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
        }

        .body {
            padding: 36px 40px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .badge {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .title {
            font-size: 30px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.25;
            margin-bottom: 12px;
        }

        .excerpt {
            font-size: 17px;
            color: #64748b;
            border-left: 3px solid #bfdbfe;
            padding-left: 16px;
            margin-bottom: 28px;
            font-style: italic;
        }

        .content {
            font-size: 16px;
            color: #334155;
            word-break: break-word;
        }

        .content h1, .content h2, .content h3, .content h4 {
            color: #0f172a;
            margin: 28px 0 12px;
            line-height: 1.3;
        }

        .content h1 { font-size: 24px; }
        .content h2 { font-size: 21px; }
        .content h3 { font-size: 18px; }

        .content p {
            margin-bottom: 18px;
        }

        .content a {
            color: #2563eb;
            text-decoration: underline;
        }

        .content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .content blockquote {
            border-left: 3px solid #bfdbfe;
            padding-left: 16px;
            color: #64748b;
            margin: 18px 0;
        }

        .content pre {
            background: #f1f5f9;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 14px;
        }

        .content ul, .content ol {
            padding-left: 24px;
            margin-bottom: 18px;
        }

        .footer {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            padding: 24px 20px 40px;
        }

        .footer a {
            color: #60a5fa;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .body {
                padding: 24px 20px;
            }

            .title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="{{ config('app.frontend_url', 'https://htashop.com') }}">{{ config('app.name', 'Htashop') }}</a>
    </div>

    <div class="page">
        <div class="card">
            @if ($post->featured_image_url)
                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="featured-image">
            @endif

            <div class="body">
                <div class="meta">
                    <span class="badge">{{ $post->type?->label() ?? 'Post' }}</span>
                    @if ($post->created_at)
                        <span class="badge">{{ $post->created_at->format('F j, Y') }}</span>
                    @endif
                    @if ($post->creator)
                        <span class="badge">By {{ $post->creator->name }}</span>
                    @endif
                </div>

                <h1 class="title">{{ $post->title }}</h1>

                @if ($post->excerpt)
                    <p class="excerpt">{{ $post->excerpt }}</p>
                @endif

                <div class="content">
                    {!! $post->content !!}
                </div>
            </div>
        </div>

        <div class="footer">
            <p>You are receiving this because you are subscribed to {{ config('app.name', 'Htashop') }} updates.</p>
            <p>
                <a href="{{ config('app.frontend_url', 'https://htashop.com') }}">Visit {{ config('app.name', 'Htashop') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
