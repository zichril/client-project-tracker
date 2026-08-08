# Client Project Tracker

A fullstack Client Project Tracker built for the Koda Fullstack Developer Assessment.

See [TECHNICAL_REFLECTION.md](./TECHNICAL_REFLECTION.md) for the technical reflection (why this approach, tradeoffs, hardest part, AI tool disclosure).

## Technology Choices

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend | Laravel 13 (PHP 8.3) | Opinionated structure, Eloquent ORM, built-in API resources and form request validation |
| Frontend | Vue 3 + Vite | Composition API, Pinia state management, fast HMR |
| Database | MySQL 8 | Relational data, enum columns for status/priority, familiar to most teams |
| Auth | Laravel Sanctum | Lightweight API token auth, no session/cookie complexity for SPA |
| Styling | Bootstrap 5 | Specified in the job description; component-rich, responsive grid |
| Infrastructure | Docker (nginx + php-fpm + vite + mysql) | One-command setup, mirrors production deployment patterns |

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose v2)
- No local PHP, Node, or MySQL required

## Setup and Running

```bash
# Clone the repository
git clone <repo-url>
cd client-project-tracker

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
- Deployment is not included (see [TECHNICAL_REFLECTION.md](./TECHNICAL_REFLECTION.md) for what I'd add)
