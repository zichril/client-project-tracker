# Client Project Tracker — Design Spec

**Date:** 2026-08-08  
**Stack:** Laravel 11 · PHP 8.3 · Vue 3 + Vite · MySQL 8 · Docker  
**Styling:** Bootstrap 5  
**Assessment:** Koda Fullstack Developer Assessment

---

## 1. Docker Architecture

Four containers on a shared `app-network` bridge network. A single URL (`http://localhost`) serves both the API and SPA during development. The `php` image is `php:8.3-fpm`.

```
docker-compose.yml
├── nginx        → port 80
│   ├── /api/*  → FastCGI proxy to php:9000
│   └── /*      → proxy to vite:5173
├── php          → Laravel app via php-fpm (port 9000, internal only)
├── vite         → Vue 3 + Vite dev server (port 5173, internal only)
└── mysql        → MySQL 8 (port 3306, internal only) + named volume
```

`nginx.conf` handles two server blocks: one FastCGI proxy for `/api/` (with `SCRIPT_FILENAME` pointing to `public/index.php`) and a plain HTTP proxy for everything else to the Vite dev server.

---

## 2. Backend — Laravel REST API

### Auth Endpoints (public)

```
POST /api/auth/register   { name, email, password, password_confirmation }
POST /api/auth/login      { email, password }
POST /api/auth/logout     ← requires Bearer token
```

Sanctum returns a plain API token on register and login. No cookie-based sessions — `stateful` domains are not configured. Logout revokes the current token via `$request->user()->currentAccessToken()->delete()`.

### Project Endpoints (all require `auth:sanctum`)

```
GET    /api/projects           ?search=&status=&priority=&sort_by=&sort_dir=
GET    /api/projects/{id}
POST   /api/projects
PUT    /api/projects/{id}
DELETE /api/projects/{id}
```

### Database Schema — `projects` table

| Column         | Type                                              | Notes                    |
|----------------|---------------------------------------------------|--------------------------|
| id             | BIGINT UNSIGNED PK AUTO_INCREMENT                 |                          |
| user_id        | BIGINT UNSIGNED FK → users.id                     | CASCADE DELETE           |
| client_name    | VARCHAR(255)                                      | required                 |
| project_name   | VARCHAR(255)                                      | required                 |
| description    | TEXT NULLABLE                                     |                          |
| status         | ENUM('Planning','In Progress','On Hold','Completed') | required              |
| priority       | ENUM('Low','Medium','High')                       | required                 |
| start_date     | DATE NULLABLE                                     |                          |
| due_date       | DATE NULLABLE                                     | must be ≥ start_date     |
| created_at     | TIMESTAMP                                         |                          |
| updated_at     | TIMESTAMP                                         |                          |

### Key Files

```
app/
├── Http/
│   ├── Controllers/API/
│   │   ├── AuthController.php
│   │   └── ProjectController.php
│   ├── Requests/
│   │   ├── StoreProjectRequest.php
│   │   └── UpdateProjectRequest.php
│   └── Resources/
│       └── ProjectResource.php
└── Models/
    └── Project.php

database/
├── migrations/
│   └── ..._create_projects_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── UserSeeder.php        ← creates demo@example.com / password
    └── ProjectSeeder.php     ← reads test_data.json, assigns to demo user
```

### Query Logic (`ProjectController@index`)

All parameters are optional. Applied as Eloquent builder chains scoped to `auth()->user()->projects()`:

- `search` → `WHERE client_name LIKE %?% OR project_name LIKE %?%`
- `status` → `WHERE status = ?`
- `priority` → `WHERE priority = ?`
- `sort_by` → column name whitelist: `client_name`, `project_name`, `status`, `priority`, `due_date`, `start_date`; defaults to `created_at`
- `sort_dir` → `asc` or `desc`; defaults to `desc`

### Validation (`StoreProjectRequest` / `UpdateProjectRequest`)

| Field        | Rules                                                             |
|--------------|-------------------------------------------------------------------|
| client_name  | `required\|string\|max:255`                                       |
| project_name | `required\|string\|max:255`                                       |
| description  | `nullable\|string`                                                |
| status       | `required\|in:Planning,In Progress,On Hold,Completed`             |
| priority     | `required\|in:Low,Medium,High`                                    |
| start_date   | `nullable\|date`                                                  |
| due_date     | `nullable\|date\|after_or_equal:start_date`                       |

Validation failures return `422` with JSON `{ message, errors: { field: [messages] } }`.

### API Response Shape (`ProjectResource`)

```json
{
  "id": 1,
  "clientName": "Acme Corporation",
  "projectName": "Corporate Website Redesign",
  "description": "...",
  "status": "In Progress",
  "priority": "High",
  "startDate": "2026-06-01",
  "dueDate": "2026-07-15",
  "createdAt": "2026-08-08T00:00:00Z"
}
```

Keys are camelCase to match `test_data.json` and Vue conventions.

---

## 3. Frontend — Vue 3 SPA

### Directory Structure

```
src/
├── api/
│   ├── axios.js          ← axios instance, request/response interceptors
│   ├── auth.js           ← register(), login(), logout()
│   └── projects.js       ← index(), show(), store(), update(), destroy()
├── stores/
│   ├── authStore.js      ← token (localStorage), user, login/logout actions
│   └── projectStore.js   ← projects[], filters, CRUD actions
├── router/
│   └── index.js          ← routes + nav guard (redirect to /login if no token)
├── views/
│   ├── auth/
│   │   ├── LoginView.vue
│   │   └── RegisterView.vue
│   └── projects/
│       ├── ProjectBoardView.vue   ← Kanban (default view)
│       └── ProjectTableView.vue  ← table with filter/sort toolbar
└── components/
    ├── KanbanColumn.vue           ← vue-draggable-plus, one per status
    ├── ProjectCard.vue            ← card: name, client, priority badge, due date
    ├── ProjectTable.vue           ← sortable columns
    ├── ProjectFilters.vue         ← search input + status/priority dropdowns
    └── ProjectFormModal.vue       ← shared create/edit modal (used by both views)
```

### Auth Flow

1. Login/Register → Sanctum returns `{ token, user }`
2. `authStore` saves token to `localStorage`, sets Axios default header: `Authorization: Bearer {token}`
3. Axios request interceptor attaches the header on every request
4. Axios response interceptor: on `401` → clear store + `router.push('/login')`
5. Logout → calls `POST /api/auth/logout`, clears store + localStorage, redirects to `/login`

### Kanban Board View

- `ProjectBoardView` groups `projectStore.projects` into 4 arrays by status
- `KanbanColumn` receives its status label and its project array; renders a `vue-draggable-plus` `<VueDraggable>` list
- On drop into a different column: fires `PUT /api/projects/{id}` with `{ ...project, status: newColumnStatus }`
- `projectStore` optimistically updates locally; reverts on API error + shows error toast

### Table View

- `ProjectTableView` shows `ProjectFilters` above `ProjectTable`
- Filter/sort state lives in `projectStore` (not component-local) so it persists across board/table toggle
- Filter changes trigger `projectStore.fetchProjects()` which calls `GET /api/projects` with current params
- Column headers are clickable to toggle `sort_by` and `sort_dir`

### View Toggle

A `[Board] [Table]` toggle in the top nav sets a `viewMode` ref in `projectStore` (persisted to `localStorage`). Vue Router does not change on toggle — it's a conditional `v-if` render within a single route.

### `ProjectFormModal`

- Shared between board and table views
- Create mode: empty form, submits `POST /api/projects`
- Edit mode: pre-populated from `projectStore`, submits `PUT /api/projects/{id}`
- Per-field validation errors from `422` response rendered under each input
- Closes and refreshes project list on success

---

## 4. Error Handling

| Scenario                    | Behaviour                                              |
|-----------------------------|--------------------------------------------------------|
| 422 validation error        | Per-field error messages under inputs in modal         |
| 401 Unauthorized            | Clear token, redirect to `/login`                      |
| 404 Not Found               | Toast notification                                     |
| 500 Server Error            | Toast notification                                     |
| Drag-drop status update fail| Optimistic revert + toast notification                 |
| Empty project list          | Empty state message in each board column / table       |

---

## 5. Seeder

`UserSeeder` creates one default user:
- Email: `demo@example.com`
- Password: `password`
- Name: `Demo User`

`ProjectSeeder` reads `fullstack-developer-assessment/test_data.json`, maps camelCase fields to snake_case columns, and inserts all 12 rows with `user_id` set to the demo user.

Run with: `php artisan migrate:fresh --seed`

---

## 6. Testing (PHPUnit)

Feature tests in `tests/Feature/`:

| Test File              | Coverage                                                    |
|------------------------|-------------------------------------------------------------|
| `AuthTest.php`         | register, login, logout, protected route without token      |
| `ProjectCrudTest.php`  | index, show, store, update, destroy — happy path            |
| `ProjectValidationTest.php` | missing required fields, invalid status/priority, due_date before start_date |
| `ProjectFilterTest.php`| search, status filter, priority filter, sort_by + sort_dir  |

Each test class uses `RefreshDatabase` and creates its own user + Sanctum token.

---

## 7. Styling

**Bootstrap 5** (`bootstrap` + `@popperjs/core`) imported globally in `main.js`. No additional component library — Bootstrap utility classes and components (cards, badges, modals, tables, navbar) are used directly. Bootstrap is specified in the job description.

Priority badges use Bootstrap `badge` with contextual colours: `High` → `bg-danger`, `Medium` → `bg-warning text-dark`, `Low` → `bg-success`. Status badges: `In Progress` → `bg-primary`, `Planning` → `bg-secondary`, `On Hold` → `bg-warning text-dark`, `Completed` → `bg-success`.

Toast notifications use `vue-toastification`.

---

## 8. Seed Data

Source: `fullstack-developer-assessment/test_data.json` — 12 projects across all 4 statuses and all 3 priority levels. Used to demonstrate the Kanban board with populated columns from first run.

---

## 9. Submission Deliverables

### README.md (required — Documentation 10%)

Must cover:
- **Setup instructions**: prerequisites (Docker + Docker Compose), one-command startup (`docker compose up --build`)
- **Technology choices**: why Laravel, Vue 3, MySQL, Docker
- **How to run**: `docker compose up --build`, seed command, default credentials (`demo@example.com` / `password`), URLs (`http://localhost`)
- **Assumptions made**: projects are user-scoped (auth required), no multi-tenancy, dates are optional, seeder pre-loads test data

### Technical Reflection (required — Communication 5%)

To be written at the end of implementation. Answers to include in README or a separate `TECHNICAL_REFLECTION.md`:

1. **Why this approach?** Laravel handles validation, response formatting, and database access cleanly out of the box. Vue 3 with Pinia is a natural fit for a reactive SPA. Docker ensures the evaluator can run the whole stack with one command.
2. **Tradeoffs?** Token auth is simpler for a pure SPA but tokens in `localStorage` carry XSS risk — would switch to `httpOnly` cookie-based Sanctum in production. Server-side filtering scales better than client-side but fires a request on every filter change. Deployment skipped in favour of code quality.
3. **What to improve?** Add pagination (currently returns all records), debounce the search input to reduce API calls, add a project detail page with full description.
4. **Hardest part?** The Kanban drag-and-drop optimistic update — storing the previous status, catching API errors, reverting the value, and showing a toast all had to happen in the right order to keep the UI in sync with the database.
5. **AI tools?** Claude Code (claude.ai/code) was used for brainstorming the architecture, writing the design spec and implementation plan, and generating boilerplate. All output was reviewed and understood before committing.

### Deployment (optional bonus — skipped)

Not implemented. Would add: multi-stage Dockerfile (build Vue bundle into nginx image), GitHub Actions CI/CD pipeline, deployment to Railway or Fly.io.
