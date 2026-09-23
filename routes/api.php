<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Assignment\AssignmentController;
use App\Http\Controllers\V1\Category\CategoryController;
use App\Http\Controllers\V1\User\UserController;
use App\Http\Controllers\V1\Comment\CommentController;
use App\Http\Controllers\V1\Profile\ProfileController;
use App\Http\Controllers\V1\Price\PriceController;
use App\Http\Controllers\V1\Quote\QuoteController;
use App\Http\Controllers\V1\Code\CodeController;
use App\Http\Controllers\V1\Definition\DefinitionController;
use App\Http\Controllers\V1\Label\LabelController;
use App\Http\Controllers\V1\Measurement\MeasurementController;
use App\Http\Controllers\V1\Unit\UnitController;
use App\Http\Controllers\V1\Ticket\TicketController;
use App\Http\Controllers\V1\Package\PackageController;
use App\Http\Controllers\V1\Product\ProductController;
use App\Http\Controllers\V1\Product\ProductRevisionController;
use App\Http\Controllers\V1\Feedback\FeedbackController;
use App\Http\Controllers\V1\Feedback\FeedbackAdminController;
use App\Http\Controllers\V1\Contact\ContactMessageController;
use App\Http\Controllers\V1\Contact\ContactMessageAdminController;
use App\Http\Controllers\V1\CrashReport\CrashReportController;
use App\Http\Controllers\V1\Audit\AuditController;
use App\Http\Controllers\V1\Dam\DamCollectionController;
use App\Http\Controllers\V1\Dam\DamController;
use App\Http\Controllers\V1\Support\PublicSupportTicketController;
use App\Http\Controllers\V1\Chat\ChatController;
use App\Http\Controllers\V1\Chat\ChatStreamController;
use App\Http\Controllers\V1\Chat\ChatFeedbackController;
use App\Http\Controllers\V1\Chat\ChatAdminController;
use App\Http\Controllers\V1\Knowledge\KnowledgeEntryAdminController;
use App\Http\Middleware\EnsureChatEnabled;
use App\Http\Middleware\EnsureChatTokenBudget;
use App\Http\Middleware\EnsureChatVisitor;
use App\Http\Controllers\V1\Forum\PublicForumController;
use App\Http\Controllers\V1\Forum\PublicForumCommentController;
use App\Http\Controllers\V1\Forum\ForumPostController;
use App\Http\Controllers\V1\Forum\AdminForumController;
use App\Http\Controllers\V1\Punchout\AdminPunchoutSessionController;
use App\Http\Controllers\V1\Punchout\PunchoutController;
use App\Http\Controllers\V1\Value\ValueController;
use App\Http\Controllers\V1\Variant\VariantController;
use App\Http\Controllers\V1\Variant\VariantRevisionController;
use App\Http\Controllers\V1\Eula\EulaController;
use App\Http\Controllers\V1\Eula\EulaActionController;
use App\Http\Controllers\V1\Eula\ConsentController;
use App\Http\Controllers\V1\AvailabilityController;
use App\Http\Controllers\V1\Currency\CurrencyController;
use App\Http\Controllers\V1\Warehouse\WarehouseController;
use App\Http\Controllers\V1\Inventory\InventoryController;
use App\Http\Controllers\V1\SellerDashboard\SellerDashboardController;
use App\Http\Controllers\V1\Catalog\CatalogController;
use App\Http\Controllers\V1\Cart\CartController;
use App\Http\Controllers\V1\ProductReview\ProductReviewController;
use App\Http\Controllers\V1\Highlight\HighlightController;
use App\Http\Controllers\V1\Highlight\ProductHighlightController;
use App\Http\Controllers\V1\Address\AddressController;
use App\Http\Controllers\V1\Account\AccountController;
use App\Http\Controllers\V1\Country\CountryController;
use App\Http\Controllers\V1\City\CityController;
use App\Http\Controllers\V1\Order\OrderController;
use App\Http\Controllers\V1\OrderDocument\OrderDocumentController;
use App\Http\Controllers\V1\Manufacturer\ManufacturerController;
use App\Http\Controllers\V1\Brand\BrandController;
use App\Http\Controllers\V1\Banner\BannerController;
use Illuminate\Http\Request;
use Illuminate\Fasades\DB;
use Illuminate\Support\Facades\Auth;

// API Version 1 Routes
Route::prefix('v1')->group(function () {

    // Public authentication routes
    Route::post('/register', [\App\Http\Controllers\V1\Auth\AuthController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\V1\Auth\AuthController::class, 'login']);
    Route::post('/refresh', [\App\Http\Controllers\V1\Auth\AuthController::class, 'refresh']);

    // Protected: Get authenticated user
    Route::middleware('auth.api')->get('/user', [\App\Http\Controllers\V1\Auth\AuthController::class, 'user']);

    // User avatar routes (protected)
    Route::middleware('auth.api')->post('/user/avatar', [\App\Http\Controllers\V1\User\UserController::class, 'uploadAvatar']);
    Route::middleware('auth.api')->delete('/user/avatar', [\App\Http\Controllers\V1\User\UserController::class, 'deleteAvatar']);

    // Email verification routes
    Route::post('/verify-email', [\App\Http\Controllers\V1\Auth\AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification-email', [\App\Http\Controllers\V1\Auth\AuthController::class, 'resendVerificationEmail']);

    // Password reset routes
    Route::post('/check-account', [\App\Http\Controllers\V1\Auth\AuthController::class, 'checkAccount']);
    Route::post('/forgot-password', [\App\Http\Controllers\V1\Auth\AuthController::class, 'sendResetLink']);
    Route::post('/reset-password', [\App\Http\Controllers\V1\Auth\AuthController::class, 'resetPassword']);

    // Public punchout entry points
    Route::post('/punchout/{tenantIdentifier}/setup', [PunchoutController::class, 'setup'])->middleware('throttle:10,1');
    Route::post('/punchout/{tenantIdentifier}/cart', [PunchoutController::class, 'cart'])->middleware('throttle:60,1');
    Route::get('/punchout/{tenantIdentifier}/start', [PunchoutController::class, 'start'])->middleware('throttle:60,1');

    // Public EULA endpoint (for download consent)
    Route::get('/eulas/active', [EulaController::class, 'active']);

    // Software Update Checker (Public endpoint for desktop app)

    // Public changelog endpoints (for frontend changelog pages)
    Route::get('/changelogs', [\App\Http\Controllers\V1\Changelog\ChangelogController::class, 'index']);
    Route::get('/changelogs/{id}', [\App\Http\Controllers\V1\Changelog\ChangelogController::class, 'show'])->where('id', '[0-9]+');

    // Public reference data (countries / cities for address forms)
    Route::get('/countries', [CountryController::class, 'index']);
    Route::get('/cities', [CityController::class, 'index']);

    // Public banners (placement + context aware)
    Route::get('/banners', [BannerController::class, 'index']);

    // ====================================================================================
    // ADMIN-ONLY ROUTES - Requires super-admin or admin role
    // ====================================================================================
    Route::middleware(['auth.api', 'admin'])->prefix('admin')->group(function () {

        // Admin Highlight routes
        Route::get('/highlights', [HighlightController::class, 'index']);
        Route::post('/highlights', [HighlightController::class, 'store']);
        Route::get('/highlights/{highlight}', [HighlightController::class, 'show']);
        Route::put('/highlights/{highlight}', [HighlightController::class, 'update']);
        Route::delete('/highlights/{highlight}', [HighlightController::class, 'destroy']);

        // Admin approval workflows (manufacturers & brands suggested by vendors)
        Route::post('/manufacturers/{uuid}/approve', [ManufacturerController::class, 'approve']);
        Route::post('/manufacturers/{uuid}/reject', [ManufacturerController::class, 'reject']);
        Route::post('/brands/{uuid}/approve', [BrandController::class, 'approve']);
        Route::post('/brands/{uuid}/reject', [BrandController::class, 'reject']);

        // Admin Banner CRUD
        Route::get('/banners', [BannerController::class, 'adminIndex']);
        Route::post('/banners', [BannerController::class, 'store']);
        Route::get('/banners/{uuid}', [BannerController::class, 'show']);
        Route::post('/banners/{uuid}/restore', [BannerController::class, 'restore']);
        Route::put('/banners/{uuid}', [BannerController::class, 'update']);
        Route::delete('/banners/{uuid}', [BannerController::class, 'destroy']);

        // Admin Ticket routes
        Route::get('/tickets', [TicketController::class, 'adminIndex']);
        Route::get('/tickets/stats', [TicketController::class, 'adminStats']);

        // Admin Contact Message routes
        Route::get('/contact-messages', [ContactMessageAdminController::class, 'index']);
        Route::get('/contact-messages/statistics', [ContactMessageAdminController::class, 'statistics']);
        Route::get('/contact-messages/{id}', [ContactMessageAdminController::class, 'show'])->whereNumber('id');
        Route::patch('/contact-messages/{id}/mark-read', [ContactMessageAdminController::class, 'markAsRead'])->whereNumber('id');
        Route::patch('/contact-messages/{id}/reply', [ContactMessageAdminController::class, 'reply'])->whereNumber('id');
        Route::delete('/contact-messages/{id}', [ContactMessageAdminController::class, 'destroy'])->whereNumber('id');

        // Admin - AI support assistant: knowledge base
        // `statistics` must be registered before `{uuid}` so it is not captured.
        Route::get('/knowledge-entries', [KnowledgeEntryAdminController::class, 'index']);
        Route::get('/knowledge-entries/statistics', [KnowledgeEntryAdminController::class, 'statistics']);
        Route::post('/knowledge-entries', [KnowledgeEntryAdminController::class, 'store']);
        Route::get('/knowledge-entries/{uuid}', [KnowledgeEntryAdminController::class, 'show']);
        Route::patch('/knowledge-entries/{uuid}', [KnowledgeEntryAdminController::class, 'update']);
        Route::delete('/knowledge-entries/{uuid}', [KnowledgeEntryAdminController::class, 'destroy']);
        Route::post('/knowledge-entries/{uuid}/actions/publish', [KnowledgeEntryAdminController::class, 'publish']);
        Route::post('/knowledge-entries/{uuid}/actions/unpublish', [KnowledgeEntryAdminController::class, 'unpublish']);

        // Admin - AI support assistant: conversations
        Route::get('/chats', [ChatAdminController::class, 'index']);
        Route::get('/chats/statistics', [ChatAdminController::class, 'statistics']);
        Route::get('/chats/{uuid}', [ChatAdminController::class, 'show']);

        // Admin newsletter audience stats
        Route::get('/newsletter/subscribers', [\App\Http\Controllers\V1\Newsletter\NewsletterAdminController::class, 'subscribers']);

        // Admin Audit routes
        Route::get('/audits', [AuditController::class, 'adminIndex']);
        Route::get('/audits/stats', [AuditController::class, 'adminStats']);
        Route::get('/audits/export', [AuditController::class, 'exportCsv']);
        Route::get('/audits/{id}', [\App\Http\Controllers\V1\Audit\AuditController::class, 'show']);

        // Admin Punchout session audit routes
        Route::get('/punchout/sessions', [AdminPunchoutSessionController::class, 'index']);
        Route::get('/punchout/sessions/{uuid}', [AdminPunchoutSessionController::class, 'show']);

        // Admin Post Management Routes
        Route::get('/posts', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'index']);
        Route::get('/posts/stats', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'stats']);
        Route::get('/posts/publishing-trends', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'publishingTrends']);
        Route::get('/posts/types', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'types']);
        Route::get('/posts/statuses', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'statuses']);
        Route::post('/posts', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'store']);
        Route::get('/posts/{uuid}', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'show']);
        Route::put('/posts/{uuid}', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'update']);
        Route::delete('/posts/{uuid}', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'destroy']);

        // Admin Post Action Routes
        Route::post('/posts/{uuid}/actions/send', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'send']);
        Route::post('/posts/{uuid}/actions/schedule', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'schedule']);
        Route::post('/posts/{uuid}/actions/toggle-blog', [\App\Http\Controllers\V1\Post\AdminPostController::class, 'toggleBlogPublication']);

        // Admin Tutorial Management Routes
        Route::get('/tutorials', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'index']);
        Route::get('/tutorials/stats', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'stats']);
        Route::get('/tutorials/popular', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'popular']);
        Route::get('/tutorials/types', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'types']);
        Route::get('/tutorials/statuses', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'statuses']);
        Route::post('/tutorials', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'store']);
        Route::get('/tutorials/{uuid}', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'show']);
        Route::put('/tutorials/{uuid}', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'update']);
        Route::delete('/tutorials/{uuid}', [\App\Http\Controllers\V1\Tutorial\AdminTutorialController::class, 'destroy']);

        // Email logs (admin only)
        Route::get('/email-logs', [\App\Http\Controllers\V1\Email\EmailLogController::class, 'index']);
        Route::get('/email-logs/statistics', [\App\Http\Controllers\V1\Email\EmailLogController::class, 'statistics']);
        Route::get('/email-logs/{uuid}', [\App\Http\Controllers\V1\Email\EmailLogController::class, 'show']);
        Route::get('/email-logs/context/{contextType}/{contextId}', [\App\Http\Controllers\V1\Email\EmailLogController::class, 'byContext']);
        Route::post('/email-logs/{uuid}/retry', [\App\Http\Controllers\V1\Email\EmailLogController::class, 'retry']);

        // Feedback Management (Admin)
        Route::get('/feedbacks', [FeedbackAdminController::class, 'index']);
        Route::get('/feedbacks/statistics', [FeedbackAdminController::class, 'statistics']);
        Route::get('/feedbacks/{uuid}', [FeedbackAdminController::class, 'show']);
        Route::post('/feedbacks/{uuid}/reply', [FeedbackAdminController::class, 'reply']);
        Route::patch('/feedbacks/{uuid}/mark-read', [FeedbackAdminController::class, 'markAsRead']);
        Route::patch('/feedbacks/{uuid}/mark-closed', [FeedbackAdminController::class, 'markAsClosed']);
        Route::delete('/feedbacks/{uuid}', [FeedbackAdminController::class, 'destroy']);

        // Feedback Comments (Admin) - Polymorphic implementation
        Route::get('/feedbacks/{uuid}/comments', [FeedbackAdminController::class, 'getComments']);
        Route::post('/feedbacks/{uuid}/comments', [FeedbackAdminController::class, 'addComment']);

        // ====================================================================================
        // COMMUNITY FORUM - Admin management routes
        // ====================================================================================
        Route::prefix('forum')->group(function () {
            // Topics CRUD
            Route::get('/topics', [AdminForumController::class, 'topicIndex']);
            Route::post('/topics', [AdminForumController::class, 'topicStore']);
            Route::post('/topics/reorder', [AdminForumController::class, 'topicReorder']);
            Route::get('/topics/{uuid}', [AdminForumController::class, 'topicShow']);
            Route::put('/topics/{uuid}', [AdminForumController::class, 'topicUpdate']);
            Route::delete('/topics/{uuid}', [AdminForumController::class, 'topicDestroy']);

            // Posts management
            Route::get('/posts', [AdminForumController::class, 'postIndex']);
            Route::get('/posts/{uuid}', [AdminForumController::class, 'postShow']);
            Route::put('/posts/{uuid}', [AdminForumController::class, 'postUpdate']);
            Route::delete('/posts/{uuid}', [AdminForumController::class, 'postDestroy']);

            // Comments management
            Route::get('/comments', [AdminForumController::class, 'commentIndex']);
            Route::patch('/comments/{id}/hide', [AdminForumController::class, 'commentHide']);
            Route::patch('/comments/{id}/unhide', [AdminForumController::class, 'commentUnhide']);
            Route::delete('/comments/{id}', [AdminForumController::class, 'commentDestroy']);

            // Reports management
            Route::get('/reports', [AdminForumController::class, 'reportIndex']);
            Route::put('/reports/{id}', [AdminForumController::class, 'reportReview']);

            // Stats
            Route::get('/stats', [AdminForumController::class, 'stats']);
        });
    });

    // Posts API (public) - only GET operations are active for now
    Route::get('/posts', [\App\Http\Controllers\V1\Post\PostApiController::class, 'index']);
    Route::get('/posts/category/{categorySlug}', [\App\Http\Controllers\V1\Post\PostApiController::class, 'byCategory']);
    Route::get('/posts/{id}', [\App\Http\Controllers\V1\Post\PostApiController::class, 'show']);

    // Tutorials API (public)
    Route::get('/tutorials', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'index']);
    Route::get('/tutorials/popular', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'popular']);
    Route::get('/tutorials/category/{categorySlug}', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'byCategory']);
    Route::get('/tutorials/{slug}', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'show']);

    // Tutorials API (public) for Software
    Route::get('/learning-center', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'index']);
    Route::get('/learning-center/category/{categorySlug}', [\App\Http\Controllers\V1\Tutorial\PublicTutorialController::class, 'byCategory']);

    // ====================================================================================
    // COMMUNITY FORUM - Public routes
    // ====================================================================================
    Route::prefix('forum')->group(function () {
        Route::get('/topics', [PublicForumController::class, 'topics']);
        Route::get('/topics/{slug}', [PublicForumController::class, 'topicBySlug']);
        Route::get('/posts', [PublicForumController::class, 'posts']);
        Route::get('/posts/{slug}', [PublicForumController::class, 'postBySlug'])->name('forum.posts.show-by-slug');
        Route::get('/posts/{slug}/comments', [PublicForumCommentController::class, 'index']);
    });

    // Category and Tag API routes (public)
    Route::get('/categories', [\App\Http\Controllers\V1\Category\CategoryController::class, 'index']);
    Route::get('/categories/tree', [\App\Http\Controllers\V1\Category\CategoryController::class, 'tree']);
    Route::get('/currencies', [CurrencyController::class, 'index']);
    Route::post('/tags', [\App\Http\Controllers\V1\Tag\TagController::class, 'store']);

    // Category-specific endpoints
    Route::get('/blogs', [\App\Http\Controllers\V1\Post\PostApiController::class, 'blogs']);
    Route::get('/events', [\App\Http\Controllers\V1\Post\PostApiController::class, 'events']);
    Route::get('/news', [\App\Http\Controllers\V1\Post\PostApiController::class, 'news']);

    // Public asset URL generation (no auth required)
    // Use this endpoint for all public images stored in the images/ directory
    Route::post('/assets/generate-url', [\App\Http\Controllers\V1\Asset\PublicAssetController::class, 'generateUrl']);

    // Public post image URL generation (no auth required)
    // @deprecated - Use /assets/generate-url for new implementations
    Route::post('/posts/generate-image-url', [\App\Http\Controllers\V1\Post\PostApiController::class, 'generateImageUrl']);

    // Placeholder RESTful routes (create/update/delete) kept but return 405 from controller
    Route::post('/posts', [\App\Http\Controllers\V1\Post\PostApiController::class, 'store']);
    Route::put('/posts/{id}', [\App\Http\Controllers\V1\Post\PostApiController::class, 'update']);
    Route::delete('/posts/{id}', [\App\Http\Controllers\V1\Post\PostApiController::class, 'destroy']);

    // Feedback API routes
    Route::post('/feedback', [FeedbackController::class, 'submitFeedback'])->middleware('throttle:60,1');
    Route::get('/feedback/{reference}', [FeedbackController::class, 'getFeedback'])->middleware('throttle:120,1');
    Route::get('/feedback/options', [FeedbackController::class, 'getOptions']);

    // Public contact and support intake routes
    Route::post('/contact', [ContactMessageController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/support/tickets', [PublicSupportTicketController::class, 'store'])->middleware('throttle:20,1');

    // AI support assistant — guest-first. A dedicated httpOnly visitor cookie
    // (see EnsureChatVisitor) replaces the session, so chat never touches the
    // database-backed session store.
    Route::prefix('chat')
        ->middleware(EnsureChatVisitor::class)
        ->group(function () {
            // Always available: the widget calls this to learn whether chat is on.
            Route::get('/config', [ChatController::class, 'config']);
            Route::get('/conversation', [ChatController::class, 'show']);
            Route::post('/conversation/actions/reset', [ChatController::class, 'reset']);

            Route::middleware(EnsureChatEnabled::class)->group(function () {
                Route::post('/stream', [ChatStreamController::class, 'store'])
                    ->middleware(['throttle:chat', EnsureChatTokenBudget::class]);

                Route::post('/messages/{message:uuid}/actions/feedback', [ChatFeedbackController::class, 'store'])
                    ->middleware('throttle:chat');
            });
        });

    // Public newsletter subscription
    Route::post('/newsletter/subscribe', [\App\Http\Controllers\V1\Newsletter\NewsletterController::class, 'subscribe'])
        ->middleware('throttle:10,1');

    // Public unsubscribe API (storefront SPA at /unsubscribe/{token})
    Route::get('/unsubscribe/{token}', [\App\Http\Controllers\V1\Unsubscribe\ApiUnsubscribeController::class, 'show']);
    Route::post('/unsubscribe/{token}/unsubscribe', [\App\Http\Controllers\V1\Unsubscribe\ApiUnsubscribeController::class, 'unsubscribe'])
        ->middleware('throttle:10,1');
    Route::post('/unsubscribe/{token}/resubscribe', [\App\Http\Controllers\V1\Unsubscribe\ApiUnsubscribeController::class, 'resubscribe'])
        ->middleware('throttle:10,1');

    // Quote API routes
    Route::post('/quotes', [QuoteController::class, 'submitQuoteRequest'])->middleware('throttle:10,1');
    Route::get('/quotes/{uuid}', [QuoteController::class, 'getQuoteRequest'])->middleware('throttle:60,1');
    Route::post('/quotes/responses/{uuid}/track-view', [QuoteController::class, 'trackView']);

    // Categories - Public routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    // Public catalog (storefront) routes
    Route::get('/catalog/products', [CatalogController::class, 'products']);
    Route::get('/catalog/products/{key}', [CatalogController::class, 'show']);
    Route::get('/catalog/filters', [CatalogController::class, 'filters']);
    Route::get('/catalog/top-nav', [CatalogController::class, 'topNav']);

    // Public search (Typesense-backed with database fallback)
    Route::get('/search/suggest', [\App\Http\Controllers\V1\Search\SearchController::class, 'suggest'])->middleware('throttle:120,1');
    Route::get('/search/trending', [\App\Http\Controllers\V1\Search\SearchController::class, 'trending'])->middleware('throttle:120,1');
    Route::get('/search', [\App\Http\Controllers\V1\Search\SearchController::class, 'index'])->middleware('throttle:120,1');
    Route::post('/search/click', [\App\Http\Controllers\V1\Search\SearchController::class, 'click'])->middleware('throttle:120,1');

    // Public product review routes
    Route::get('/catalog/products/{key}/reviews', [ProductReviewController::class, 'index']);
    Route::get('/catalog/products/{key}/reviews/summary', [ProductReviewController::class, 'summary']);

    // Public cart routes (session + authenticated)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{uuid}', [CartController::class, 'update']);
    Route::delete('/cart/items/{uuid}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // GDPR consent (public — guests identified by pseudonymous consent token)
    Route::post('/gdpr/consents', [\App\Http\Controllers\V1\Gdpr\GdprConsentController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/gdpr/consents/latest', [\App\Http\Controllers\V1\Gdpr\GdprConsentController::class, 'latest']);
    Route::delete('/gdpr/consents', [\App\Http\Controllers\V1\Gdpr\GdprConsentController::class, 'destroy']);

    // Checkout (public — guests can order with inline addresses)
    Route::post('/checkout', [\App\Http\Controllers\V1\Checkout\CheckoutController::class, 'store']);
    Route::get('/checkout/orders/{uuid}', [\App\Http\Controllers\V1\Checkout\CheckoutController::class, 'show']);


    // Profiles - Public routes
    Route::get('/profiles', [ProfileController::class, 'index']);
    Route::get('/profiles/user/{userId}', [ProfileController::class, 'getByUserId']);

    // Public profile view by ID - Must be after /profiles/me
    Route::get('/profiles/{id}', [ProfileController::class, 'show']);


    // Management - Protected routes
    Route::middleware('auth.api')->group(function () {

        // Product reviews (authenticated writes)
        Route::post('/catalog/products/{key}/reviews', [ProductReviewController::class, 'store']);
        Route::post('/reviews/{uuid}/helpful', [ProductReviewController::class, 'toggleHelpful']);

        // Product highlights (manage override)
        Route::get('/products/{uuid}/highlights', [ProductHighlightController::class, 'show']);
        Route::put('/products/{uuid}/highlights', [ProductHighlightController::class, 'sync']);
        Route::get('/highlights', [HighlightController::class, 'available']);
        Route::post('/highlights', [HighlightController::class, 'storeForMerchant']);

        // Address book (user / organization)
        Route::get('/users/me/addresses', [AddressController::class, 'index']);
        Route::post('/users/me/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{uuid}', [AddressController::class, 'update']);
        Route::delete('/addresses/{uuid}', [AddressController::class, 'destroy']);
        Route::post('/addresses/{uuid}/primary', [AddressController::class, 'setPrimary']);

        // Buyer account — order history scoped to the authenticated customer
        Route::get('/account/orders', [OrderController::class, 'myOrders']);
        Route::get('/account/orders/{uuid}', [OrderController::class, 'myOrder']);

        // Buyer account — profile & security
        Route::get('/account/profile', [AccountController::class, 'profile']);
        Route::put('/account/profile', [AccountController::class, 'updateProfile']);
        Route::post('/account/change-password', [AccountController::class, 'changePassword']);
        Route::post('/account/deactivate', [AccountController::class, 'deactivateAccount']);

        // GDPR consent history (privacy center)
        Route::get('/gdpr/consents', [\App\Http\Controllers\V1\Gdpr\GdprConsentController::class, 'index']);

        // Order management (admin + merchant)
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{uuid}', [OrderController::class, 'show']);
        Route::put('/orders/{uuid}/status', [OrderController::class, 'update']);

        // Order documents (invoice / packing slip PDFs)
        Route::get('/orders/{uuid}/documents', [OrderDocumentController::class, 'index']);
        Route::post('/orders/{uuid}/documents', [OrderDocumentController::class, 'store']);

        // ====================================================================================
        // COMMUNITY FORUM - Authenticated user routes
        // ====================================================================================
        Route::prefix('forum')->group(function () {
            Route::get('/my-posts', [ForumPostController::class, 'myPosts']);
            Route::post('/posts', [ForumPostController::class, 'store'])->middleware('throttle:30,1');
            Route::get('/posts/{uuid}', [ForumPostController::class, 'show'])->name('forum.posts.show');
            Route::put('/posts/{id}', [ForumPostController::class, 'update']);
            Route::delete('/posts/{id}', [ForumPostController::class, 'destroy']);
            Route::post('/posts/{id}/like', [ForumPostController::class, 'toggleLike'])->middleware('throttle:120,1');
            Route::post('/posts/{postId}/comments', [ForumPostController::class, 'addComment'])->middleware('throttle:60,1');
            Route::put('/comments/{commentId}', [ForumPostController::class, 'updateComment']);
            Route::delete('/comments/{commentId}', [ForumPostController::class, 'destroyComment']);
            Route::post('/comments/{commentId}/like', [ForumPostController::class, 'toggleCommentLike'])->middleware('throttle:120,1');
            Route::post('/report', [ForumPostController::class, 'report'])->middleware('throttle:20,1');
        });


        // Seller dashboard (protected)
        Route::get('/dashboard', [SellerDashboardController::class, 'index']);

        // Ticket Management - Protected routes
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/stats', [TicketController::class, 'stats']);
        Route::get('/tickets/assignable-users', [TicketController::class, 'assignableUsers']);
        Route::get('/tickets/{uuid}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

        // Ticket admin actions
        Route::patch('/tickets/{uuid}/resolve', [TicketController::class, 'resolve']);
        Route::patch('/tickets/{uuid}/close', [TicketController::class, 'closeTicket']);
        Route::patch('/tickets/{uuid}/reopen', [TicketController::class, 'reopen']);
        Route::patch('/tickets/{uuid}/lock', [TicketController::class, 'toggleLock']);
        Route::patch('/tickets/{uuid}/archive', [TicketController::class, 'archiveTicket']);
        Route::post('/tickets/{uuid}/assign', [TicketController::class, 'assign']);
        Route::post('/tickets/{uuid}/unassign', [TicketController::class, 'unassign']);

        // Ticket messages
        Route::post('/tickets/{uuid}/messages', [\App\Http\Controllers\V1\Message\MessageController::class, 'store']);
        Route::post('/tickets/{uuid}/messages/upload', [\App\Http\Controllers\V1\Message\MessageController::class, 'upload']);
        // Ticket description uploads (for create/edit flows)
        Route::post('/tickets/{uuid}/upload', [TicketController::class, 'upload']);

        // Dropdown data endpoints
        Route::get('/packages', [PackageController::class, 'index']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{uuid}', [ProductController::class, 'show']);
        Route::put('/products/{uuid}', [ProductController::class, 'update']);
        Route::delete('/products/{uuid}', [ProductController::class, 'destroy']);

        Route::get('/features', [\App\Http\Controllers\V1\Feature\FeatureController::class, 'index']);

        Route::get('/manufacturers', [\App\Http\Controllers\V1\Manufacturer\ManufacturerController::class, 'index']);
        Route::post('/manufacturers', [\App\Http\Controllers\V1\Manufacturer\ManufacturerController::class, 'store']);
        Route::get('/manufacturers/{uuid}', [\App\Http\Controllers\V1\Manufacturer\ManufacturerController::class, 'show']);
        Route::put('/manufacturers/{uuid}', [\App\Http\Controllers\V1\Manufacturer\ManufacturerController::class, 'update']);
        Route::delete('/manufacturers/{uuid}', [\App\Http\Controllers\V1\Manufacturer\ManufacturerController::class, 'destroy']);

        Route::get('/brands', [\App\Http\Controllers\V1\Brand\BrandController::class, 'index']);
        Route::post('/brands', [\App\Http\Controllers\V1\Brand\BrandController::class, 'store']);
        Route::get('/brands/{uuid}', [\App\Http\Controllers\V1\Brand\BrandController::class, 'show']);
        Route::put('/brands/{uuid}', [\App\Http\Controllers\V1\Brand\BrandController::class, 'update']);
        Route::delete('/brands/{uuid}', [\App\Http\Controllers\V1\Brand\BrandController::class, 'destroy']);

        Route::get('/products/{uuid}/revisions', [ProductRevisionController::class, 'index']);
        Route::get('/products/{uuid}/revisions/{revision_uuid}', [ProductRevisionController::class, 'show']);
        Route::post('/products/{uuid}/revisions/{revision_uuid}/restore', [ProductRevisionController::class, 'restore']);

        Route::get('/definitions', [DefinitionController::class, 'index']);
        Route::post('/definitions', [DefinitionController::class, 'store']);
        Route::get('/definitions/{uuid}', [DefinitionController::class, 'show']);
        Route::put('/definitions/{uuid}', [DefinitionController::class, 'update']);
        Route::delete('/definitions/{uuid}', [DefinitionController::class, 'destroy']);
        Route::get('/measurements', [MeasurementController::class, 'index']);
        Route::post('/measurements', [MeasurementController::class, 'store']);
        Route::get('/measurements/{uuid}', [MeasurementController::class, 'show']);
        Route::put('/measurements/{uuid}', [MeasurementController::class, 'update']);
        Route::delete('/measurements/{uuid}', [MeasurementController::class, 'destroy']);
        Route::get('/units', [UnitController::class, 'index']);
        Route::post('/units', [UnitController::class, 'store']);
        Route::get('/units/{uuid}', [UnitController::class, 'show']);
        Route::put('/units/{uuid}', [UnitController::class, 'update']);
        Route::delete('/units/{uuid}', [UnitController::class, 'destroy']);
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::get('/warehouses/{uuid}', [WarehouseController::class, 'show']);
        Route::put('/warehouses/{uuid}', [WarehouseController::class, 'update']);
        Route::delete('/warehouses/{uuid}', [WarehouseController::class, 'destroy']);
        Route::get('/inventories', [InventoryController::class, 'index']);
        Route::post('/inventories', [InventoryController::class, 'store']);
        Route::get('/inventories/{uuid}', [InventoryController::class, 'show']);
        Route::put('/inventories/{uuid}', [InventoryController::class, 'update']);
        Route::delete('/inventories/{uuid}', [InventoryController::class, 'destroy']);
        Route::get('/labels', [LabelController::class, 'index']);
        Route::post('/labels', [LabelController::class, 'store']);
        Route::put('/labels/{uuid}', [LabelController::class, 'update']);
        Route::delete('/labels/{uuid}', [LabelController::class, 'destroy']);

        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::post('/assignments', [AssignmentController::class, 'store']);
        Route::get('/assignments/{id}', [AssignmentController::class, 'show'])->whereNumber('id');
        Route::put('/assignments/{id}', [AssignmentController::class, 'update'])->whereNumber('id');
        Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy'])->whereNumber('id');

        Route::get('/variants', [VariantController::class, 'index']);
        Route::post('/variants', [VariantController::class, 'store']);
        Route::get('/variants/{uuid}', [VariantController::class, 'show']);
        Route::put('/variants/{uuid}', [VariantController::class, 'update']);
        Route::delete('/variants/{uuid}', [VariantController::class, 'destroy']);

        Route::get('/variants/{uuid}/revisions', [VariantRevisionController::class, 'index']);
        Route::get('/variants/{uuid}/revisions/{revision_uuid}', [VariantRevisionController::class, 'show']);
        Route::post('/variants/{uuid}/revisions/{revision_uuid}/restore', [VariantRevisionController::class, 'restore']);

        Route::get('/codes', [CodeController::class, 'index']);
        Route::post('/codes', [CodeController::class, 'store']);
        Route::get('/codes/{id}', [CodeController::class, 'show'])->whereNumber('id');
        Route::put('/codes/{id}', [CodeController::class, 'update'])->whereNumber('id');
        Route::delete('/codes/{id}', [CodeController::class, 'destroy'])->whereNumber('id');

        Route::get('/prices', [PriceController::class, 'index']);
        Route::post('/prices', [PriceController::class, 'store']);
        Route::get('/prices/{id}', [PriceController::class, 'show'])->whereNumber('id');
        Route::put('/prices/{id}', [PriceController::class, 'update'])->whereNumber('id');
        Route::delete('/prices/{id}', [PriceController::class, 'destroy'])->whereNumber('id');

        Route::get('/values', [ValueController::class, 'index']);
        Route::post('/values', [ValueController::class, 'store']);
        Route::get('/values/{id}', [ValueController::class, 'show'])->whereNumber('id');
        Route::put('/values/{id}', [ValueController::class, 'update'])->whereNumber('id');
        Route::delete('/values/{id}', [ValueController::class, 'destroy'])->whereNumber('id');

        // DAM-first upload and asset routes
        Route::get('/dam/collections', [DamCollectionController::class, 'index']);
        Route::post('/dam/collections', [DamCollectionController::class, 'store']);
        Route::put('/dam/collections/{damCollection}', [DamCollectionController::class, 'update']);
        Route::delete('/dam/collections/{damCollection}', [DamCollectionController::class, 'destroy']);
        Route::get('/dam/owners', [DamController::class, 'owners']);
        Route::get('/dam/assets', [DamController::class, 'index']);
        Route::post('/dam/presign', [DamController::class, 'presign']);
        Route::post('/dam/ingest', [DamController::class, 'ingest']);
        Route::post('/dam/upload/presigned-url', [DamController::class, 'generatePresignedUrl']);
        Route::post('/dam/upload/multipart/initiate', [DamController::class, 'initiateMultipartUpload']);
        Route::post('/dam/upload/multipart/part-urls', [DamController::class, 'getMultipartUploadUrls']);
        Route::post('/dam/upload/multipart/complete', [DamController::class, 'completeMultipartUpload']);
        Route::post('/dam/upload/multipart/abort', [DamController::class, 'abortMultipartUpload']);
        Route::post('/dam/verify', [DamController::class, 'verifyUpload']);
        Route::post('/dam/generate-url', [DamController::class, 'generateAccessUrl']);

        // Compatibility aliases for existing frontend callers
        Route::post('/storage/upload/presigned-url', [DamController::class, 'generatePresignedUrl']);
        Route::post('/storage/upload/multipart/initiate', [DamController::class, 'initiateMultipartUpload']);
        Route::post('/storage/upload/multipart/part-urls', [DamController::class, 'getMultipartUploadUrls']);
        Route::post('/storage/upload/multipart/complete', [DamController::class, 'completeMultipartUpload']);
        Route::post('/storage/upload/multipart/abort', [DamController::class, 'abortMultipartUpload']);
        Route::post('/storage/verify', [DamController::class, 'verifyUpload']);
        Route::post('/storage/generate-url', [DamController::class, 'generateAccessUrl']);

        // EULA Management Routes (Admin Protected)
        Route::apiResource('eulas', EulaController::class);
        Route::get('/eulas-stats', [EulaController::class, 'statistics']);
        Route::post('/eulas/{eula}/actions/activate', [EulaActionController::class, 'activate']);

        // Consent Management
        Route::post('/consents', [ConsentController::class, 'store']);
        Route::get('/consents/check', [ConsentController::class, 'check']);
        Route::get('/consents', [ConsentController::class, 'index']);
        Route::get('/eulas/{eula}/consents', [ConsentController::class, 'eulaConsents']);
        Route::get('/eulas/{eula}/consents/stats', [ConsentController::class, 'eulaConsentStats']);

        Route::post('/categories', [CategoryController::class, 'store']);

        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/statistics', [UserController::class, 'statistics']);
        Route::get('/users/roles', [UserController::class, 'getRoles']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::get('/users/{user}/publications', [UserController::class, 'publications']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [UserController::class, 'restore']);
        Route::delete('/users/{id}/force', [UserController::class, 'forceDelete']);
        Route::post('/users/{user}/roles', [UserController::class, 'assignRoles']);
        // Admin actions: reset password and verify email for a specific user
        Route::post('/users/{user}/reset-password', [UserController::class, 'adminResetPassword']);
        Route::post('/users/{user}/verify', [UserController::class, 'verifyUserEmail']);

        // Get unread count - MUST be before generic routes
        Route::get('/comments/unread-count', [CommentController::class, 'unreadCount']);

        // Get comments for an entity (manuscript or reviewer)
        Route::get('/comments', [CommentController::class, 'index']);
        Route::get('/comments/{id}', [CommentController::class, 'show']);

        // Create and manage comments
        Route::post('/comments', [CommentController::class, 'store']);
        Route::post('/comments/{parentId}/reply', [CommentController::class, 'reply']);
        Route::put('/comments/{id}', [CommentController::class, 'update']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

        // Mark as read
        Route::post('/comments/{id}/read', [CommentController::class, 'markAsRead']);
        Route::post('/comments/read-all', [CommentController::class, 'markAllAsRead']);

        Route::get('/profiles/me', [ProfileController::class, 'me']); // Must be before /profiles/{id}
        Route::post('/profiles', [ProfileController::class, 'store']);
        Route::put('/profiles/{id}', [ProfileController::class, 'update']);
        Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']);
        Route::patch('/profiles/{id}/visibility', [ProfileController::class, 'toggleVisibility']);
        Route::patch('/profiles/{id}/reviewer-availability', [ProfileController::class, 'updateReviewerAvailability']);

        Route::post('/profiles/{id}/verify', [ProfileController::class, 'verify']);
        Route::post('/profiles/complete-onboarding', [ProfileController::class, 'completeOnboarding']);

        // Availability checks (onboarding)
        Route::post('/check-availability', [AvailabilityController::class, 'check']);

    });


        // New role-based authentication routes
    // Route::prefix('auth')->group(function () {
    //     Route::post('/login-with-roles', [RoleAuthController::class, 'login']);
    //     Route::post('/register-with-roles', [RoleAuthController::class, 'register']);

    //     Route::middleware('auth:api')->group(function () {
    //         Route::get('/user', [RoleAuthController::class, 'user']);
    //         Route::post('/logout', [RoleAuthController::class, 'logout']);
    //         Route::post('/check-permissions', [RoleAuthController::class, 'checkPermissions']);
    //         Route::post('/check-roles', [RoleAuthController::class, 'checkRoles']);
    //     });
    // });

    // Test route for debugging
    Route::post('/test-auth', function(Request $request) {
        try {
            $email = $request->email;
            $password = $request->password;

            if (!Auth::attempt(['email' => $email, 'password' => $password])) {
                return response()->json(['error' => 'Auth failed']);
            }

            $user = Auth::user();
            $token = $user->createToken('Test Token')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => $user->name,
                'token' => substr($token, 0, 20) . '...'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    });


});

// Health check endpoints for HAProxy (unversioned for infrastructure)
Route::get('/health', function () {
    try {
        // Check database connection
        $dbStatus = 'OK';
        try {
            DB::connection()->getPdo();
            if (DB::connection()->getDatabaseName()) {
                $dbStatus = 'Connected';
            }
        } catch (\Exception $e) {
            $dbStatus = 'Failed: ' . $e->getMessage();
        }

        // Check disk space
        $diskSpace = disk_free_space('/') / disk_total_space('/') * 100;

        // Check memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB

        // Container info
        $containerInfo = [
            'container_id' => gethostname(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
        ];

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'database' => $dbStatus,
            'disk_free_percent' => round($diskSpace, 2),
            'memory_usage_mb' => round($memoryUsage, 2),
            'container' => $containerInfo,
            'load_balancer' => 'haproxy'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage(),
            'container_id' => gethostname()
        ], 503);
    }
});

// Simple ping endpoint
Route::get('/ping', function () {
    return response()->json(['pong' => true, 'timestamp' => time()], 200);
});
