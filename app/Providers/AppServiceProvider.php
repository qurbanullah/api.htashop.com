<?php

namespace App\Providers;

use App\Actions\Profile\CreateProfileAction;
use App\Actions\Profile\DeleteProfileAction;
use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Revision\RevisionCreateAction;
use App\Actions\Revision\RevisionReadAction;
use App\Actions\Revision\RevisionRestoreAction;
use App\Actions\Revision\RevisionSearchByUuidAction;
use App\Actions\Users\AssignRolesAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\RestoreUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Interfaces\Ai\ChatProviderInterface;
use App\Interfaces\Ai\EmbeddingProviderInterface;
use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Models\Assignment;
use App\Models\Code;
use App\Models\Definition;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\KnowledgeEntry;
use App\Models\Label;
use App\Models\Measurement;
use App\Models\Post;
use App\Models\Price;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Unit;
use App\Models\User;
use App\Models\Value;
use App\Models\Variant;
use App\Observers\KnowledgeEntryObserver;
use App\Observers\ProductSearchObserver;
use App\Observers\SeoIndexingObserver;
use App\Policies\AssignmentPolicy;
use App\Policies\CodePolicy;
use App\Policies\DefinitionPolicy;
use App\Policies\ForumCommentPolicy;
use App\Policies\ForumPostPolicy;
use App\Policies\LabelPolicy;
use App\Policies\MeasurementPolicy;
use App\Policies\PricePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\ValuePolicy;
use App\Policies\VariantPolicy;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\ProviderManager;
use App\Services\Ai\TokenBudgetService;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\Tools\CreateSupportTicketTool;
use App\Services\Ai\Tools\EscalateToHumanTool;
use App\Services\Ai\Tools\SearchKnowledgeBaseTool;
use App\Services\Ai\Tools\SearchProductsTool;
use App\Services\Assignment\AssignmentService;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationService;
use App\Services\Code\CodeService;
use App\Services\Definition\DefinitionService;
use App\Services\Knowledge\KnowledgeChunker;
use App\Services\Knowledge\KnowledgeEntryService;
use App\Services\Knowledge\KnowledgeSearchService;
use App\Services\Label\LabelService;
use App\Services\Measurement\MeasurementService;
use App\Services\Price\PriceService;
use App\Services\Product\ProductService;
use App\Services\Profile\ProfileService;
use App\Services\Punchout\PunchoutProtocolService;
use App\Services\Punchout\PunchoutSessionService;
use App\Services\Punchout\PunchoutTransactionService;
use App\Services\Revision\RevisionService;
use App\Services\Search\ProductSearchService;
use App\Services\Search\SearchAnalyticsService;
use App\Services\Unit\UnitService;
use App\Services\User\UserService;
use App\Services\Value\ValueService;
use App\Services\Variant\VariantService;
use App\Support\Ai\ChatIdentity;
use App\Support\Punchout\Protocols\CxmlPunchoutProtocol;
use App\Support\Punchout\Protocols\OciPunchoutProtocol;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Actions

        // Register Profile Actions
        $this->app->singleton(CreateProfileAction::class);
        $this->app->singleton(UpdateProfileAction::class);
        $this->app->singleton(DeleteProfileAction::class);

        // Register User Actions
        $this->app->singleton(CreateUserAction::class);
        $this->app->singleton(UpdateUserAction::class);
        $this->app->singleton(DeleteUserAction::class);
        $this->app->singleton(RestoreUserAction::class);
        $this->app->singleton(AssignRolesAction::class);

        // Register Revision Actions
        $this->app->singleton(RevisionCreateAction::class);
        $this->app->singleton(RevisionReadAction::class);
        $this->app->singleton(RevisionSearchByUuidAction::class);
        $this->app->singleton(RevisionRestoreAction::class);

        // Register Services
        $this->app->singleton(RevisionService::class);
        $this->app->singleton(ProfileService::class);
        $this->app->singleton(UserService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(VariantService::class);
        $this->app->singleton(DefinitionService::class);
        $this->app->singleton(LabelService::class);
        $this->app->singleton(MeasurementService::class);
        $this->app->singleton(UnitService::class);
        $this->app->singleton(AssignmentService::class);
        $this->app->singleton(CodeService::class);
        $this->app->singleton(PriceService::class);
        $this->app->singleton(ValueService::class);
        $this->app->singleton(CxmlPunchoutProtocol::class);
        $this->app->singleton(OciPunchoutProtocol::class);
        $this->app->singleton(PunchoutProtocolService::class);
        $this->app->singleton(PunchoutTransactionService::class);
        $this->app->singleton(PunchoutSessionService::class);

        // Search services (Typesense + analytics)
        $this->app->singleton(ProductSearchService::class);
        $this->app->singleton(SearchAnalyticsService::class);

        // AI support assistant
        $this->registerSupportAssistant();
    }

    /**
     * Register the support assistant's services.
     *
     * Chat, embeddings, retrieval and tools are all resolved through
     * interfaces, so a provider can be swapped without touching callers.
     */
    protected function registerSupportAssistant(): void
    {
        $this->app->singleton(ProviderManager::class);
        $this->app->singleton(TokenBudgetService::class);
        $this->app->singleton(PromptBuilder::class);
        $this->app->singleton(KnowledgeChunker::class);

        $this->app->bind(
            ChatProviderInterface::class,
            fn ($app) => $app->make(ProviderManager::class)->chat()
        );
        $this->app->bind(
            EmbeddingProviderInterface::class,
            fn ($app) => $app->make(ProviderManager::class)->embeddings()
        );

        // One shared Typesense-backed retriever behind the interface.
        $this->app->singleton(KnowledgeSearchService::class);
        $this->app->alias(KnowledgeSearchService::class, KnowledgeRetrieverInterface::class);

        $this->app->singleton(KnowledgeEntryService::class);

        // Only these tools are ever exposed to the model.
        $this->app->singleton(ToolRegistry::class, fn ($app) => new ToolRegistry([
            $app->make(SearchKnowledgeBaseTool::class),
            $app->make(SearchProductsTool::class),
            $app->make(CreateSupportTicketTool::class),
            $app->make(EscalateToHumanTool::class),
        ]));

        $this->app->singleton(ConversationService::class);
        $this->app->singleton(ChatService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Passport token lifetimes only if Passport is installed
        if (class_exists(Passport::class)) {
            Passport::tokensExpireIn(now()->addDays(15));
            Passport::refreshTokensExpireIn(now()->addDays(30));
            Passport::personalAccessTokensExpireIn(now()->addMonths(6));
        }

        // Register Policies
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Variant::class, VariantPolicy::class);
        Gate::policy(Definition::class, DefinitionPolicy::class);
        Gate::policy(Label::class, LabelPolicy::class);
        Gate::policy(Measurement::class, MeasurementPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Assignment::class, AssignmentPolicy::class);
        Gate::policy(Code::class, CodePolicy::class);
        Gate::policy(Price::class, PricePolicy::class);
        Gate::policy(Value::class, ValuePolicy::class);
        // Register Forum policies
        Gate::policy(ForumPost::class, ForumPostPolicy::class);
        Gate::policy(ForumComment::class, ForumCommentPolicy::class);

        // Keep the Typesense product index in sync with the products table.
        Product::observe(ProductSearchObserver::class);

        // Notify IndexNow when public URLs go live (products and web posts).
        Product::observe(SeoIndexingObserver::class);
        Post::observe(SeoIndexingObserver::class);

        // Keep the Typesense knowledge index in sync with curated entries.
        KnowledgeEntry::observe(KnowledgeEntryObserver::class);

        // Throttle the assistant per visitor, so one client cannot monopolise
        // the provider. Token spend is separately capped by EnsureChatTokenBudget.
        RateLimiter::for('chat', function (Request $request) {
            $key = ChatIdentity::visitorKey($request);

            return [
                Limit::perMinute(max(1, (int) config('ai.limits.per_minute', 10)))->by($key.':minute'),
                Limit::perDay(max(1, (int) config('ai.limits.per_day', 100)))->by($key.':day'),
            ];
        });
    }
}
