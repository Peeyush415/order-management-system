# Order Management System

Production-style Order Management System: a Laravel REST API (`/api/v1`) and a Vue 3 SPA served from the same app.

- **Backend:** Laravel 13, MySQL, Sanctum SPA cookie auth
- **Frontend:** Vue 3 (`<script setup>`), Vue Router, Pinia, TanStack Vue Query, Tailwind, built with Vite
- **Tests:** PHPUnit (MySQL), Vitest

## Requirements

| Tool     | Version |
|----------|---------|
| PHP      | 8.3+ (with `pdo_mysql`) |
| Composer | 2.x |
| Node.js  | 20.19+ / 22.12+ |
| MySQL    | 8.0+ or MariaDB 10.6+ (XAMPP works) |

## Quick Start

```bash
git clone <repo-url> order-management-system && cd order-management-system
cp .env.example .env            # set DB_USERNAME / DB_PASSWORD; create an empty database "oms"
composer setup                  # install, key, migrate + seed, build frontend
composer run dev                # http://localhost:8000  (server + queue worker + vite)
```

Create the database first, for example:

```sql
CREATE DATABASE oms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> `composer setup` runs `migrate:fresh --seed`, so running it again **resets all data** in `oms`.

Without the composer scripts:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve               # terminal 1
php artisan queue:work          # terminal 2
```

### Demo logins

All demo users have the password `password`.

| Role    | Email | Can do |
|---------|-------|--------|
| Admin   | `admin@oms.test` | Everything, including users, roles, audit log and deleting orders |
| Manager | `manager1@oms.test`, `manager2@oms.test` | Dashboard, all orders, customers; no deletes, no product or user management |
| Staff   | `staff1@oms.test`, `staff2@oms.test`, `staff3@oms.test` | Only orders assigned to them: change status (limited) and add notes |

## Environment variables

All variables live in `.env.example` with safe local defaults. The ones this project relies on:

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_URL` | `http://localhost:8000` | Base URL of the app |
| `DB_CONNECTION` / `DB_DATABASE` | `mysql` / `oms` | Main database |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / *(empty)* | MySQL credentials (XAMPP default) |
| `SESSION_DRIVER` | `database` | Sessions back Sanctum cookie auth |
| `SESSION_DOMAIN` | `null` | Cookie domain; keep `null` for localhost |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:8000,127.0.0.1:8000` | Hosts that get cookie (stateful) API auth |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | No Redis needed to run locally |

## Tests and code style

Backend tests use a **separate MySQL database `oms_testing`** (set in `phpunit.xml`), because FULLTEXT search and date functions are MySQL-specific. Host and credentials come from `.env`.

```bash
mysql -u root -e "CREATE DATABASE oms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan test                # backend tests (database: oms_testing)
npm run test                    # frontend tests (Vitest)
./vendor/bin/pint --test        # code style check (composer lint to fix)
npm run build                   # production assets
php artisan migrate:fresh --seed  # reset demo data
```

## Project layout

```
app/                    Laravel backend (controllers, services, policies, ...)
routes/api.php          REST API under /api/v1
routes/web.php          SPA catch-all: every non-API path returns the Vue shell
resources/views/app.blade.php   single SPA shell
resources/js/           Vue 3 SPA (main.js, App.vue, api/, composables/, pages/, router/)
tests/                  PHPUnit feature and unit tests
docs/AI_USAGE.md        How AI tools were used while building this project
```
