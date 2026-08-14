<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Assignment;
use App\Models\Code;
use App\Models\Definition;
use App\Models\Label;
use App\Models\Measurement;
use App\Models\Product;
use App\Models\Price;
use App\Models\Unit;
use App\Models\User;
use App\Models\Value;
use App\Models\Manuscript;
use App\Models\Profile;
use App\Models\Variant;
use App\Policies\CodePolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\DefinitionPolicy;
use App\Policies\LabelPolicy;
use App\Policies\MeasurementPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PricePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\ValuePolicy;
use App\Policies\ManuscriptPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\ForumPostPolicy;
use App\Policies\ForumCommentPolicy;
use App\Policies\VariantPolicy;
use App\Models\ForumPost;
use App\Models\ForumComment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Actions


        // Register Profile Actions
        $this->app->singleton(\App\Actions\Profile\CreateProfileAction::class);
        $this->app->singleton(\App\Actions\Profile\UpdateProfileAction::class);
        $this->app->singleton(\App\Actions\Profile\DeleteProfileAction::class);

        // Register User Actions
        $this->app->singleton(\App\Actions\Users\CreateUserAction::class);
        $this->app->singleton(\App\Actions\Users\UpdateUserAction::class);
        $this->app->singleton(\App\Actions\Users\DeleteUserAction::class);
        $this->app->singleton(\App\Actions\Users\RestoreUserAction::class);
        $this->app->singleton(\App\Actions\Users\AssignRolesAction::class);

        // Register Revision Actions
        $this->app->singleton(\App\Actions\Revision\RevisionCreateAction::class);
        $this->app->singleton(\App\Actions\Revision\RevisionReadAction::class);
        $this->app->singleton(\App\Actions\Revision\RevisionSearchByUuidAction::class);
        $this->app->singleton(\App\Actions\Revision\RevisionRestoreAction::class);

        // Register Services
        $this->app->singleton(\App\Services\Revision\RevisionService::class);
        $this->app->singleton(\App\Services\Profile\ProfileService::class);
        $this->app->singleton(\App\Services\User\UserService::class);
        $this->app->singleton(\App\Services\Product\ProductService::class);
        $this->app->singleton(\App\Services\Variant\VariantService::class);
        $this->app->singleton(\App\Services\Definition\DefinitionService::class);
        $this->app->singleton(\App\Services\Label\LabelService::class);
        $this->app->singleton(\App\Services\Measurement\MeasurementService::class);
        $this->app->singleton(\App\Services\Unit\UnitService::class);
        $this->app->singleton(\App\Services\Assignment\AssignmentService::class);
        $this->app->singleton(\App\Services\Code\CodeService::class);
        $this->app->singleton(\App\Services\Price\PriceService::class);
        $this->app->singleton(\App\Services\Value\ValueService::class);
        $this->app->singleton(\App\Support\Punchout\Protocols\CxmlPunchoutProtocol::class);
        $this->app->singleton(\App\Support\Punchout\Protocols\OciPunchoutProtocol::class);
        $this->app->singleton(\App\Services\Punchout\PunchoutProtocolService::class);
        $this->app->singleton(\App\Services\Punchout\PunchoutTransactionService::class);
        $this->app->singleton(\App\Services\Punchout\PunchoutSessionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Passport token lifetimes only if Passport is installed
        if (class_exists(\Laravel\Passport\Passport::class)) {
            \Laravel\Passport\Passport::tokensExpireIn(now()->addDays(15));
            \Laravel\Passport\Passport::refreshTokensExpireIn(now()->addDays(30));
            \Laravel\Passport\Passport::personalAccessTokensExpireIn(now()->addMonths(6));
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

    }
}
