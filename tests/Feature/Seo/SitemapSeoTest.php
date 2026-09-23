<?php

use App\Enums\PostTypeEnum;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves robots.txt with a sitemap reference', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap:');
});

it('serves a sitemap index that lists every sitemap type', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee('sitemap-products.xml')
        ->assertSee('sitemap-posts.xml')
        ->assertSee('sitemap-categories.xml')
        ->assertSee('sitemap-pages.xml');
});

it('serves per-type sitemaps with well-formed urlset wrappers', function () {
    foreach (['products', 'posts', 'categories', 'pages'] as $type) {
        $this->get("/sitemap-{$type}.xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee('</urlset>', false);
    }
});

it('includes storefront static pages in the pages sitemap', function () {
    $this->get('/sitemap-pages.xml')
        ->assertOk()
        ->assertSee('/contact')
        ->assertSee('/feedback')
        ->assertSee('/blogs')
        ->assertSee('/news')
        ->assertSee('/events');
});

it('serves the configured IndexNow key file and rejects unknown keys', function () {
    config()->set('indexnow.key', 'abcdef0123456789');

    $this->get('/abcdef0123456789.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('abcdef0123456789');

    $this->get('/deadbeefdeadbeef.txt')->assertNotFound();
});

it('includes every web-published post type in the posts sitemap', function () {
    config()->set('indexnow.enabled', false);

    Post::factory()->published()->publishedAsBlog()->create([
        'type' => PostTypeEnum::BLOG,
        'slug' => 'a-blog-post',
    ]);

    // Announcements have their own dedicated section.
    Post::factory()->published()->publishedAsBlog()->create([
        'type' => PostTypeEnum::ANNOUNCEMENT,
        'slug' => 'a-big-announcement',
    ]);

    // Not live: a draft, and a published post never published to the web.
    Post::factory()->draft()->publishedAsBlog()->create([
        'type' => PostTypeEnum::ANNOUNCEMENT,
        'slug' => 'draft-announcement',
    ]);
    Post::factory()->published()->create([
        'type' => PostTypeEnum::ANNOUNCEMENT,
        'slug' => 'not-web-published',
        'is_published_as_blog' => false,
    ]);

    $this->get('/sitemap-posts.xml')
        ->assertOk()
        ->assertSee('/blogs/a-blog-post')
        ->assertSee('/announcements/a-big-announcement')
        ->assertDontSee('draft-announcement')
        ->assertDontSee('not-web-published');
});
