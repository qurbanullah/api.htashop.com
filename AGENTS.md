# Volvicon API - AI Agent Guidelines

> **Version**: 2.0 (January 2026)  
> **Laravel Version**: 12.x  
> **PHP Version**: 8.2+

> **🚨 CRITICAL - CHECK BEFORE CREATING ANYTHING NEW 🚨**
> 
> Before creating any new helper, service, trait, or utility class, **ALWAYS** search for existing implementations in:
> - `/api/app/Helpers/` - Helper classes (CacheHelper, ApiResponse, etc.)
> - `/api/app/Services/` - Service classes (business logic)
> - `/api/app/Traits/` - Reusable traits (GeneratesUniqueSlug, etc.)
> - `/api/app/Actions/` - CRUD actions
> - `/api/app/Filters/` - Pipeline filters
> 
> **DO NOT CREATE DUPLICATES. REUSE EXISTING CODE.**
> 
> Examples:
> - ✅ Use `CacheHelper` from `/api/app/Helpers/CacheHelper.php` for all caching
> - ✅ Use `ApiResponse` helper for all JSON responses
> - ✅ Use existing Services for business logic
> - ❌ DO NOT create custom helper classes for functionality that already exists

This document defines the architectural standards, patterns, and best practices for the Volvicon API service. All AI agents and developers must follow these guidelines strictly.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [API Design Standards](#2-api-design-standards)
3. [Layer Responsibilities](#3-layer-responsibilities)
4. [File Organization](#4-file-organization)
5. [Request Flow Pattern](#5-request-flow-pattern)
6. [Storage & Media Management](#6-storage--media-management)
7. [Database & Query Standards](#7-database--query-standards)
8. [Caching Strategy](#8-caching-strategy)
9. [Authorization & Security](#9-authorization--security)
10. [Response Standards](#10-response-standards)
11. [Testing Requirements](#11-testing-requirements)
12. [Docker Environment](#12-docker-environment)
13. [Code Examples](#13-code-examples)

---

## 1. Architecture Overview

### 1.1 Core Principles

✅ **REST + Standardized Actions** (not pure REST, not GraphQL)
- Standard CRUD via REST resources
- Complex state transitions via `/actions/` endpoints
- Sub-resources for nested relationships

✅ **Layered Architecture**
```
Request → Controller → Service → Action → Model → Database
         ↓           ↓         ↓
      Validation  Business   CRUD
                   Logic   Operations
```

✅ **Single Responsibility**
- Controllers: Route requests, delegate to services
- Services: Business logic, orchestration
- Actions: Single CRUD operations
- Models: Data structure, relationships, casting

✅ **Database-Driven Taxonomy and Dynamic Configuration**
- For dropdowns, labels, classifications, measurement types, and other business-managed values, prefer tenant-scoped database tables over hardcoded enums or dedicated one-off models.
- Reusable taxonomy should be modeled once and attached through scalable relations such as polymorphic pivots when multiple domains may need the same values.
- Do not introduce definition-specific, product-specific, or feature-specific taxonomy models when a generic taxonomy model can serve multiple domains.
- Do not reuse `Family` semantics for measurements. Product taxonomy families and dimensional measurement systems are different concepts; measurements must use the dedicated `Measurement` model and related `Unit` model.
- Adding a new business value should usually require inserting data, not editing PHP code.
- Use caching for frequently requested taxonomy payloads, but do not replace database-driven configuration with static code.

### 1.2 SOLID Principles (MANDATORY)

🚨 **ALL code MUST follow SOLID principles. Violations are NOT acceptable.**

#### **S - Single Responsibility Principle**
- Each class should have ONE reason to change
- **Controllers**: Only handle HTTP concerns (routing, validation, authorization, responses)
- **Services**: Only orchestrate business logic (transactions, cache clearing, event firing)
- **Actions**: Only perform single CRUD operations on models
- **Models**: Only define data structure, relationships, and casts
- ❌ **NEVER** put business logic in Controllers or Actions
- ❌ **NEVER** put CRUD operations directly in Services

#### **O - Open/Closed Principle**
- Classes should be open for extension, closed for modification
- Use **inheritance** and **interfaces** for extensibility
- **Actions** should remain unchanged when new features are added
- Add new Actions instead of modifying existing ones
- Example: Don't modify `CreateTicketAction` to handle changelogs - create `CreateChangelogAction`

#### **L - Liskov Substitution Principle**
- Derived classes must be substitutable for base classes
- All Repository implementations must honor the same contract
- Service subclasses must not break parent expectations
- Example: `AdminTicketService` must work wherever `TicketService` is expected

#### **I - Interface Segregation Principle**
- Clients should not depend on interfaces they don't use
- Create specific interfaces instead of general-purpose ones
- Example: `TicketRepositoryInterface`, `TicketCacheInterface` - separate concerns
- Avoid fat interfaces with many methods

#### **D - Dependency Inversion Principle**
- Depend on abstractions, not concretions
- Use **constructor injection** for dependencies
- Services depend on Action interfaces, not concrete Actions
- Example: `public function __construct(private CreateTicketAction $createTicketAction)`

#### **Common SOLID Violations to AVOID:**

❌ **BAD** - Action class with business logic:
```php
class VersionCreateAction {
    public function execute($data) {
        $version = Version::create($data);
        // ❌ Business logic in Action!
        if ($data['changelog']) {
            Changelog::create([...]);
        }
        Cache::forget('versions'); // ❌ Cache clearing in Action!
        return $version;
    }
}
```

✅ **GOOD** - Action focuses on single CRUD operation:
```php
class VersionCreateAction {
    public function execute($data) {
        return Version::create($data); // ✅ Single responsibility
    }
}

class VersionService {
    public function create($data) {
        return DB::transaction(function() use ($data) {
            $version = $this->versionCreateAction->execute($data);
            
            // ✅ Business logic in Service
            if ($data['changelog']) {
                $this->changelogCreateAction->execute([
                    'changelogable_type' => Version::class,
                    'changelogable_id' => $version->id,
                    'content' => $data['changelog'],
                ]);
            }
            
            // ✅ Cache clearing in Service
            CacheHelper::clearVersionCaches();
            
            return $version;
        });
    }
}
```

#### **Why SOLID Matters:**
- ✅ **Maintainability**: Easy to understand and modify
- ✅ **Testability**: Each class can be tested in isolation
- ✅ **Scalability**: New features don't break existing code
- ✅ **Reusability**: Actions can be reused in different contexts
- ✅ **Team Collaboration**: Clear boundaries reduce conflicts

**When in doubt, ask yourself:**
1. Does this class have MORE than one reason to change? → Refactor
2. Would adding a feature require modifying this class? → Use extension
3. Am I putting business logic in an Action? → Move to Service
4. Am I putting CRUD in a Service? → Use Action
5. Am I clearing cache in an Action? → Move to Service
6. Am I creating a new taxonomy model for values that could live in a shared tenant-scoped table? → Reuse or extend the shared taxonomy instead

### 1.3 Technology Stack

- **Framework**: Laravel 12.x
- **PHP**: 8.4+
- **Database**: MariaDB 12.0+
- **Cache**: Redis 8.x
- **Storage**: IDriveE2 (S3-compatible)
- **Container**: Docker + Docker Swarm (production)

### 1.4 Community Forum Context

This API now contains the community/forum domain used across the Volvicon websites.

Core forum areas:

1. Public browsing: topics, post feeds, post detail pages, and public comment threads.
2. Authenticated user actions: create posts, manage own posts, like posts and comments, comment, and report content.
3. Admin moderation: manage topics, moderate posts and comments, review reports, and fetch forum stats.

Primary code locations:

1. `app/Models/ForumTopic.php`, `ForumPost.php`, `ForumComment.php`, `ForumLike.php`, `ForumReport.php`
2. `app/Services/Forum/`
3. `app/Http/Controllers/V1/Forum/`
4. `app/Http/Requests/V1/Forum/` and `app/Http/Resources/V1/Forum/`
5. `tests/Feature/Forum/`

Important expectations for future changes:

1. Keep public forum endpoints read-only and scoped to publicly visible content.
2. Keep admin forum endpoints under `/api/v1/admin/forum/*` and authenticated user endpoints under `/api/v1/forum/*`.
3. Preserve topic/post/comment/report count integrity when deleting or moving forum content.
4. Add or update forum feature tests when changing listing filters, moderation behavior, or cleanup logic.

---

## 2. API Design Standards

### 2.1 REST Resource Pattern

```php
// Standard CRUD endpoints
GET    /api/v1/tickets              # List all (with filters)
POST   /api/v1/tickets              # Create new
GET    /api/v1/tickets/{uuid}       # Show single
PATCH  /api/v1/tickets/{uuid}       # Update
DELETE /api/v1/tickets/{uuid}       # Delete
```

### 2.2 Action-Based Endpoints

```php
// State transitions and complex operations
POST /api/v1/tickets/{uuid}/actions/resolve
POST /api/v1/tickets/{uuid}/actions/close
POST /api/v1/tickets/{uuid}/actions/reopen
POST /api/v1/tickets/{uuid}/actions/lock
POST /api/v1/tickets/{uuid}/actions/archive
```

### 2.3 Sub-Resource Pattern

```php
// Nested resources
GET    /api/v1/tickets/{uuid}/messages
POST   /api/v1/tickets/{uuid}/messages
GET    /api/v1/tickets/{uuid}/assignments
POST   /api/v1/tickets/{uuid}/assignments
DELETE /api/v1/tickets/{uuid}/assignments/{id}
```

### 2.4 Collection Operations

```php
// Statistics and aggregations
GET /api/v1/tickets/statistics
GET /api/v1/licenses/stats
GET /api/v1/users/statistics

// Batch operations
POST /api/v1/licenses/actions/batch-approve
POST /api/v1/tickets/actions/bulk-assign
```

### 2.5 Query Parameter Standards

```php
// Filtering
GET /api/v1/tickets?status=open&priority=high&assigned_to=123

// Pagination
GET /api/v1/tickets?page=2&per_page=20

// Sorting
GET /api/v1/tickets?sort=created_at&order=desc

// Field selection
GET /api/v1/tickets?fields=id,title,status

// Includes (eager loading)
GET /api/v1/tickets?include=messages,assignments
```

---

## 3. Layer Responsibilities

### 3.1 Controllers (`app/Http/Controllers/V1/`)

**✅ Controllers SHOULD:**
- Route requests to appropriate services
- Validate input using FormRequest classes
- Return formatted responses using Resource classes
- Handle HTTP concerns (status codes, headers)
- Authorize using policies

**❌ Controllers SHOULD NOT:**
- Contain business logic
- Query databases directly
- Manipulate data
- Clear caches
- Create/update models

**Example:**
```php
<?php

namespace App\Http\Controllers\V1\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Resources\Tickets\TicketResource;
use App\Services\Tickets\TicketService;
use App\Http\Responses\ApiResponse;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}
    
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);
        
        $ticket = $this->ticketService->createTicket(
            $request->validated()
        );
        
        return ApiResponse::success(
            new TicketResource($ticket),
            'Ticket created successfully',
            201
        );
    }
}
```

### 3.2 Services (`app/Services/{Domain}/`)

**✅ Services SHOULD:**
- Orchestrate business logic
- Call multiple actions if needed
- Handle transactions
- Clear relevant caches
- Fire events
- Log operations
- Handle error scenarios

**❌ Services SHOULD NOT:**
- Perform direct CRUD (use Actions)
- Return HTTP responses
- Validate input (use Requests)
- Format output (use Resources)

**Example:**
```php
<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\CreateTicketAction;
use App\Actions\Tickets\AssignTicketAction;
use App\Helpers\CacheHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function createTicket(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            // Create ticket using Action
            $ticket = app(CreateTicketAction::class)->execute($data);
            
            // Auto-assign to support user with least workload
            $assignee = app(AssignTicketAction::class)
                ->autoAssign($ticket);
            
            // Clear relevant caches
            CacheHelper::clearTicketCaches($ticket->user_id);
            
            // Fire event
            event(new TicketCreated($ticket));
            
            // Log
            Log::info('Ticket created', [
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
            ]);
            
            return $ticket->fresh(['messages', 'assignments.user']);
        });
    }
}
```

### 3.3 Actions (`app/Actions/{Domain}/`)

**✅ Actions SHOULD:**
- Perform single CRUD operations
- Use Pipeline & Filters for queries
- Handle model creation/updates
- Apply business rules at data level
- Return models or collections

**❌ Actions SHOULD NOT:**
- Call other actions
- Create caches
- Clear caches
- Fire events
- Handle transactions (Service responsibility)

**Example:**
```php
<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Filters\FilterByStatus;
use App\Filters\FilterByPriority;
use App\Filters\FilterByAssignee;
use Illuminate\Pipeline\Pipeline;

class GetTicketsAction
{
    public function execute(array $data)
    {
        return Pipeline::send(Ticket::query())
            ->through([
                new FilterByStatus(data_get($data, 'filters.status')),
                new FilterByPriority(data_get($data, 'filters.priority')),
                new FilterByAssignee(data_get($data, 'filters.assigned_to')),
            ])
            ->thenReturn()
            ->with(['user:id,name,email', 'assignments.user'])
            ->select('id', 'uuid', 'title', 'status', 'priority', 'created_at')
            ->orderBy(data_get($data, 'sort', 'created_at'), data_get($data, 'order', 'desc'))
            ->paginate(data_get($data, 'per_page', 20));
    }
}
```

### 3.4 Models (`app/Models/`)

**✅ Models SHOULD:**
- Define relationships
- Define casts and mutators
- Implement interfaces (HasMedia, HasRoles)
- Define scopes
- Handle soft deletes
- Register media conversions

**❌ Models SHOULD NOT:**
- Contain business logic
- Query other models
- Clear caches
- Fire custom events (use observers)
- Format data for API

**Example:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;
    
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
    ];
    
    protected $casts = [
        'status' => \App\Enums\TicketStatus::class,
        'priority' => \App\Enums\TicketPriority::class,
        'created_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
    
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
    
    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', TicketStatus::OPEN);
    }
    
    // Media conversions
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->nonQueued();
    }
}
```

---

## 4. File Organization

```
api/
├── app/
│   ├── Actions/              # CRUD operations
│   │   ├── Tickets/
│   │   │   ├── CreateTicketAction.php
│   │   │   ├── UpdateTicketAction.php
│   │   │   ├── GetTicketsAction.php
│   │   │   └── DeleteTicketAction.php
│   │   └── Licenses/
│   │
│   ├── Services/             # Business logic orchestration
│   │   ├── Tickets/
│   │   │   ├── TicketService.php
│   │   │   └── AssignmentService.php
│   │   └── Licenses/
│   │
│   ├── Http/
│   │   ├── Controllers/V1/   # Route handlers
│   │   │   ├── Tickets/
│   │   │   │   ├── TicketController.php
│   │   │   │   ├── TicketActionController.php
│   │   │   │   └── AssignmentController.php
│   │   │   └── Licenses/
│   │   │
│   │   ├── Requests/         # Validation
│   │   │   ├── Tickets/
│   │   │   │   ├── StoreTicketRequest.php
│   │   │   │   └── UpdateTicketRequest.php
│   │   │   └── Licenses/
│   │   │
│   │   ├── Resources/        # API responses
│   │   │   ├── Tickets/
│   │   │   │   ├── TicketResource.php
│   │   │   │   └── TicketCollection.php
│   │   │   └── Licenses/
│   │   │
│   │   └── Responses/        # Response helpers
│   │       └── ApiResponse.php
│   │
│   ├── Filters/              # Pipeline filters
│   │   ├── FilterByStatus.php
│   │   ├── FilterByPriority.php
│   │   └── FilterByDate.php
│   │
│   ├── Helpers/              # Helper functions
│   │   ├── CacheHelper.php
│   │   └── StorageHelper.php
│   │
│   ├── Enums/                # Enum classes
│   │   ├── TicketStatus.php
│   │   ├── TicketPriority.php
│   │   └── LicenseStatus.php
│   │
│   ├── Policies/             # Authorization
│   │   ├── TicketPolicy.php
│   │   └── LicensePolicy.php
│   │
│   └── Models/               # Eloquent models
│       ├── Ticket.php
│       └── License.php
│
└── routes/
    ├── api.php               # API v1 routes
    └── api-v2.php            # API v2 routes (future)
```

---

## 5. Request Flow Pattern

### Complete Flow Example

```
1. Request arrives → routes/api.php
   ↓
2. Middleware (auth, throttle, etc.)
   ↓
3. Controller method (TicketController@store)
   ↓
4. Authorization via Policy
   ↓
5. FormRequest validation (StoreTicketRequest)
   ↓
6. Service method (TicketService::createTicket)
   ↓
7. Action execution (CreateTicketAction::execute)
   ↓
8. Model operations
   ↓
9. Cache clearing (CacheHelper)
   ↓
10. Event firing
   ↓
11. Resource transformation (TicketResource)
   ↓
12. JSON response (ApiResponse::success)
```

---

## 6. Storage & Media Management

### 6.1 Critical Rules

🚨 **ALWAYS use `idrivee2` disk for all file storage**
🚨 **NEVER use `local` or `public` disk**
🚨 **ALWAYS use custom logic for upload no use of Spatie Media Library at the moment**

### 6.2 Configuration

```php
// config/filesystems.php
'idrivee2' => [
    'driver' => 's3',
    'key' => env('IDRIVE_ACCESS_KEY'),
    'secret' => env('IDRIVE_SECRET_KEY'),
    'region' => env('IDRIVE_REGION', 'eu-west-3'),
    'bucket' => env('IDRIVE_BUCKET'),
    'url' => env('IDRIVE_URL'),
    'endpoint' => env('IDRIVE_ENDPOINT'),
],
```

### 6.3 Model Setup (Not to be used at the momoent, Uploading on frontend)

```php
<?php

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;
    
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
    
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->nonQueued();
            
        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->nonQueued();
    }
}
```

### 6.4 Upload Pattern (in Service) (Not to be used at the moment, Uploading on frontend)

```php
public function uploadAvatar(User $user, UploadedFile $file): Media
{
    // Clear existing
    $user->clearMediaCollection('avatar');
    
    // Upload to IDriveE2
    $media = $user->addMedia($file)
        ->toMediaCollection('avatar', 'idrivee2');
    
    // Clear cache
    CacheHelper::clearUserCache($user->id);
    
    return $media;
}
```

### 6.5 URL Generation (in Resource)

```php
<?php

namespace App\Http\Resources\Users;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->getFirstMediaUrl('avatar'),
            'avatar_thumb_url' => $this->getFirstMediaUrl('avatar', 'thumb'),
        ];
    }
}
```

---

## 7. Database & Query Standards

### 7.1 Enums

🚨 **NEVER use enums in database migrations**
✅ **ALWAYS use string columns with Enum classes in PHP**

**❌ Wrong:**
```php
// Migration
$table->enum('status', ['open', 'closed', 'resolved']);
```

**✅ Correct:**
```php
// Migration
$table->string('status', 50)->default('open');
$table->index('status');

// Enum Class (app/Enums/TicketStatus.php)
<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';
    
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::ARCHIVED => 'Archived',
        };
    }
}

// Model
protected $casts = [
    'status' => TicketStatus::class,
];
```

### 7.2 Polymorphic Relations

✅ **Use polymorphic relations for scalability**
🚨 **NEVER duplicate index on morphs as morphs is already applying the index**

```php
// Messages can belong to Tickets, Licenses, etc.
// Migration
$table->morphs('messageable');

// Model
class Message extends Model
{
    public function messageable()
    {
        return $this->morphTo();
    }
}

class Ticket extends Model
{
    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
}
```

### 7.3 Pipeline & Filters

**Filter Class:**
```php
<?php

namespace App\Filters;

use Closure;

class FilterByStatus
{
    public function __construct(private ?string $status = null)
    {}
    
    public function handle($query, Closure $next)
    {
        if ($this->status) {
            $query->where('status', $this->status);
        }
        
        return $next($query);
    }
}
```

**Usage in Action:**
```php
use Illuminate\Pipeline\Pipeline;

public function execute(array $data)
{
    return Pipeline::send(Ticket::query())
        ->through([
            new FilterByStatus(data_get($data, 'filters.status')),
            new FilterByPriority(data_get($data, 'filters.priority')),
            new FilterByAssignee(data_get($data, 'filters.assigned_to')),
            new FilterByDateRange(data_get($data, 'filters.date_from'), data_get($data, 'filters.date_to')),
        ])
        ->thenReturn()
        ->with(['user', 'assignments.user'])
        ->paginate(data_get($data, 'per_page', 20));
}
```

### 7.4 Indexes

```php
// Add indexes for commonly queried columns
$table->index('user_id');
$table->index('status');
$table->index('priority');
$table->index(['user_id', 'status']);
$table->index('created_at');
$table->unique('uuid');
```

---

## 8. Caching Strategy

### 8.1 Cache Helper

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    // Cache keys
    private const USER_PREFIX = 'user:';
    private const TICKET_PREFIX = 'ticket:';
    private const LICENSE_PREFIX = 'license:';
    
    // TTL in seconds
    private const DEFAULT_TTL = 3600; // 1 hour
    
    /**
     * Clear all user-related caches
     */
    public static function clearUserCache(int $userId): void
    {
        $tags = [
            self::USER_PREFIX . $userId,
            self::TICKET_PREFIX . 'user:' . $userId,
            self::LICENSE_PREFIX . 'user:' . $userId,
        ];
        
        foreach ($tags as $tag) {
            Cache::tags($tag)->flush();
        }
    }
    
    /**
     * Clear ticket caches
     */
    public static function clearTicketCaches(?int $userId = null): void
    {
        Cache::tags([self::TICKET_PREFIX . 'list'])->flush();
        
        if ($userId) {
            Cache::tags([self::TICKET_PREFIX . 'user:' . $userId])->flush();
        }
    }
    
    /**
     * Clear license caches
     */
    public static function clearLicenseCaches(?int $userId = null): void
    {
        Cache::tags([self::LICENSE_PREFIX . 'list'])->flush();
        
        if ($userId) {
            Cache::tags([self::LICENSE_PREFIX . 'user:' . $userId])->flush();
        }
    }
    
    /**
     * Remember with tags
     */
    public static function remember(string $key, array $tags, int $ttl, Closure $callback)
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }
}
```

### 8.2 Usage in Service

```php
public function createTicket(array $data): Ticket
{
    $ticket = DB::transaction(function () use ($data) {
        $ticket = app(CreateTicketAction::class)->execute($data);
        
        // Clear caches
        CacheHelper::clearTicketCaches($ticket->user_id);
        
        return $ticket;
    });
    
    return $ticket;
}
```

### 8.3 Caching Queries (in Action)

```php
public function getTickets(int $userId, array $filters): LengthAwarePaginator
{
    $cacheKey = 'tickets:user:' . $userId . ':' . md5(json_encode($filters));
    $tags = ['ticket:list', 'ticket:user:' . $userId];
    
    return CacheHelper::remember($cacheKey, $tags, 3600, function () use ($userId, $filters) {
        return Pipeline::send(Ticket::query()->where('user_id', $userId))
            ->through([/* filters */])
            ->thenReturn()
            ->paginate();
    });
}
```

---

## 9. Authorization & Security

### 9.1 Policy Pattern

🚨 **ALWAYS use Policies, NEVER custom authorization checks**

**Create Policy:**
```bash
docker exec -it volvicon-api php artisan make:policy TicketPolicy --model=Ticket
```

**Policy Implementation:**
```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'support-assistant']);
    }
    
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id 
            || $user->hasAnyRole(['super-admin', 'admin', 'support-assistant']);
    }
    
    public function create(User $user): bool
    {
        return true; // All authenticated users can create tickets
    }
    
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id 
            || $user->hasAnyRole(['super-admin', 'admin']);
    }
    
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('super-admin');
    }
    
    public function resolve(User $user, Ticket $ticket): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'support-assistant']);
    }
}
```

**Usage in Controller:**
```php
public function store(StoreTicketRequest $request): JsonResponse
{
    $this->authorize('create', Ticket::class);
    
    $ticket = $this->ticketService->createTicket($request->validated());
    
    return ApiResponse::success(new TicketResource($ticket), 'Ticket created', 201);
}

public function update(UpdateTicketRequest $request, string $uuid): JsonResponse
{
    $ticket = Ticket::where('uuid', $uuid)->firstOrFail();
    $this->authorize('update', $ticket);
    
    $ticket = $this->ticketService->updateTicket($ticket, $request->validated());
    
    return ApiResponse::success(new TicketResource($ticket), 'Ticket updated');
}
```

### 9.2 Rate Limiting

```php
// routes/api.php
Route::post('/tickets', [TicketController::class, 'store'])
    ->middleware(['auth.api', 'throttle:10,1']); // 10 requests per minute

Route::post('/license/verify', [LicenseVerifyController::class, 'verify'])
    ->middleware('throttle:60,1'); // 60 requests per minute
```

---

## 10. Response Standards

### 10.1 ApiResponse Helper

```php
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Success response
     */
    public static function success(
        $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ], $meta),
        ], $status);
    }
    
    /**
     * Error response
     */
    public static function error(
        string $message,
        $errors = null,
        int $status = 400,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ], $meta),
        ], $status);
    }
    
    /**
     * Paginated response
     */
    public static function paginated($data, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 200, [
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }
}
```

### 10.2 Resource Classes

**Single Resource:**
```php
<?php

namespace App\Http\Resources\Tickets;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'user' => new UserResource($this->whenLoaded('user')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'assignments' => AssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

**Collection Resource:**
```php
<?php

namespace App\Http\Resources\Tickets;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TicketCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'tickets' => $this->collection,
        ];
    }
}
```

### 10.3 Standard HTTP Status Codes

```php
200 OK          → Successful GET, PATCH
201 Created     → Successful POST (resource created)
204 No Content  → Successful DELETE
400 Bad Request → Validation error
401 Unauthorized → Authentication required
403 Forbidden   → Authorization failed
404 Not Found   → Resource not found
422 Unprocessable Entity → Validation failed
429 Too Many Requests → Rate limit exceeded
500 Internal Server Error → Server error
```

---

## 11. Testing Requirements

### 11.1 Test Structure

```bash
tests/
├── Feature/              # Integration tests
│   ├── Tickets/
│   │   ├── CreateTicketTest.php
│   │   ├── UpdateTicketTest.php
│   │   └── TicketActionsTest.php
│   └── Licenses/
│
└── Unit/                 # Unit tests
    ├── Actions/
    ├── Services/
    └── Helpers/
```

### 11.2 Feature Test Example (Pest)

```php
<?php

use App\Models\User;
use App\Models\Ticket;
use App\Enums\TicketStatus;

test('authenticated user can create ticket', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test description',
            'priority' => 'high',
        ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'uuid',
                'title',
                'status',
            ],
        ]);
    
    expect(Ticket::count())->toBe(1);
});

test('admin can resolve ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();
    
    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/v1/tickets/{$ticket->uuid}/actions/resolve", [
            'resolution_note' => 'Issue resolved',
        ]);
    
    $response->assertStatus(200);
    
    expect($ticket->fresh()->status)->toBe(TicketStatus::RESOLVED);
});
```

### 11.3 Running Tests in Docker

```bash
# Run all tests
docker exec -it volvicon-api php artisan test

# Run specific test file
docker exec -it volvicon-api php artisan test tests/Feature/Tickets/CreateTicketTest.php

# Run with coverage
docker exec -it volvicon-api php artisan test --coverage
```

---

## 12. Docker Environment

### 12.1 Key Information

- **Development**: `docker-compose.yml`
- **Production**: Docker Swarm (`docker-swarm.yml`)
- **API Container**: `volvicon-api`
- **Internal Port**: 8000
- **External Port**: 20020

### 12.2 Common Commands

```bash
# Enter API container
docker exec -it volvicon-api bash

# Run Artisan commands
docker exec -it volvicon-api php artisan migrate
docker exec -it volvicon-api php artisan db:seed
docker exec -it volvicon-api php artisan make:controller V1/Tickets/TicketController
docker exec -it volvicon-api php artisan make:model Ticket -m

# View logs
docker logs volvicon-api
docker logs -f volvicon-api  # Follow

# Clear cache
docker exec -it volvicon-api php artisan cache:clear
docker exec -it volvicon-api php artisan config:clear
docker exec -it volvicon-api php artisan route:clear

# Composer
docker exec -it volvicon-api composer install
docker exec -it volvicon-api composer require package/name

# Database
docker exec -it volvicon-api php artisan migrate:fresh --seed
docker exec -it volvicon-api php artisan migrate:rollback
```

### 12.3 Environment Variables

```bash
# .env (API service)
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:20020

DB_HOST=db
DB_PORT=3306
DB_DATABASE=volvicon
DB_USERNAME=volvicon
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

IDRIVE_ACCESS_KEY=your_key
IDRIVE_SECRET_KEY=your_secret
IDRIVE_REGION=eu-west-3
IDRIVE_BUCKET=volvicon
IDRIVE_URL=https://e2-s3.eu-west-3.idrivee2.com
IDRIVE_ENDPOINT=https://e2-s3.eu-west-3.idrivee2.com
```

---

## 13. Code Examples

### 13.1 Complete CRUD Feature

**1. Migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status', 50)->default('open');
            $table->string('priority', 50)->default('medium');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }
};
```

**2. Enum:**
```php
<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    
    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }
}
```

**3. Model:**
```php
<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['uuid', 'user_id', 'title', 'description', 'status', 'priority'];
    
    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'resolved_at' => 'datetime',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ticket) {
            $ticket->uuid = (string) Str::uuid();
        });
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
}
```

**4. Request:**
```php
<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled in controller via policy
    }
    
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(TicketPriority::values())],
        ];
    }
}
```

**5. Action:**
```php
<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;

class CreateTicketAction
{
    public function execute(array $data): Ticket
    {
        return Ticket::create([
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => 'open',
        ]);
    }
}
```

**6. Service:**
```php
<?php

namespace App\Services\Tickets;

use App\Actions\Tickets\CreateTicketAction;
use App\Helpers\CacheHelper;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function createTicket(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $ticket = app(CreateTicketAction::class)->execute($data);
            
            CacheHelper::clearTicketCaches(auth()->id());
            
            return $ticket->load('user', 'messages');
        });
    }
}
```

**7. Resource:**
```php
<?php

namespace App\Http\Resources\Tickets;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

**8. Controller:**
```php
<?php

namespace App\Http\Controllers\V1\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Resources\Tickets\TicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}
    
    public function store(StoreTicketRequest $request)
    {
        $this->authorize('create', Ticket::class);
        
        $ticket = $this->ticketService->createTicket($request->validated());
        
        return ApiResponse::success(
            new TicketResource($ticket),
            'Ticket created successfully',
            201
        );
    }
}
```

**9. Route:**
```php
Route::middleware('auth.api')->group(function () {
    Route::post('/tickets', [TicketController::class, 'store']);
});
```

---

## Summary Checklist

When implementing new features, ensure:

- [ ] REST + Action-based routes defined
- [ ] FormRequest for validation created
- [ ] Policy for authorization implemented
- [ ] Action class for CRUD operation
- [ ] Service class for business logic
- [ ] Resource class for API response
- [ ] Cache clearing via CacheHelper
- [ ] Enum classes for status/types (not in migration)
- [ ] Polymorphic relations used where applicable
- [ ] Pipeline & filters for complex queries
- [ ] idrivee2 disk for file uploads
- [ ] Tests written (Feature + Unit)
- [ ] All commands run in Docker container
- [ ] Standard JSON response format used

---

**Last Updated**: January 23, 2026  
**Version**: 2.0
