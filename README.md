# Laravel API Starter Kit with Filament CMS Admin Management

A production-ready Laravel 13 API starter kit with Filament CMS admin management, Sanctum token auth, media uploads, OpenAPI docs, and code quality tooling. Clone and build — not configure.

---

## Stack

| Layer | Package | Version |
|---|---|---|
| Framework | `laravel/framework` | 13 |
| Language | PHP | 8.4+ |
| Authentication | `laravel/sanctum` | 4 |
| Admin Panel | `filament/filament` | 5 |
| Media Library | `spatie/laravel-medialibrary` | 11 |
| Query Builder | `spatie/laravel-query-builder` | 7 |
| API Docs | `dedoc/scramble` | 0.13 |
| Debugging | `laravel/telescope` | 5 |
| Testing | `pestphp/pest` | 4 |
| Static Analysis | `nunomaduro/larastan` | 3 (PHPStan level 6) |
| Code Style | `laravel/pint` | 1 |
| Refactoring | `rector/rector` + `driftingly/rector-laravel` | 2 |

---

## Features

- Token-based auth with multi-device support and per-token revocation
- Separate Filament admin panel with its own `Admin` model and auth guard
- Decoupled two-phase media system — upload first, attach later (Stripe/Slack style)
- User avatar management with Spatie Media Library (auto-resize, single-file collection)
- Collection filtering, sorting, and pagination via Spatie Query Builder (`filter[name]=`, `sort=-created_at`)
- API versioning (`/api/v1/`) with a clear path to add `/api/v2/`
- Centralized JSON response system with machine-readable error codes
- Global exception handler — all errors return consistent JSON, never HTML
- Four named rate limiters, fully env-driven
- Request ID tracing (`X-Request-ID`) on every request and response
- Structured JSON request logging
- Password reset with anti-enumeration (same response for known/unknown emails)
- Email verification via signed URLs
- CORS configured for local and production
- OpenAPI docs auto-generated at `/docs` — zero annotations required

---

## Quick Start

```bash
git clone https://github.com/your-username/laravel-api-starter.git
cd laravel-api-starter

composer install
cp .env.example .env
php artisan key:generate
```

Open `.env` and configure your database, then:

```bash
php artisan migrate
php artisan serve
```

| URL | What it is |
|---|---|
| `http://localhost:8000/api/v1` | API base URL |
| `http://localhost:8000/docs` | Interactive OpenAPI docs (Scramble) |
| `http://localhost:8000/docs/api` | Raw OpenAPI JSON (Postman / Insomnia) |
| `http://localhost:8000/admin` | Filament admin panel |
| `http://localhost:8000/telescope` | Request / query debugger (dev only) |

Create your first admin account before opening the panel:

```bash
php artisan admin:create
```

**Full dev environment** (server + queue worker + log tail + Vite, all in one terminal):

```bash
composer run dev
```

**One-command setup** (install, copy `.env`, generate key, migrate, build assets):

```bash
composer run setup
```

---

## Project Structure

```
app/
  Console/Commands/
  Filament/Resources/
  Http/
    Controllers/
      Api/V1/
    Middleware/
    Requests/
      Api/V1/
    Resources/
      V1/
    Traits/
  Models/
  Providers/
  Services/
  Support/
config/
database/
routes/                            
  api/
    v1.php                            # all v1 routes
  console.php                         # scheduled commands
tests/
```

---

## Admin Panel

The Filament admin panel runs at `/admin` and uses a **dedicated `Admin` model** — completely separate from the `User` model used by the API. API Bearer tokens have no effect on panel access.

### Create your first admin

```bash
php artisan admin:create
```

```
 Name: Jane Doe
 Email: jane@example.com
 Password:

Admin created successfully!
ID:    1
Email: jane@example.com
```

The command validates email format, enforces uniqueness, and hashes the password automatically via the model cast.

### Admin guard

The panel uses a dedicated `admin` guard backed by the `admins` table.

```php
// config/auth.php
'guards' => [
    'admin' => ['driver' => 'session', 'provider' => 'admins'],
],
'providers' => [
    'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
],
```

---

## Token System (Sanctum)

### Token expiration

By default, Sanctum tokens **do not expire** (`expiration: null` in `config/sanctum.php`). For production, set a global TTL:

```php
// config/sanctum.php
'expiration' => 60 * 24 * 30, // 30 days in minutes
```

Or set expiry per token at creation time:

```php
$user->createToken('device-name', ['*'], now()->addDays(30));
```

Recommended strategy by client type:

| Client | Recommended TTL | Reasoning |
|---|---|---|
| Mobile app | 90 days | Long-lived, user expects persistent login |
| SPA | 7–30 days | Moderate, refresh on activity |
| Third-party / CI | No expiry | Explicit revocation only |

### Token naming strategy

The login endpoint accepts an optional `device_name` field. This name is stored against the token and shown in `GET /api/v1/auth/tokens` so users know which sessions are active.

```json
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "secret",
  "device_name": "iPhone 15 Pro"
}
```

If `device_name` is omitted, the token is named `"API Token"`. Clients **should** always send a descriptive name. Recommended conventions:

- Mobile: `"iPhone 15 Pro"` / `"Samsung Galaxy S24"`
- Web browser: `"Chrome on macOS"`
- CLI / CI: `"GitHub Actions"` / `"Deploy Bot"`

### Multi-device behaviour

Each login creates an **independent token**. Tokens are scoped to a single device and do not share sessions.

| Action | Endpoint | Behaviour |
|---|---|---|
| Login | `POST /auth/login` | Issues a new named token |
| List sessions | `GET /auth/tokens` | Returns all active tokens with `last_used_at` |
| Logout current | `POST /auth/logout` | Deletes **only** the token on this request |
| Logout all | `POST /auth/logout-all` | Deletes **all** tokens for this user |
| Revoke one | `DELETE /auth/tokens/{id}` | Deletes one specific token by ID |

**"Logout all except current"** is not a built-in endpoint. To implement it:

```php
$user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
```

### Login rate limiting

Login is throttled at two levels:

1. **Route middleware** — `throttle:auth` (10 req/min per IP, configured in `config/rate-limiting.php`)
2. **`LoginRequest`** — additional per-email+IP lockout after 5 failed attempts, with a backoff period

---

## API Endpoints

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/auth/register` | — | Register and receive token |
| `POST` | `/api/v1/auth/login` | — | Login and receive token |
| `GET` | `/api/v1/auth/me` | Bearer | Authenticated user |
| `POST` | `/api/v1/auth/logout` | Bearer | Revoke current token |
| `POST` | `/api/v1/auth/logout-all` | Bearer | Revoke all tokens |
| `GET` | `/api/v1/auth/tokens` | Bearer | List active tokens |
| `DELETE` | `/api/v1/auth/tokens/{id}` | Bearer | Revoke specific token |
| `POST` | `/api/v1/auth/password/forgot` | — | Send reset link |
| `GET` | `/api/v1/auth/password/reset/{token}` | — | Get reset form data |
| `POST` | `/api/v1/auth/password/reset` | — | Reset password |
| `POST` | `/api/v1/auth/email/send-notification` | Bearer | Resend verification email |
| `GET` | `/api/v1/auth/email/verify/{id}/{hash}` | Signed | Verify email address |

### Profile

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `PATCH` | `/api/v1/profile` | Bearer | Update name |
| `PUT` | `/api/v1/profile/password` | Bearer | Change password |
| `POST` | `/api/v1/profile/avatar` | Bearer | Attach a previously uploaded file as avatar (`media_uuid`) |
| `DELETE` | `/api/v1/profile/avatar` | Bearer | Remove avatar |

### Media

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/media/upload` | Bearer + `throttle:uploads` | Phase 1 — store file, receive UUID |
| `GET` | `/api/v1/media/{uuid}` | Bearer | Get a fresh temporary URL for any media UUID |
| `GET` | `/api/v1/media/serve/{uuid}` | Signed URL | Serve local-disk file (no Bearer required) |

---

## Response System

Every response follows a consistent envelope. Machine-readable error codes mean clients never need to parse message strings.

### Consistency enforcement

The envelope is **globally enforced**, not opt-in per controller:

- `app/Support/ApiResponse.php` is the single source of truth for all response shapes
- The exception handler in `bootstrap/app.php` calls `ApiResponse` directly — framework errors (`404`, `401`, `429`, etc.) all return the same shape
- All controllers extend `ApiController`, which uses the `ApiResponder` trait to delegate to `ApiResponse`
- No controller ever calls `response()->json()` directly

This means the response shape cannot diverge as the codebase grows.

### How `data` gets into responses

All single-resource and mutation responses go through `ApiResponse::message()`, which conditionally adds `"data"` only when a resource is provided:

```php
// With resource — adds "data" key
$this->respondWithMessage('User updated.', new UserResource($user));

// Without resource — no "data" key
$this->respondEmpty('User deleted.');
```

Paginated collections are returned directly via Laravel's resource collection, which automatically adds `"data"`, `"links"`, and `"meta"`:

```php
// Collection — Laravel wraps in {"data": [...], "links": {...}, "meta": {...}}
return UserResource::collection(User::paginate());
```

Null is never included in the response. `"data": null` and `"error": null` do not appear.

### Response shapes

**Single resource** (GET, e.g. `me`)
```json
{ "message": "Authenticated user.", "data": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" } }
```

**Paginated collection** (GET)
```json
{
  "data": [{ "id": 1, "name": "Jane Doe" }],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 60 }
}
```

**Mutation with resource** (`store`, `update`)
```json
{ "message": "User updated.", "data": { "id": 1, "name": "Jane Doe" } }
```

**Empty action** (`delete`, `logout`)
```json
{ "message": "Logged out successfully." }
```

**Auth response**
```json
{
  "message": "Login successful.",
  "data": {
    "token": "1|abc...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" }
  }
}
```

**Validation error**
```json
{
  "message": "The email field is required.",
  "error": {
    "code": "VALIDATION_ERROR",
    "details": { "email": ["The email field is required."] }
  }
}
```

**Error**
```json
{ "message": "Resource not found.", "error": { "code": "NOT_FOUND" } }
```

### Message standardisation

| Action | `message` format |
|---|---|
| `store` | `"X created."` |
| `update` | `"X updated."` |
| `delete` | `"X deleted."` |
| `login` | `"Login successful."` |
| `logout` | `"Logged out successfully."` |

Messages are human-readable. Clients should act on `error.code`, not parse the message string.

### Error codes

| Code | HTTP | Trigger |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Failed form request |
| `UNAUTHENTICATED` | 401 | Missing or expired token |
| `FORBIDDEN` | 403 | Policy/gate denied |
| `NOT_FOUND` | 404 | Model or route not found |
| `METHOD_NOT_ALLOWED` | 405 | Wrong HTTP verb |
| `INVALID_SIGNATURE` | 403 | Expired or tampered signed URL |
| `RATE_LIMITED` | 429 | Throttle limit hit |
| `SERVER_ERROR` | 500 | Unexpected server error |

### Controller helpers

```php
// Mutation with resource (store, update)
$this->respondWithMessage('Post created.', new PostResource($post), 201);
$this->respondWithMessage('Post updated.', new PostResource($post));

// Auth — token + user in data
$this->respondWithToken($token->plainTextToken, new UserResource($user));

// Empty — delete, logout, side-effect-only
$this->respondEmpty('Post deleted.');

// Domain error from inside a controller
$this->respondWithError('Subscription expired.', 'SUBSCRIPTION_EXPIRED', 403);
```

---

## Data Ownership

This is the authorization contract for the API and the Filament panel.

### User data rules

| Rule | Implementation |
|---|---|
| Users access only their own data | Enforced in each controller via `$request->user()` — never pass IDs from the request when the authenticated user's record is the target |
| Users cannot access each other's data | No shared resource endpoints exist by default — add policies when introducing them |
| Users can only attach their own uploads | `MediaService::isStagingOwnedBy()` verifies the `Upload` record belongs to the authenticated user before attachment |

### Admin data rules

| Rule | Implementation |
|---|---|
| Admins access all user data via the Filament panel | Filament resources have no per-row ownership checks — all admins see all records |
| Admins can delete user media via the panel | `UserResource` in Filament shows media; deletion is a panel-level action |
| Admins cannot use API Bearer tokens | `Admin` model is not returned by `auth:sanctum` — the guards are entirely separate |
| API endpoints are always scoped to the authenticated `User` | Even if an admin's email matches a user account, their session token cannot access the API |

### Extending ownership

When adding new resources, follow this pattern:

```php
// Policy — scope all queries to the authenticated user
public function viewAny(User $user): bool { return true; }
public function view(User $user, Post $post): bool { return $user->id === $post->user_id; }
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
public function delete(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

---

## Media System (Spatie Media Library)

The kit uses [Spatie Media Library v11](https://spatie.be/docs/laravel-medialibrary) for all file handling with a **decoupled two-phase design** — upload first, attach later. No `model_type` or `model_id` is ever accepted from the API.

### Two-phase upload flow

```
Phase 1 — Upload (file only)
  POST /api/v1/media/upload
  Body: { file: <binary> }
  ← { "message": "File uploaded.", "data": { "uuid": "...", "url": "...", "file_name": "..." } }

Phase 2 — Attach (uuid only, no file transfer)
  POST /api/v1/profile/avatar
  Body: { "media_uuid": "550e8400-e29b-41d4-a716-446655440000" }
  ← { "message": "Avatar updated.", "data": { ...user resource... } }
```

The client holds the UUID between the two requests. The server verifies ownership at attachment time and then moves the file from the staging area to the target model's collection.

### Staging model (`Upload`)

`App\Models\Upload` is a lightweight staging model that implements `HasMedia`. Every uploaded file is attached here first. When the file is attached to its final destination (e.g. user avatar), the `Upload` record and its file are deleted automatically via Spatie's observer.

```
uploads
  id          — internal, never exposed
  user_id     — FK to users (for ownership verification)
  timestamps
```

Orphaned rows (uploaded but never attached) should be pruned by a scheduled command. Add to `routes/console.php`:

```php
Schedule::command('media-library:clean')->daily();
```

### `MediaService`

All media logic lives in `App\Services\MediaService`:

| Method | Description |
|---|---|
| `storeUpload(file, user)` | Creates an `Upload` record, attaches the file via Spatie, returns the `Media` record |
| `attachUserAvatar(stagingMedia, user)` | Moves file from `Upload` staging to `User` avatar collection, deletes the `Upload` |
| `temporaryUrl(media, minutes)` | Returns pre-signed S3 URL or Laravel signed route — never exposes storage paths |
| `findByUuid(uuid)` | Looks up any media record by UUID — DB ID never accepted |
| `isStagingOwnedBy(media, user)` | Verifies the staged file belongs to the authenticated user before attachment |
| `delete(media)` | Permanently deletes the record and file from disk |

To support attachment for other models (e.g. `Post` cover image), add a dedicated method alongside `attachUserAvatar`:

```php
public function attachPostCover(Media $stagingMedia, Post $post): Media
{
    $newMedia = $post
        ->addMediaFromDisk($stagingMedia->getPathRelativeToRoot(), $stagingMedia->disk)
        ->usingFileName($stagingMedia->file_name)
        ->toMediaCollection('cover');

    $stagingMedia->model->delete();

    return $newMedia;
}
```

### User avatar collection

`User` implements `HasMedia` with a single-file `avatar` collection and a `thumb` conversion:

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('avatar')
        ->singleFile()          // automatically removes the previous file on attachment
        ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
}

public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')->width(150)->height(150);
}
```

`getAvatarUrl()` returns the correct URL for any disk:

```php
public function getAvatarUrl(): string
{
    $media = $this->getFirstMedia('avatar');

    if ($media?->disk === 's3') {
        return Storage::disk('s3')->temporaryUrl($media->getPath(), now()->addMinutes(15));
    }

    return $media?->getUrl('thumb') ?: $media?->getUrl()
        ?: 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&size=150&background=random';
}
```

### Media UUID

Spatie generates a **UUID v4** (random, non-sequential) for every `media` record. This is the only public identifier. The auto-increment `id` is never returned to clients or accepted in requests.

```json
{ "uuid": "550e8400-e29b-41d4-a716-446655440000", "url": "...", "file_name": "photo.jpg", "mime_type": "image/jpeg", "size": 204800 }
```

### Temporary URL behaviour

| Disk | URL type | TTL | Rotation |
|---|---|---|---|
| S3 | AWS pre-signed URL | 15 min | Fresh every request — never cached server-side |
| Local | Laravel signed route | 15 min | Fresh every request — never cached server-side |

For clients that display the same media repeatedly (e.g. a profile avatar):

1. Store the `uuid` on the client, not the URL
2. Request a fresh URL from `GET /api/v1/media/{uuid}` when the old one is within ~2 minutes of expiry
3. Never cache URLs in a database — they are intentionally short-lived

### Security guarantees

- UUID v4 — non-sequential, non-guessable, safe to expose
- S3 — pre-signed AWS URLs, bucket name and path never reach the client
- Local disk — files served through the application via signed routes, raw paths never exposed
- No `model_type` or `model_id` ever accepted from the API — arbitrary model binding is not possible
- Re-attachment prevented — `isStagingOwnedBy()` returns false for already-attached media

---

## Filtering, Sorting & Pagination (Spatie Query Builder)

Collection endpoints use [spatie/laravel-query-builder v7](https://spatie.be/docs/laravel-query-builder) for consistent, safe filtering and sorting. Unrecognised filter or sort keys are rejected with a `400` response — clients cannot probe arbitrary columns.

### Query parameters

| Parameter | Example | Description |
|---|---|---|
| `filter[field]=value` | `filter[name]=jane` | Filter by allowed field |
| `sort=field` | `sort=name` | Sort ascending |
| `sort=-field` | `sort=-created_at` | Sort descending (prefix `-`) |
| `sort=a,-b` | `sort=name,-created_at` | Multi-column sort |
| `per_page=N` | `per_page=25` | Items per page (default 15) |
| `page=N` | `page=2` | Page number |

### Example: `GET /api/v1/users` (add when you implement user listing)

```
# Filter
?filter[name]=jane              partial match, case-insensitive
?filter[email]=@example.com     partial match, case-insensitive
?filter[verified]=true          only email-verified users
?filter[verified]=false         only unverified users

# Sort
?sort=name
?sort=-created_at
?sort=email,-name

# Pagination
?page=2&per_page=25
```

### Adding query builder to a new endpoint

```php
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

public function index(): AnonymousResourceCollection
{
    $posts = QueryBuilder::for(Post::class)
        ->allowedFilters(
            AllowedFilter::partial('title'),
            AllowedFilter::exact('status'),
            AllowedFilter::scope('published'),   // calls Post::scopePublished()
        )
        ->allowedSorts(
            AllowedSort::field('title'),
            AllowedSort::field('created_at'),
        )
        ->defaultSort('-created_at')
        ->paginate(request()->integer('per_page', 15))
        ->appends(request()->query());

    return PostResource::collection($posts);
}
```

### Scope filters

Use `AllowedFilter::scope()` for filters that need custom query logic. Define the scope on the model:

```php
// Model
public function scopePublished(Builder $query, bool $value): void
{
    $value
        ? $query->whereNotNull('published_at')
        : $query->whereNull('published_at');
}

// Usage: ?filter[published]=true
```

### Pagination response shape

```json
{
  "data": [{ "id": 1, "name": "Jane Doe" }],
  "links": {
    "first": "/api/v1/users?page=1",
    "last":  "/api/v1/users?page=4",
    "prev":  null,
    "next":  "/api/v1/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 15,
    "total": 60,
    "path": "/api/v1/users"
  }
}
```

---

## Rate Limiting

All limiters are configured in `config/rate-limiting.php` and fully env-driven.

| Limiter | Default | Window | Scope | Use on |
|---------|---------|--------|-------|--------|
| `api` | 60 req | 60 s | user or IP | All resource routes |
| `auth` | 10 req | 60 s | IP | Login, register, password reset |
| `uploads` | 10 req | 60 s | user or IP | File upload endpoints |
| `strict` | 3 req | 60 s | IP | OTP, 2FA, sensitive endpoints |

```php
Route::middleware('throttle:uploads')->post('/media/upload', [MediaController::class, 'upload']);
Route::middleware('throttle:strict')->post('/verify-otp', ...);
```

Override defaults via env without touching code:

```env
RATE_LIMIT_API=120
RATE_LIMIT_AUTH=5
RATE_LIMIT_UPLOADS=20
RATE_LIMIT_STRICT=3
```

---

## Global Middleware

Applied to every API request in order:

| # | Middleware | Purpose |
|---|---|---|
| 1 | `ForceJsonResponse` | Sets `Accept: application/json` — ensures JSON errors on all clients including ones that don't send the header |
| 2 | `RequestId` | Attaches/propagates `X-Request-ID` for distributed tracing |
| 3 | `LogApiRequests` | Writes structured JSON log entry after the response is built |

---

## Queue & Background Jobs

### Default driver

The default queue connection is **`database`** — jobs are stored in the `jobs` table, which is created by the standard Laravel migration. No Redis required to get started.

For production, switch to Redis:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Run workers:

```bash
php artisan queue:work                        # single worker
php artisan queue:work --tries=3 --backoff=5  # with retry and backoff
php artisan horizon                            # Horizon for dashboard + multi-queue
```

### Media conversions

Spatie Media Library can process image conversions (e.g. `thumb`) either synchronously or via queue. To queue conversions, enable it in the Spatie config:

```php
// config/media-library.php
'queue_conversions_by_default' => env('MEDIA_QUEUE_CONVERSIONS', true),
```

When queued, the uploaded file is stored immediately and the conversion job runs asynchronously. The `thumb` URL will return the original until the job completes.

### Retry policy

When adding jobs to this project, follow this convention:

```php
class ProcessMediaConversion implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 120;       // must be less than retry_after in queue config

    public function backoff(): array
    {
        return [1, 5, 10];           // exponential: 1s, 5s, 10s between retries
    }

    public function failed(Throwable $exception): void
    {
        // notify, log, or mark the media record as failed
    }
}
```

### Failed jobs

Failed jobs land in the `failed_jobs` table. To inspect and retry:

```bash
php artisan queue:failed          # list failed jobs
php artisan queue:retry all       # retry all
php artisan queue:retry {id}      # retry one
php artisan queue:flush           # delete all failed
```

---

## Testing

Tests are written with [Pest 4](https://pestphp.com). All tests are feature tests by default.

```bash
php artisan test --compact                    # all tests, compact output
php artisan test --filter=AuthTest            # filter by name
php artisan test --parallel                   # parallel execution
```

### Database isolation

Use `LazilyRefreshDatabase` in `tests/Pest.php`. It only migrates when a test actually hits the database, making test suites with many unit tests faster.

```php
// tests/Pest.php
pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');
```

Do **not** use `RefreshDatabase` — it runs migrations before every test file regardless of whether the test needs the database.

### Factory rules

- Always use factories to create models in tests — never `Model::create()` with hardcoded data
- Use factory states instead of manually overriding attributes:

```php
// Correct
$user = User::factory()->unverified()->create();

// Avoid
$user = User::factory()->create(['email_verified_at' => null]);
```

- Share relationship instances across factories with `recycle()`:

```php
$posts = Post::factory(3)->recycle($user)->create();
```

### Auth testing with Sanctum

```php
use Laravel\Sanctum\Sanctum;

// Act as an authenticated user
it('returns the authenticated user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

// Test token scopes
Sanctum::actingAs($user, ['read']);
```

### API test structure convention

```
tests/
  Feature/
    Auth/
      LoginTest.php
      RegisterTest.php
      TokenManagementTest.php
    Profile/
      UpdateProfileTest.php
      ChangePasswordTest.php
      AvatarTest.php
    Media/
      UploadMediaTest.php
  Unit/
    Services/
      MediaServiceTest.php
```

Each test file covers one controller or service. Group with `describe()` when a controller has multiple related actions:

```php
describe('login', function () {
    it('issues a token on valid credentials', function () { ... });
    it('locks out after 5 failed attempts', function () { ... });
    it('rejects unverified email', function () { ... });
});
```

---

## API Versioning Strategy

### Structure

```
routes/
  api.php          ← registers versioned route groups
  api/
    v1.php         ← all v1 routes
    v2.php         ← all v2 routes (when needed)

app/Http/
  Controllers/
    Api/
      V1/          ← v1 controllers
      V2/          ← v2 controllers (only for changed endpoints)
  Resources/
    V1/            ← v1 resources
    V2/            ← v2 resources (only for changed shapes)
```

### What counts as a breaking change

| Breaking — requires v2 | Non-breaking — safe in v1 |
|---|---|
| Removing a field from a response | Adding a new optional field |
| Renaming a field | Adding a new endpoint |
| Changing a field's type | Making a formerly required field optional |
| Changing endpoint behaviour | Adding new error codes |

### Shared code between versions

Only create a new versioned class when the interface actually changes. Shared logic stays in services and models:

```
app/
  Services/
    MediaService.php      ← shared, no version suffix
  Models/
    User.php              ← shared, no version suffix
  Http/
    Controllers/
      Api/
        V1/UserController.php   ← v1 response shape
        V2/UserController.php   ← v2 response shape (only if changed)
    Resources/
      V1/UserResource.php       ← v1 fields
      V2/UserResource.php       ← v2 fields (only if shape changed)
```

A V2 controller can return a V1 resource if the response shape did not change.

### Deprecation rules

1. Announce deprecation with a response header before removal: `Deprecation: version="v1", date="2026-06-01"`
2. Run both versions in parallel for a minimum of one release cycle
3. Remove the old version only after traffic to it has dropped to zero or a hard deadline is reached
4. Never remove a route — return `410 Gone` with `{"error": {"code": "VERSION_DEPRECATED"}}` so clients get an actionable error

### Adding v2

```bash
touch routes/api/v2.php
```

```php
// routes/api.php
Route::prefix('v2')->name('v2.')->group(base_path('routes/api/v2.php'));
```

---

## Code Quality

### PHPStan (Larastan)

Static analysis at **level 6** via Larastan (PHPStan with Laravel-aware extensions).

```bash
./vendor/bin/phpstan analyse
```

Key settings in `phpstan.neon`:

| Setting | Value | Reason |
|---|---|---|
| `level` | `6` | Catches real bugs without fighting Eloquent/Filament magic methods |
| `treatPhpDocTypesAsCertain` | `false` | Avoids false positives from Laravel's dynamic return types |
| `reportUnmatchedIgnoredErrors` | `false` | Keeps the ignore list from accumulating stale entries |

### Pint

Opinionated code style formatter based on PHP-CS-Fixer.

```bash
./vendor/bin/pint              # fix all files
./vendor/bin/pint --dirty      # fix only changed files (use before committing)
./vendor/bin/pint --test       # check without writing (use in CI)
```

### Rector

Automated refactoring for PHP and Laravel upgrades.

```bash
./vendor/bin/rector process            # apply all refactors
./vendor/bin/rector process --dry-run  # preview changes without writing
```

---

## API Documentation

[Scramble](https://scramble.dedoc.co) auto-generates OpenAPI 3.1 docs from routes, controllers, and resources — no `@OA` annotations required.

```
GET /docs        # Interactive Swagger UI
GET /docs/api    # Raw OpenAPI JSON (use with Postman, Insomnia, etc.)
```

---

## Debugging (dev only)

[Laravel Telescope](https://laravel.com/docs/telescope) is available at `/telescope`. Loaded only in `local` environment, restricted by email allowlist.

```env
TELESCOPE_ADMIN_EMAILS=you@example.com
```

Telescope records: HTTP requests, queries, jobs, mail, notifications, cache, exceptions.

---

## S3 / Storage

Switch from local disk to S3 by updating `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

No code changes required. `MediaService::temporaryUrl()` and `User::getAvatarUrl()` both detect the disk and return the appropriate URL type automatically.

---

## Key Environment Variables

```env
APP_ENV=production
APP_URL=https://api.yourdomain.com
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

QUEUE_CONNECTION=redis          # 'database' for dev, 'redis' for production

FILESYSTEM_DISK=s3              # 'local' for dev, 's3' for production
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@yourdomain.com

CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
CORS_SUPPORTS_CREDENTIALS=false

RATE_LIMIT_API=60
RATE_LIMIT_AUTH=10
RATE_LIMIT_UPLOADS=10
RATE_LIMIT_STRICT=3

SANCTUM_TOKEN_PREFIX=           # optional prefix for secret scanning detection

MEDIA_QUEUE_CONVERSIONS=true    # queue image conversions (recommended for production)

TELESCOPE_ADMIN_EMAILS=you@example.com
```

---

## License

MIT
