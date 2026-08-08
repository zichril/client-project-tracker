# Client Project Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a fullstack Client Project Tracker with a Laravel REST API, Vue 3 Kanban/table SPA, MySQL database, and Docker — ready to submit as a Koda job assessment.

**Architecture:** Four Docker containers (nginx, php-fpm, vite, mysql) behind a single `http://localhost` origin. nginx reverse-proxies `/api/*` to php-fpm via FastCGI and proxies `/` to the Vite dev server, making CORS a non-issue. Laravel handles REST API + Sanctum token auth; Vue 3 SPA consumes the API with Pinia state, Vue Router, and Axios.

**Tech Stack:** Laravel 11 · PHP 8.3-fpm · Vue 3 + Vite · Pinia · Vue Router · Axios · Tailwind CSS v4 (`@tailwindcss/vite`) · vue-draggable-plus · vue-toastification · MySQL 8 · PHPUnit feature tests

## Global Constraints

- PHP image: `php:8.3-fpm` (not `php:8.3-cli`)
- MySQL image: `mysql:8.0`
- Node image: `node:20-alpine` (for the vite container)
- All containers share `app-network` bridge network
- Laravel lives in `backend/`; Vue lives in `frontend/`
- All API routes prefixed `/api` (handled automatically by Laravel 11 `routes/api.php`)
- API responses use camelCase JSON keys matching `test_data.json`
- Status enum values: `Planning`, `In Progress`, `On Hold`, `Completed` (exact strings, spaces included)
- Priority enum values: `Low`, `Medium`, `High`
- Default demo user: `demo@example.com` / `password`
- All PHPUnit tests use `RefreshDatabase` and create their own Sanctum token
- No TypeScript — plain JS throughout the Vue project

---

### Task 1: Docker Infrastructure

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/nginx/Dockerfile`
- Create: `docker/nginx/nginx.conf`
- Create: `docker/php/Dockerfile`
- Create: `.env.example`

**Interfaces:**
- Produces: `http://localhost` (nginx on port 80), `mysql` internal hostname on port 3306, `php` internal hostname on port 9000, `vite` internal hostname on port 5173

- [ ] **Step 1: Write `docker-compose.yml`**

```yaml
services:
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    ports:
      - "80:80"
    depends_on:
      - php
      - vite
    networks:
      - app-network

  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - ./backend:/var/www/html
    depends_on:
      mysql:
        condition: service_healthy
    networks:
      - app-network

  vite:
    image: node:20-alpine
    working_dir: /app
    volumes:
      - ./frontend:/app
    command: sh -c "npm install && npm run dev"
    ports:
      - "5173:5173"
    networks:
      - app-network

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: koda_tracker
      MYSQL_USER: koda
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10
    networks:
      - app-network

networks:
  app-network:
    driver: bridge

volumes:
  mysql-data:
```

- [ ] **Step 2: Write `docker/php/Dockerfile`**

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

- [ ] **Step 3: Write `docker/nginx/Dockerfile`**

```dockerfile
FROM nginx:alpine
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
```

- [ ] **Step 4: Write `docker/nginx/nginx.conf`**

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    location ^~ /api {
        try_files $uri $uri/ /index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass php:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    location / {
        proxy_pass http://vite:5173;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

- [ ] **Step 5: Write `.env.example`**

```env
APP_NAME="Koda Project Tracker"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=koda_tracker
DB_USERNAME=koda
DB_PASSWORD=secret
```

- [ ] **Step 6: Verify Docker builds**

```bash
docker compose build
```
Expected: all images build without errors. The `php` image will take ~2 minutes on first build.

- [ ] **Step 7: Commit**

```bash
git add docker-compose.yml docker/ .env.example
git commit -m "feat: add Docker infrastructure (nginx + php-fpm + vite + mysql)"
```

---

### Task 2: Laravel Scaffold + Sanctum

**Files:**
- Create: `backend/` (via Composer)
- Modify: `backend/.env`
- Modify: `backend/app/Models/User.php` (add `projects()` relationship — after Task 3 creates the model, but `HasApiTokens` is added here)

**Interfaces:**
- Produces: Laravel app booted at `php:9000`, `GET http://localhost/api/up` returns 200

- [ ] **Step 1: Create Laravel project in `backend/`**

Run from repo root:
```bash
composer create-project laravel/laravel backend --prefer-dist
```

- [ ] **Step 2: Copy `.env` from example and set database credentials**

```bash
cp .env.example backend/.env
```

Then open `backend/.env` and ensure these values are set (they should match `.env.example`):
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=koda_tracker
DB_USERNAME=koda
DB_PASSWORD=secret
```

- [ ] **Step 3: Install Laravel Sanctum via the `install:api` command**

```bash
docker compose up -d php mysql
docker compose exec php php artisan key:generate
docker compose exec php php artisan install:api
```

`install:api` publishes the Sanctum migration, adds `HasApiTokens` to the User model, and ensures `routes/api.php` exists.

- [ ] **Step 4: Verify the app boots**

```bash
docker compose up -d nginx
curl http://localhost/api/up
```
Expected: `{"status":"up",...}` with HTTP 200.

- [ ] **Step 5: Commit**

```bash
git add backend/
git commit -m "feat: scaffold Laravel 11 with Sanctum token auth"
```

---

### Task 3: Database Migration, Project Model, and Factory

**Files:**
- Create: `backend/database/migrations/TIMESTAMP_create_projects_table.php`
- Create: `backend/app/Models/Project.php`
- Create: `backend/database/factories/ProjectFactory.php`
- Modify: `backend/app/Models/User.php` — add `projects()` HasMany

**Interfaces:**
- Produces: `Project` Eloquent model with `$fillable`, `$casts`, and `user()` BelongsTo; `User::projects()` HasMany; `Project::factory()` for tests

- [ ] **Step 1: Create the migration**

```bash
docker compose exec php php artisan make:migration create_projects_table
```

Open the generated file in `backend/database/migrations/` and replace its `up()` method:

```php
public function up(): void
{
    Schema::create('projects', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('client_name');
        $table->string('project_name');
        $table->text('description')->nullable();
        $table->enum('status', ['Planning', 'In Progress', 'On Hold', 'Completed']);
        $table->enum('priority', ['Low', 'Medium', 'High']);
        $table->date('start_date')->nullable();
        $table->date('due_date')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('projects');
}
```

- [ ] **Step 2: Write `backend/app/Models/Project.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_name',
        'project_name',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Add `projects()` to `backend/app/Models/User.php`**

Add this import at the top of User.php:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Add this method to the User class body:
```php
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}
```

- [ ] **Step 4: Write `backend/database/factories/ProjectFactory.php`**

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');

        return [
            'client_name' => $this->faker->company(),
            'project_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['Planning', 'In Progress', 'On Hold', 'Completed']),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High']),
            'start_date' => $startDate->format('Y-m-d'),
            'due_date' => $this->faker->dateTimeBetween($startDate, '+3 months')->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 5: Run migration**

```bash
docker compose exec php php artisan migrate
```
Expected: `projects` table created, migration table shows it ran.

- [ ] **Step 6: Smoke test in Tinker**

```bash
docker compose exec php php artisan tinker
```
```php
>>> App\Models\Project::count()
=> 0
>>> exit
```

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/ backend/app/Models/ backend/database/factories/
git commit -m "feat: add projects migration, model, and factory"
```

---

### Task 4: Auth API + Tests (TDD)

**Files:**
- Create: `backend/tests/Feature/AuthTest.php`
- Create: `backend/app/Http/Controllers/API/AuthController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `User` model, `HasApiTokens` (from Sanctum install)
- Produces: `POST /api/auth/register` → `{token, user}` 201; `POST /api/auth/login` → `{token, user}` 200; `POST /api/auth/logout` → 200

- [ ] **Step 1: Write `backend/tests/Feature/AuthTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertOk()->assertJson(['message' => 'Logged out successfully']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run the tests — verify they ALL fail**

```bash
docker compose exec php php artisan test --filter=AuthTest
```
Expected: all 6 tests fail with route-not-found or class-not-found errors.

- [ ] **Step 3: Create `backend/app/Http/Controllers/API/AuthController.php`**

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'token' => $user->createToken('auth-token')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('auth-token')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
```

- [ ] **Step 4: Update `backend/routes/api.php`**

Replace the file contents entirely:

```php
<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->apiResource('projects', ProjectController::class);
```

- [ ] **Step 5: Run the tests — verify they ALL pass**

```bash
docker compose exec php php artisan test --filter=AuthTest
```
Expected: 6 tests pass.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/API/AuthController.php backend/routes/api.php backend/tests/Feature/AuthTest.php
git commit -m "feat: add auth API (register/login/logout) with Sanctum tokens"
```

---

### Task 5: Project CRUD + Validation + Tests (TDD)

**Files:**
- Create: `backend/tests/Feature/ProjectCrudTest.php`
- Create: `backend/tests/Feature/ProjectValidationTest.php`
- Create: `backend/app/Http/Requests/StoreProjectRequest.php`
- Create: `backend/app/Http/Requests/UpdateProjectRequest.php`
- Create: `backend/app/Http/Resources/ProjectResource.php`
- Create: `backend/app/Http/Controllers/API/ProjectController.php`

**Interfaces:**
- Consumes: `routes/api.php` `apiResource('projects', ...)` from Task 4; `Project` model and `ProjectFactory` from Task 3
- Produces: all 5 CRUD endpoints returning `ProjectResource` JSON; 422 on validation failure

- [ ] **Step 1: Write `backend/tests/Feature/ProjectCrudTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Acme Corp',
            'project_name' => 'Website Redesign',
            'description' => 'A redesign project',
            'status' => 'Planning',
            'priority' => 'High',
            'start_date' => '2026-06-01',
            'due_date' => '2026-07-01',
        ], $overrides);
    }

    public function test_can_list_own_projects(): void
    {
        Project::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_see_other_users_projects(): void
    {
        $other = User::factory()->create();
        Project::factory()->count(2)->create(['user_id' => $other->id]);

        $this->withToken($this->token)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_can_create_project(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', $this->projectPayload())
            ->assertStatus(201)
            ->assertJsonFragment(['clientName' => 'Acme Corp'])
            ->assertJsonFragment(['projectName' => 'Website Redesign'])
            ->assertJsonFragment(['status' => 'Planning'])
            ->assertJsonFragment(['priority' => 'High']);

        $this->assertDatabaseHas('projects', [
            'client_name' => 'Acme Corp',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_show_own_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $project->id]);
    }

    public function test_cannot_show_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)->getJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_can_update_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->putJson("/api/projects/{$project->id}", $this->projectPayload(['status' => 'In Progress']))
            ->assertOk()
            ->assertJsonFragment(['status' => 'In Progress']);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'In Progress']);
    }

    public function test_cannot_update_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)
            ->putJson("/api/projects/{$project->id}", $this->projectPayload())
            ->assertNotFound();
    }

    public function test_can_delete_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->deleteJson("/api/projects/{$project->id}")
            ->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_cannot_delete_other_users_project(): void
    {
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)->deleteJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Write `backend/tests/Feature/ProjectValidationTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->token = $user->createToken('test')->plainTextToken;
    }

    public function test_client_name_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', ['project_name' => 'Test', 'status' => 'Planning', 'priority' => 'Low'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_name']);
    }

    public function test_project_name_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', ['client_name' => 'Acme', 'status' => 'Planning', 'priority' => 'Low'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_name']);
    }

    public function test_status_must_be_a_valid_value(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Cancelled',
                'priority' => 'Low',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_priority_must_be_a_valid_value(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Critical',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_due_date_cannot_be_before_start_date(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Low',
                'start_date' => '2026-07-01',
                'due_date' => '2026-06-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_due_date_equal_to_start_date_is_valid(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/projects', [
                'client_name' => 'Acme',
                'project_name' => 'Test',
                'status' => 'Planning',
                'priority' => 'Low',
                'start_date' => '2026-07-01',
                'due_date' => '2026-07-01',
            ])
            ->assertStatus(201);
    }
}
```

- [ ] **Step 3: Run tests — verify they ALL fail**

```bash
docker compose exec php php artisan test --filter="ProjectCrudTest|ProjectValidationTest"
```
Expected: all tests fail (controller class not found).

- [ ] **Step 4: Write `backend/app/Http/Requests/StoreProjectRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name'  => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'required|in:Planning,In Progress,On Hold,Completed',
            'priority'     => 'required|in:Low,Medium,High',
            'start_date'   => 'nullable|date',
            'due_date'     => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
```

- [ ] **Step 5: Write `backend/app/Http/Requests/UpdateProjectRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name'  => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'required|in:Planning,In Progress,On Hold,Completed',
            'priority'     => 'required|in:Low,Medium,High',
            'start_date'   => 'nullable|date',
            'due_date'     => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
```

- [ ] **Step 6: Write `backend/app/Http/Resources/ProjectResource.php`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'clientName'  => $this->client_name,
            'projectName' => $this->project_name,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'startDate'   => $this->start_date?->format('Y-m-d'),
            'dueDate'     => $this->due_date?->format('Y-m-d'),
            'createdAt'   => $this->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 7: Write `backend/app/Http/Controllers/API/ProjectController.php`**

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->projects();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('project_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $allowed = ['client_name', 'project_name', 'status', 'priority', 'due_date', 'start_date', 'created_at'];
        $sortBy  = in_array($request->query('sort_by'), $allowed) ? $request->query('sort_by') : 'created_at';
        $sortDir = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';

        return ProjectResource::collection($query->orderBy($sortBy, $sortDir)->get());
    }

    public function show(Request $request, int $id)
    {
        return new ProjectResource($request->user()->projects()->findOrFail($id));
    }

    public function store(StoreProjectRequest $request)
    {
        $project = $request->user()->projects()->create($request->validated());
        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, int $id)
    {
        $project = $request->user()->projects()->findOrFail($id);
        $project->update($request->validated());
        return new ProjectResource($project);
    }

    public function destroy(Request $request, int $id)
    {
        $request->user()->projects()->findOrFail($id)->delete();
        return response()->json(['message' => 'Project deleted successfully']);
    }
}
```

- [ ] **Step 8: Run tests — verify they ALL pass**

```bash
docker compose exec php php artisan test --filter="ProjectCrudTest|ProjectValidationTest"
```
Expected: all 15 tests pass.

- [ ] **Step 9: Commit**

```bash
git add backend/app/Http/Controllers/API/ProjectController.php \
        backend/app/Http/Requests/ \
        backend/app/Http/Resources/ \
        backend/tests/Feature/ProjectCrudTest.php \
        backend/tests/Feature/ProjectValidationTest.php
git commit -m "feat: add project CRUD API with validation and user scoping"
```

---

### Task 6: Search, Filter, and Sort + Tests (TDD)

**Files:**
- Create: `backend/tests/Feature/ProjectFilterTest.php`
- (No new implementation files — logic already in `ProjectController@index`)

**Interfaces:**
- Consumes: `GET /api/projects` query params: `search`, `status`, `priority`, `sort_by`, `sort_dir`
- Produces: filtered + sorted `data` array in response

- [ ] **Step 1: Write `backend/tests/Feature/ProjectFilterTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function make(array $attrs): void
    {
        Project::factory()->create(array_merge(['user_id' => $this->user->id], $attrs));
    }

    public function test_search_matches_client_name(): void
    {
        $this->make(['client_name' => 'Acme Corp', 'project_name' => 'Alpha', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Globex', 'project_name' => 'Beta', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['clientName' => 'Acme Corp']);
    }

    public function test_search_matches_project_name(): void
    {
        $this->make(['client_name' => 'Corp A', 'project_name' => 'Portal Launch', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Corp B', 'project_name' => 'App Redesign', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Portal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['projectName' => 'Portal Launch']);
    }

    public function test_filter_by_status(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'B', 'project_name' => 'P2', 'status' => 'In Progress', 'priority' => 'Low']);
        $this->make(['client_name' => 'C', 'project_name' => 'P3', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?status=Planning')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_priority(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'High']);
        $this->make(['client_name' => 'B', 'project_name' => 'P2', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?priority=High')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['priority' => 'High']);
    }

    public function test_sort_by_client_name_ascending(): void
    {
        $this->make(['client_name' => 'Zebra Co', 'project_name' => 'Z', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Acme Corp', 'project_name' => 'A', 'status' => 'Planning', 'priority' => 'Low']);

        $data = $this->withToken($this->token)
            ->getJson('/api/projects?sort_by=client_name&sort_dir=asc')
            ->assertOk()
            ->json('data');

        $this->assertEquals('Acme Corp', $data[0]['clientName']);
        $this->assertEquals('Zebra Co', $data[1]['clientName']);
    }

    public function test_invalid_sort_by_falls_back_to_created_at(): void
    {
        $this->make(['client_name' => 'A', 'project_name' => 'P', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?sort_by=evil_column; DROP TABLE projects--')
            ->assertOk();

        $this->assertDatabaseHas('projects', ['client_name' => 'A']);
    }

    public function test_combined_search_and_status_filter(): void
    {
        $this->make(['client_name' => 'Acme', 'project_name' => 'P1', 'status' => 'Planning', 'priority' => 'Low']);
        $this->make(['client_name' => 'Acme', 'project_name' => 'P2', 'status' => 'In Progress', 'priority' => 'Low']);
        $this->make(['client_name' => 'Other', 'project_name' => 'P3', 'status' => 'Planning', 'priority' => 'Low']);

        $this->withToken($this->token)->getJson('/api/projects?search=Acme&status=Planning')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Run tests — verify they ALL pass (implementation is already in Task 5)**

```bash
docker compose exec php php artisan test --filter=ProjectFilterTest
```
Expected: all 6 tests pass. (The `ProjectController@index` from Task 5 already implements all filter/sort logic.)

- [ ] **Step 3: Run the full test suite**

```bash
docker compose exec php php artisan test
```
Expected: all tests pass (AuthTest + ProjectCrudTest + ProjectValidationTest + ProjectFilterTest).

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Feature/ProjectFilterTest.php
git commit -m "test: add filter, search, and sort tests for project index"
```

---

### Task 7: Seeders

**Files:**
- Create: `backend/database/seeders/UserSeeder.php`
- Create: `backend/database/seeders/ProjectSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `User` model, `Project` model
- Produces: `demo@example.com` / `password` user with 12 seeded projects from `test_data.json`

- [ ] **Step 1: Write `backend/database/seeders/UserSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => Hash::make('password')]
        );
    }
}
```

- [ ] **Step 2: Write `backend/database/seeders/ProjectSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $projects = [
            ['clientName' => 'Acme Corporation',    'projectName' => 'Corporate Website Redesign',    'description' => "Redesign and modernize the company's corporate website.", 'status' => 'In Progress', 'priority' => 'High',   'startDate' => '2026-06-01', 'dueDate' => '2026-07-15'],
            ['clientName' => 'GreenLeaf Cafe',       'projectName' => 'Online Ordering System',         'description' => 'Develop an online ordering platform for customers.',           'status' => 'Planning',    'priority' => 'Medium', 'startDate' => '2026-06-10', 'dueDate' => '2026-08-01'],
            ['clientName' => 'Bright Realty',        'projectName' => 'Property Listing Portal',        'description' => 'Build a portal for managing property listings.',                'status' => 'On Hold',     'priority' => 'Medium', 'startDate' => '2026-05-15', 'dueDate' => '2026-07-30'],
            ['clientName' => 'Nova Fitness',         'projectName' => 'Mobile App MVP',                 'description' => 'Develop the first version of the fitness tracking app.',       'status' => 'In Progress', 'priority' => 'High',   'startDate' => '2026-06-05', 'dueDate' => '2026-08-20'],
            ['clientName' => 'Blue Ocean Travel',    'projectName' => 'Booking Platform Enhancement',   'description' => 'Improve search and booking functionalities.',                  'status' => 'Completed',   'priority' => 'Medium', 'startDate' => '2026-04-01', 'dueDate' => '2026-05-30'],
            ['clientName' => 'TechVision Solutions', 'projectName' => 'CRM Dashboard',                  'description' => 'Develop an internal CRM dashboard.',                          'status' => 'Planning',    'priority' => 'High',   'startDate' => '2026-06-15', 'dueDate' => '2026-08-15'],
            ['clientName' => 'Urban Living',         'projectName' => 'Property Management System',     'description' => 'Create a platform for managing rental properties.',            'status' => 'In Progress', 'priority' => 'Medium', 'startDate' => '2026-05-20', 'dueDate' => '2026-08-10'],
            ['clientName' => 'Elite Events',         'projectName' => 'Event Registration Portal',      'description' => 'Develop a registration and ticketing portal.',                 'status' => 'Planning',    'priority' => 'Low',    'startDate' => '2026-06-20', 'dueDate' => '2026-09-01'],
            ['clientName' => 'HealthFirst Clinic',   'projectName' => 'Patient Appointment System',     'description' => 'Build an appointment scheduling application.',                 'status' => 'Completed',   'priority' => 'High',   'startDate' => '2026-03-01', 'dueDate' => '2026-05-01'],
            ['clientName' => 'MarketPro',            'projectName' => 'Marketing Campaign Dashboard',   'description' => 'Track and manage digital marketing campaigns.',                'status' => 'In Progress', 'priority' => 'Medium', 'startDate' => '2026-06-01', 'dueDate' => '2026-07-31'],
            ['clientName' => 'Sunrise Education',    'projectName' => 'Learning Management Portal',     'description' => 'Develop a portal for students and instructors.',               'status' => 'Planning',    'priority' => 'High',   'startDate' => '2026-07-01', 'dueDate' => '2026-09-30'],
            ['clientName' => 'FreshFarm',            'projectName' => 'Inventory Management System',    'description' => 'Track inventory across multiple locations.',                   'status' => 'On Hold',     'priority' => 'Low',    'startDate' => '2026-05-01', 'dueDate' => '2026-08-01'],
        ];

        foreach ($projects as $p) {
            Project::create([
                'user_id'      => $user->id,
                'client_name'  => $p['clientName'],
                'project_name' => $p['projectName'],
                'description'  => $p['description'],
                'status'       => $p['status'],
                'priority'     => $p['priority'],
                'start_date'   => $p['startDate'],
                'due_date'     => $p['dueDate'],
            ]);
        }
    }
}
```

- [ ] **Step 3: Update `backend/database/seeders/DatabaseSeeder.php`**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
        ]);
    }
}
```

- [ ] **Step 4: Run the seeder**

```bash
docker compose exec php php artisan migrate:fresh --seed
```
Expected: migration runs cleanly, seeder inserts 1 user + 12 projects.

- [ ] **Step 5: Verify count in Tinker**

```bash
docker compose exec php php artisan tinker --execute="echo App\Models\Project::count();"
```
Expected output: `12`

- [ ] **Step 6: Commit**

```bash
git add backend/database/seeders/
git commit -m "feat: add UserSeeder and ProjectSeeder with test_data.json content"
```

---

### Task 8: Vue 3 Scaffold + Auth Layer

**Files:**
- Create: `frontend/` (via `npm create vite@latest`)
- Create: `frontend/src/style.css`
- Modify: `frontend/vite.config.js`
- Modify: `frontend/src/main.js`
- Modify: `frontend/src/App.vue`
- Create: `frontend/src/api/axios.js`
- Create: `frontend/src/api/auth.js`
- Create: `frontend/src/stores/authStore.js`
- Create: `frontend/src/router/index.js`
- Create: `frontend/src/views/auth/LoginView.vue`
- Create: `frontend/src/views/auth/RegisterView.vue`

**Interfaces:**
- Consumes: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`
- Produces: `http://localhost` shows Login page for unauthenticated users; successful login stores token + redirects to `/`

- [ ] **Step 1: Scaffold Vue project**

From repo root:
```bash
npm create vite@latest frontend -- --template vue
```

- [ ] **Step 2: Install dependencies**

```bash
cd frontend
npm install
npm install vue-router@4 pinia axios vue-toastification vue-draggable-plus
npm install -D @tailwindcss/vite tailwindcss
cd ..
```

- [ ] **Step 3: Write `frontend/vite.config.js`**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': resolve(__dirname, 'src') },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    hmr: { host: 'localhost' },
  },
})
```

- [ ] **Step 4: Replace `frontend/src/style.css`**

```css
@import "tailwindcss";
```

Delete `frontend/src/assets/vue.svg` and `frontend/public/vite.svg` (they're scaffold clutter).

- [ ] **Step 5: Write `frontend/src/api/axios.js`**

```js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

Note: uses `window.location.href` instead of `router.push` to avoid a circular import between `axios.js` and `router/index.js`.

- [ ] **Step 6: Write `frontend/src/api/auth.js`**

```js
import api from './axios'

export const authApi = {
  register: (data) => api.post('/auth/register', data),
  login: (data) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
}
```

- [ ] **Step 7: Write `frontend/src/stores/authStore.js`**

```js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token'))
  const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'))
  const isAuthenticated = computed(() => !!token.value)

  async function login(credentials) {
    const { data } = await authApi.login(credentials)
    _persist(data.token, data.user)
  }

  async function register(credentials) {
    const { data } = await authApi.register(credentials)
    _persist(data.token, data.user)
  }

  async function logout() {
    try { await authApi.logout() } catch {}
    _clear()
  }

  function _persist(t, u) {
    token.value = t
    user.value = u
    localStorage.setItem('auth_token', t)
    localStorage.setItem('auth_user', JSON.stringify(u))
  }

  function _clear() {
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { token, user, isAuthenticated, login, register, logout }
})
```

- [ ] **Step 8: Write `frontend/src/router/index.js`**

```js
import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/auth/LoginView.vue'
import RegisterView from '@/views/auth/RegisterView.vue'
import ProjectsView from '@/views/projects/ProjectsView.vue'

const routes = [
  { path: '/login', component: LoginView },
  { path: '/register', component: RegisterView },
  { path: '/', component: ProjectsView, meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  const token = localStorage.getItem('auth_token')
  if (to.meta.requiresAuth && !token) return '/login'
  if ((to.path === '/login' || to.path === '/register') && token) return '/'
})

export default router
```

- [ ] **Step 9: Write `frontend/src/main.js`**

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import './style.css'
import App from './App.vue'
import router from './router'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(Toast, { timeout: 3000, position: 'top-right' })
app.mount('#app')
```

- [ ] **Step 10: Write `frontend/src/views/auth/LoginView.vue`**

```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 w-full max-w-md">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Sign In</h1>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-400': errors.email }"
          />
          <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            v-model="form.password"
            type="password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium text-sm hover:bg-blue-700 disabled:opacity-50"
        >
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>

      <p class="text-sm text-center text-gray-500 mt-4">
        No account?
        <router-link to="/register" class="text-blue-600 hover:underline">Register</router-link>
      </p>

      <p class="text-xs text-center text-gray-400 mt-2">
        Demo: demo@example.com / password
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const loading = ref(false)

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    await authStore.login(form)
    router.push('/')
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 11: Write `frontend/src/views/auth/RegisterView.vue`**

```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 w-full max-w-md">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Create Account</h1>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" type="text"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-400': errors.name }" />
          <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-400': errors.email }" />
          <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input v-model="form.password" type="password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-400': errors.password }" />
          <p v-if="errors.password" class="text-red-500 text-xs mt-1">{{ errors.password[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
          <input v-model="form.password_confirmation" type="password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <button type="submit" :disabled="loading"
          class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium text-sm hover:bg-blue-700 disabled:opacity-50">
          {{ loading ? 'Creating account...' : 'Create Account' }}
        </button>
      </form>

      <p class="text-sm text-center text-gray-500 mt-4">
        Have an account?
        <router-link to="/login" class="text-blue-600 hover:underline">Sign In</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref({})
const loading = ref(false)

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    await authStore.register(form)
    router.push('/')
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 12: Write a temporary `frontend/src/views/projects/ProjectsView.vue`** (stub — replaced in Task 11)

```vue
<template>
  <div class="p-8 text-gray-500">Projects view coming soon</div>
</template>
```

- [ ] **Step 13: Write `frontend/src/App.vue`**

```vue
<template>
  <router-view />
</template>
```

(The nav and modal will be added in Task 11 after the full project UI is ready.)

- [ ] **Step 14: Start Docker and verify login works**

```bash
docker compose up -d
```

Open `http://localhost` — should redirect to `/login`. Log in with `demo@example.com` / `password`. Should redirect to `/` and show "Projects view coming soon".

- [ ] **Step 15: Commit**

```bash
git add frontend/
git commit -m "feat: scaffold Vue 3 SPA with auth (login/register/logout) and routing"
```

---

### Task 9: Project Store and API Module

**Files:**
- Create: `frontend/src/api/projects.js`
- Create: `frontend/src/stores/projectStore.js`

**Interfaces:**
- Consumes: `api/axios.js`; all 5 project endpoints
- Produces: `useProjectStore()` with `projects`, `filters`, `viewMode`, `loading`, `fetchProjects()`, `createProject()`, `updateProject()`, `deleteProject()`, `updateProjectStatus()`

- [ ] **Step 1: Write `frontend/src/api/projects.js`**

```js
import api from './axios'

export const projectsApi = {
  index: (params = {}) => api.get('/projects', { params }),
  show: (id) => api.get(`/projects/${id}`),
  store: (data) => api.post('/projects', data),
  update: (id, data) => api.put(`/projects/${id}`, data),
  destroy: (id) => api.delete(`/projects/${id}`),
}
```

- [ ] **Step 2: Write `frontend/src/stores/projectStore.js`**

```js
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { projectsApi } from '@/api/projects'

export const useProjectStore = defineStore('projects', () => {
  const projects = ref([])
  const loading = ref(false)
  const viewMode = ref(localStorage.getItem('view_mode') || 'board')

  const filters = ref({
    search: '',
    status: '',
    priority: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
  })

  function setViewMode(mode) {
    viewMode.value = mode
    localStorage.setItem('view_mode', mode)
  }

  async function fetchProjects() {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters.value).filter(([, v]) => v !== '')
      )
      const { data } = await projectsApi.index(params)
      projects.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function createProject(payload) {
    const { data } = await projectsApi.store(payload)
    projects.value.unshift(data.data)
    return data.data
  }

  async function updateProject(id, payload) {
    const { data } = await projectsApi.update(id, payload)
    const idx = projects.value.findIndex((p) => p.id === id)
    if (idx !== -1) projects.value[idx] = data.data
    return data.data
  }

  async function deleteProject(id) {
    await projectsApi.destroy(id)
    projects.value = projects.value.filter((p) => p.id !== id)
  }

  async function updateProjectStatus(id, status) {
    const project = projects.value.find((p) => p.id === id)
    if (!project) return
    const prev = project.status
    project.status = status
    try {
      await projectsApi.update(id, {
        client_name: project.clientName,
        project_name: project.projectName,
        description: project.description,
        status,
        priority: project.priority,
        start_date: project.startDate,
        due_date: project.dueDate,
      })
    } catch {
      project.status = prev
      throw new Error('Status update failed')
    }
  }

  return {
    projects, loading, viewMode, filters,
    setViewMode, fetchProjects, createProject, updateProject, deleteProject, updateProjectStatus,
  }
})
```

- [ ] **Step 3: Commit**

```bash
git add frontend/src/api/projects.js frontend/src/stores/projectStore.js
git commit -m "feat: add project store and API module"
```

---

### Task 10: Project Form Modal

**Files:**
- Create: `frontend/src/components/ProjectFormModal.vue`

**Interfaces:**
- Consumes: `useProjectStore().createProject()`, `useProjectStore().updateProject()`
- Produces: emits `close` and `saved` events; `project` prop (null = create mode, object = edit mode)

- [ ] **Step 1: Write `frontend/src/components/ProjectFormModal.vue`**

```vue
<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">
          {{ project ? 'Edit Project' : 'New Project' }}
        </h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Client Name <span class="text-red-400">*</span></label>
            <input v-model="form.client_name" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.client_name ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.client_name" class="text-red-500 text-xs mt-1">{{ errors.client_name[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-400">*</span></label>
            <input v-model="form.project_name" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.project_name ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.project_name" class="text-red-500 text-xs mt-1">{{ errors.project_name[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea v-model="form.description" rows="3"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-400">*</span></label>
            <select v-model="form.status"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.status ? 'border-red-400' : 'border-gray-300'">
              <option value="">Select status</option>
              <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
            <p v-if="errors.status" class="text-red-500 text-xs mt-1">{{ errors.status[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Priority <span class="text-red-400">*</span></label>
            <select v-model="form.priority"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.priority ? 'border-red-400' : 'border-gray-300'">
              <option value="">Select priority</option>
              <option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option>
            </select>
            <p v-if="errors.priority" class="text-red-500 text-xs mt-1">{{ errors.priority[0] }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input v-model="form.start_date" type="date"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
            <input v-model="form.due_date" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.due_date ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="errors.due_date" class="text-red-500 text-xs mt-1">{{ errors.due_date[0] }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
          </button>
          <button type="submit" :disabled="loading"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'Saving...' : 'Save Project' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const props = defineProps({ project: { type: Object, default: null } })
const emit = defineEmits(['close', 'saved'])

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const PRIORITIES = ['Low', 'Medium', 'High']

const store = useProjectStore()
const toast = useToast()
const errors = ref({})
const loading = ref(false)

const form = reactive({
  client_name: '',
  project_name: '',
  description: '',
  status: '',
  priority: '',
  start_date: '',
  due_date: '',
})

watch(() => props.project, (p) => {
  if (p) {
    form.client_name  = p.clientName  ?? ''
    form.project_name = p.projectName ?? ''
    form.description  = p.description ?? ''
    form.status       = p.status      ?? ''
    form.priority     = p.priority    ?? ''
    form.start_date   = p.startDate   ?? ''
    form.due_date     = p.dueDate     ?? ''
  }
}, { immediate: true })

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    if (props.project) {
      await store.updateProject(props.project.id, form)
      toast.success('Project updated')
    } else {
      await store.createProject(form)
      toast.success('Project created')
    }
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors
    } else {
      toast.error('Something went wrong')
    }
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/components/ProjectFormModal.vue
git commit -m "feat: add shared project create/edit modal with 422 error handling"
```

---

### Task 11: Kanban Board View

**Files:**
- Create: `frontend/src/components/ProjectCard.vue`
- Create: `frontend/src/components/KanbanColumn.vue`
- Create: `frontend/src/views/projects/ProjectBoardView.vue`
- Replace: `frontend/src/views/projects/ProjectsView.vue`
- Replace: `frontend/src/App.vue`

**Interfaces:**
- Consumes: `useProjectStore()` — `projects`, `fetchProjects()`, `updateProjectStatus()`, `deleteProject()`; `ProjectFormModal`; `provide('openEditModal')`
- Produces: Kanban board with 4 columns; drag-and-drop updates status via API; edit/delete per card

- [ ] **Step 1: Write `frontend/src/components/ProjectCard.vue`**

```vue
<template>
  <div
    :data-id="project.id"
    class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm cursor-grab active:cursor-grabbing select-none group"
  >
    <div class="flex items-start justify-between gap-2 mb-2">
      <p class="text-sm font-medium text-gray-800 leading-snug">{{ project.projectName }}</p>
      <span :class="priorityClass" class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0">
        {{ project.priority }}
      </span>
    </div>
    <p class="text-xs text-gray-500 mb-2">{{ project.clientName }}</p>
    <p v-if="project.dueDate" class="text-xs text-gray-400">Due {{ project.dueDate }}</p>

    <div class="flex gap-2 mt-3 opacity-0 group-hover:opacity-100 transition-opacity">
      <button @click.stop="openEditModal(project)"
        class="text-xs text-blue-600 hover:underline">Edit</button>
      <button @click.stop="handleDelete"
        class="text-xs text-red-500 hover:underline">Delete</button>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const props = defineProps({ project: { type: Object, required: true } })

const store = useProjectStore()
const toast = useToast()
const openEditModal = inject('openEditModal')

const priorityClass = computed(() => ({
  'bg-red-100 text-red-700':    props.project.priority === 'High',
  'bg-yellow-100 text-yellow-700': props.project.priority === 'Medium',
  'bg-green-100 text-green-700':  props.project.priority === 'Low',
}))

async function handleDelete() {
  if (!confirm(`Delete "${props.project.projectName}"?`)) return
  try {
    await store.deleteProject(props.project.id)
    toast.success('Project deleted')
  } catch {
    toast.error('Failed to delete project')
  }
}
</script>
```

- [ ] **Step 2: Write `frontend/src/components/KanbanColumn.vue`**

```vue
<template>
  <div class="flex flex-col bg-gray-100 rounded-xl p-3 min-h-64 w-64 shrink-0">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ status }}</h3>
      <span class="text-xs text-gray-400 font-medium">{{ items.length }}</span>
    </div>

    <VueDraggable
      v-model="items"
      group="projects"
      item-key="id"
      class="flex flex-col gap-2 flex-1"
      @add="onAdd"
    >
      <template #item="{ element }">
        <ProjectCard :project="element" />
      </template>
    </VueDraggable>

    <div v-if="items.length === 0" class="text-xs text-gray-400 text-center py-8">
      No projects
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'
import ProjectCard from './ProjectCard.vue'

const props = defineProps({
  status: { type: String, required: true },
  projects: { type: Array, required: true },
})

const store = useProjectStore()
const toast = useToast()

const items = computed({
  get: () => props.projects,
  set: () => {},
})

async function onAdd(event) {
  const id = Number(event.item.dataset.id)
  try {
    await store.updateProjectStatus(id, props.status)
  } catch {
    toast.error('Failed to update status')
    await store.fetchProjects()
  }
}
</script>
```

- [ ] **Step 3: Write `frontend/src/views/projects/ProjectBoardView.vue`**

```vue
<template>
  <div class="flex gap-4 p-6 overflow-x-auto min-h-screen">
    <KanbanColumn
      v-for="status in STATUSES"
      :key="status"
      :status="status"
      :projects="byStatus[status]"
    />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import KanbanColumn from '@/components/KanbanColumn.vue'

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const store = useProjectStore()

const byStatus = computed(() => {
  const map = {}
  STATUSES.forEach((s) => { map[s] = [] })
  store.projects.forEach((p) => { if (map[p.status]) map[p.status].push(p) })
  return map
})

onMounted(() => store.fetchProjects())
</script>
```

- [ ] **Step 4: Replace `frontend/src/views/projects/ProjectsView.vue` and create a stub `ProjectTableView.vue`**

Create `frontend/src/views/projects/ProjectTableView.vue` as a stub (replaced for real in Task 12):

```vue
<template>
  <div class="p-8 text-gray-400">Table view coming soon</div>
</template>
```

Then replace `frontend/src/views/projects/ProjectsView.vue`:

```vue
<template>
  <ProjectBoardView v-if="store.viewMode === 'board'" />
  <ProjectTableView v-else />
</template>

<script setup>
import { useProjectStore } from '@/stores/projectStore'
import ProjectBoardView from './ProjectBoardView.vue'
import ProjectTableView from './ProjectTableView.vue'

const store = useProjectStore()
</script>
```

- [ ] **Step 5: Replace `frontend/src/App.vue`**

```vue
<template>
  <div class="min-h-screen bg-gray-50">
    <nav v-if="authStore.isAuthenticated"
      class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between sticky top-0 z-40">
      <h1 class="text-lg font-semibold text-gray-800">Client Project Tracker</h1>
      <div class="flex items-center gap-3">
        <div class="flex rounded-lg overflow-hidden border border-gray-300 text-sm">
          <button @click="setView('board')"
            :class="['px-3 py-1.5 font-medium', store.viewMode === 'board' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">
            Board
          </button>
          <button @click="setView('table')"
            :class="['px-3 py-1.5 font-medium', store.viewMode === 'table' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">
            Table
          </button>
        </div>
        <button @click="openCreateModal"
          class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-blue-700">
          + New Project
        </button>
        <span class="text-sm text-gray-500">{{ authStore.user?.name }}</span>
        <button @click="handleLogout" class="text-sm text-gray-400 hover:text-gray-600">Logout</button>
      </div>
    </nav>

    <router-view />

    <ProjectFormModal
      v-if="showModal"
      :project="editingProject"
      @close="closeModal"
      @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { ref, provide } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'
import ProjectFormModal from '@/components/ProjectFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const store = useProjectStore()
const toast = useToast()

const showModal = ref(false)
const editingProject = ref(null)

function openCreateModal() { editingProject.value = null; showModal.value = true }
function openEditModal(project) { editingProject.value = project; showModal.value = true }
function closeModal() { showModal.value = false; editingProject.value = null }
function onSaved() { closeModal(); store.fetchProjects() }

provide('openEditModal', openEditModal)

function setView(mode) { store.setViewMode(mode) }

async function handleLogout() {
  await authStore.logout()
  toast.success('Logged out')
  router.push('/login')
}
</script>
```

- [ ] **Step 6: Test in browser**

Open `http://localhost`. Log in with `demo@example.com` / `password`. Verify:
- 4 Kanban columns appear with the 12 seeded projects distributed correctly
- Dragging a card between columns updates its status (check Network tab — PUT fires)
- "Edit" on hover opens the modal pre-filled
- "Delete" removes the card after confirm
- "+ New Project" opens an empty modal; saving adds a card to the correct column

- [ ] **Step 7: Commit**

```bash
git add frontend/src/components/ProjectCard.vue \
        frontend/src/components/KanbanColumn.vue \
        frontend/src/views/projects/ProjectBoardView.vue \
        frontend/src/views/projects/ProjectsView.vue \
        frontend/src/App.vue
git commit -m "feat: add Kanban board with drag-and-drop status updates"
```

---

### Task 12: Table View + Filters

**Files:**
- Create: `frontend/src/components/ProjectFilters.vue`
- Create: `frontend/src/components/ProjectTable.vue`
- Create: `frontend/src/views/projects/ProjectTableView.vue`

**Interfaces:**
- Consumes: `useProjectStore()` — `projects`, `filters`, `fetchProjects()`, `deleteProject()`; `provide('openEditModal')`
- Produces: sortable table with search/filter toolbar, view accessible via the Board/Table toggle

- [ ] **Step 1: Write `frontend/src/components/ProjectFilters.vue`**

```vue
<template>
  <div class="flex flex-wrap gap-3 p-4 bg-white border-b border-gray-100">
    <input
      v-model="store.filters.search"
      @input="onFilterChange"
      type="text"
      placeholder="Search projects..."
      class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-blue-500"
    />

    <select v-model="store.filters.status" @change="onFilterChange"
      class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      <option value="">All Statuses</option>
      <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
    </select>

    <select v-model="store.filters.priority" @change="onFilterChange"
      class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      <option value="">All Priorities</option>
      <option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option>
    </select>

    <button v-if="hasActiveFilters" @click="clearFilters"
      class="text-sm text-gray-500 hover:text-gray-700 underline">
      Clear filters
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useProjectStore } from '@/stores/projectStore'

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const PRIORITIES = ['Low', 'Medium', 'High']

const store = useProjectStore()

const hasActiveFilters = computed(() =>
  store.filters.search || store.filters.status || store.filters.priority
)

function onFilterChange() { store.fetchProjects() }

function clearFilters() {
  store.filters.search = ''
  store.filters.status = ''
  store.filters.priority = ''
  store.fetchProjects()
}
</script>
```

- [ ] **Step 2: Write `frontend/src/components/ProjectTable.vue`**

```vue
<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 bg-gray-50">
          <th v-for="col in COLUMNS" :key="col.key"
            @click="col.sortable ? toggleSort(col.key) : null"
            :class="['px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider', col.sortable ? 'cursor-pointer hover:text-gray-800 select-none' : '']">
            {{ col.label }}
            <span v-if="col.sortable && store.filters.sort_by === col.key">
              {{ store.filters.sort_dir === 'asc' ? ' ↑' : ' ↓' }}
            </span>
          </th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="store.loading">
          <td :colspan="COLUMNS.length + 1" class="px-4 py-8 text-center text-gray-400">Loading...</td>
        </tr>
        <tr v-else-if="store.projects.length === 0">
          <td :colspan="COLUMNS.length + 1" class="px-4 py-8 text-center text-gray-400">No projects found</td>
        </tr>
        <tr v-else v-for="project in store.projects" :key="project.id"
          class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 font-medium text-gray-800">{{ project.clientName }}</td>
          <td class="px-4 py-3 text-gray-700">{{ project.projectName }}</td>
          <td class="px-4 py-3">
            <span :class="statusClass(project.status)" class="text-xs font-medium px-2 py-0.5 rounded-full">
              {{ project.status }}
            </span>
          </td>
          <td class="px-4 py-3">
            <span :class="priorityClass(project.priority)" class="text-xs font-medium px-2 py-0.5 rounded-full">
              {{ project.priority }}
            </span>
          </td>
          <td class="px-4 py-3 text-gray-500">{{ project.startDate ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-500">{{ project.dueDate ?? '—' }}</td>
          <td class="px-4 py-3 text-right">
            <button @click="openEditModal(project)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
            <button @click="handleDelete(project)" class="text-red-500 hover:underline text-xs">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const COLUMNS = [
  { key: 'client_name',  label: 'Client',       sortable: true },
  { key: 'project_name', label: 'Project',      sortable: true },
  { key: 'status',       label: 'Status',       sortable: true },
  { key: 'priority',     label: 'Priority',     sortable: true },
  { key: 'start_date',   label: 'Start Date',   sortable: true },
  { key: 'due_date',     label: 'Due Date',     sortable: true },
]

const store = useProjectStore()
const toast = useToast()
const openEditModal = inject('openEditModal')

function toggleSort(key) {
  if (store.filters.sort_by === key) {
    store.filters.sort_dir = store.filters.sort_dir === 'asc' ? 'desc' : 'asc'
  } else {
    store.filters.sort_by = key
    store.filters.sort_dir = 'asc'
  }
  store.fetchProjects()
}

function statusClass(s) {
  return {
    'Planning':    'bg-gray-100 text-gray-700',
    'In Progress': 'bg-blue-100 text-blue-700',
    'On Hold':     'bg-orange-100 text-orange-700',
    'Completed':   'bg-green-100 text-green-700',
  }[s] ?? 'bg-gray-100 text-gray-700'
}

function priorityClass(p) {
  return {
    'High':   'bg-red-100 text-red-700',
    'Medium': 'bg-yellow-100 text-yellow-700',
    'Low':    'bg-green-100 text-green-700',
  }[p] ?? 'bg-gray-100 text-gray-700'
}

async function handleDelete(project) {
  if (!confirm(`Delete "${project.projectName}"?`)) return
  try {
    await store.deleteProject(project.id)
    toast.success('Project deleted')
  } catch {
    toast.error('Failed to delete project')
  }
}
</script>
```

- [ ] **Step 3: Write `frontend/src/views/projects/ProjectTableView.vue`**

```vue
<template>
  <div class="flex flex-col">
    <ProjectFilters />
    <ProjectTable />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import ProjectFilters from '@/components/ProjectFilters.vue'
import ProjectTable from '@/components/ProjectTable.vue'

const store = useProjectStore()
onMounted(() => store.fetchProjects())
</script>
```

- [ ] **Step 4: Test in browser**

Click the "Table" toggle button. Verify:
- All 12 projects appear in the table
- Search input filters results server-side (network request fires on each keystroke)
- Status and Priority dropdowns filter correctly
- Column headers sort ascending/descending with arrow indicator
- Edit and Delete work from the table

- [ ] **Step 5: Commit**

```bash
git add frontend/src/components/ProjectFilters.vue \
        frontend/src/components/ProjectTable.vue \
        frontend/src/views/projects/ProjectTableView.vue
git commit -m "feat: add table view with search, filter, and sortable columns"
```

---

### Task 13: README and Technical Reflection

**Files:**
- Create: `README.md`
- Create: `TECHNICAL_REFLECTION.md`

**Interfaces:**
- Produces: submission-ready documentation covering all SUBMISSION.md requirements

- [ ] **Step 1: Write `README.md`**

```markdown
# Client Project Tracker

A fullstack Client Project Tracker built for the Koda Fullstack Developer Assessment.

## Technology Choices

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend | Laravel 11 (PHP 8.3) | Opinionated structure, Eloquent ORM, built-in API resources and form request validation |
| Frontend | Vue 3 + Vite | Composition API, Pinia state management, fast HMR |
| Database | MySQL 8 | Relational data, enum columns for status/priority, familiar to most teams |
| Auth | Laravel Sanctum | Lightweight API token auth, no session/cookie complexity for SPA |
| Styling | Tailwind CSS v4 | Utility-first, no component library dependency |
| Infrastructure | Docker (nginx + php-fpm + vite + mysql) | One-command setup, mirrors production deployment patterns |

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose v2)
- No local PHP, Node, or MySQL required

## Setup and Running

```bash
# Clone the repository
git clone <repo-url>
cd koda-task-management-app

# Start all containers
docker compose up --build -d

# Generate Laravel app key
docker compose exec php php artisan key:generate

# Run migrations and seed the database
docker compose exec php php artisan migrate:fresh --seed
```

Open **http://localhost** in your browser.

**Demo credentials:** `demo@example.com` / `password`

The seeder pre-loads 12 sample projects from the assessment's `test_data.json`.

## Running Tests

```bash
docker compose exec php php artisan test
```

## Project URLs

| URL | Description |
|-----|-------------|
| http://localhost | Vue SPA (board view by default) |
| http://localhost/api/up | Laravel health check |
| http://localhost/api/auth/register | Register endpoint |
| http://localhost/api/projects | Projects API (requires Bearer token) |

## Assumptions

- Projects are user-scoped: each authenticated user sees only their own projects
- Authentication is required for all project operations (no public read access)
- `start_date` and `due_date` are optional; if both are provided, `due_date` must be ≥ `start_date`
- The Kanban board drag-and-drop updates only the `status` field
- Deployment is not included (see Technical Reflection for what I'd add)
```

- [ ] **Step 2: Write `TECHNICAL_REFLECTION.md`**

```markdown
# Technical Reflection

## 1. Why did you choose this implementation approach?

Laravel's opinionated structure enforces clean separation of concerns with minimal boilerplate: Form Requests handle validation, API Resources ensure consistent JSON shape, and Eloquent scopes make user-data isolation straightforward. Vue 3's Composition API paired with Pinia gives a clean reactive layer without the verbosity of the Options API. Docker with nginx + php-fpm mirrors how Laravel runs in production, rather than using `php artisan serve` which is explicitly a dev-only tool.

## 2. What tradeoffs did you make?

- **Separate SPA + API vs. Inertia.js:** The assessment explicitly asks for a REST API, so a decoupled SPA is the right match. Inertia.js would reduce boilerplate but blurs the API surface.
- **Token auth vs. cookie/session:** API tokens are simpler to implement for a pure SPA and avoid CSRF complexity. The tradeoff is that tokens in `localStorage` are accessible to JavaScript (XSS risk), whereas `httpOnly` cookies are not — I'd move to Sanctum cookie auth in a production app.
- **Server-side filtering vs. client-side:** All search/filter/sort is handled by the Laravel API. This is correct for a real app (scales to large datasets) but means a network request on every filter change. A debounce on the search input would mitigate this.
- **Deployment skipped:** Focus was on code quality and architecture; deployment is listed as an optional bonus.

## 3. What would you improve given more time?

- Debounce the search input to reduce API calls
- Pagination for the project list (currently returns all records)
- Move Sanctum to cookie-based auth (`httpOnly`) for better XSS protection
- Add a Dockerfile production build stage (multi-stage: build Vue bundle → copy into nginx image)
- CI/CD pipeline with GitHub Actions (lint → test → build → deploy)
- Project detail page with full description and audit history
- Role-based access (admin can see all users' projects)

## 4. What was the most challenging part?

[Fill in honestly after completing the implementation.]

## 5. AI Tools Used

**Claude Code** (claude.ai/code) was used for:
- Brainstorming and finalizing the tech stack and architecture
- Writing the design spec and implementation plan
- Code generation for boilerplate (migrations, form requests, API resources, Vue components)

All generated code was reviewed and understood before committing. The architecture decisions, tradeoff reasoning, and implementation approach are my own.
```

- [ ] **Step 3: Commit**

```bash
git add README.md TECHNICAL_REFLECTION.md
git commit -m "docs: add README with setup instructions and technical reflection"
```

---

### Task 14: Final Verification

- [ ] **Step 1: Run the full backend test suite**

```bash
docker compose exec php php artisan test
```
Expected: all tests in AuthTest, ProjectCrudTest, ProjectValidationTest, ProjectFilterTest pass.

- [ ] **Step 2: Fresh install simulation**

```bash
docker compose down -v
docker compose up --build -d
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate:fresh --seed
```
Expected: `http://localhost` loads the login page. Log in with `demo@example.com` / `password`. Board shows 12 projects across 4 columns.

- [ ] **Step 3: Smoke test all frontend flows**

- [ ] Login with wrong password → error message appears
- [ ] Register a new user → redirected to board
- [ ] Create a project → card appears in correct column
- [ ] Edit a project → modal pre-filled, changes save
- [ ] Drag a card to a different column → status updates (verify in Network tab)
- [ ] Delete a project → card disappears
- [ ] Switch to Table view → all projects show, sort/filter work
- [ ] Search for "Acme" → only Acme Corporation project shown
- [ ] Filter by Status "Completed" → only 2 completed projects shown
- [ ] Logout → redirected to login, `/` redirects back to login

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete Client Project Tracker submission"
```
