{{ $post->title }}
{{ str_repeat('=', mb_strlen($post->title)) }}

Hello {{ $user?->name ?? 'there' }},

{{ strip_tags($post->content) }}

@if ($post->featured_image_url)
Featured image: {{ $post->featured_image_url }}
@endif

---
View in browser: {{ $viewOnlineUrl }}
Unsubscribe: {{ $unsubscribeUrl }}
