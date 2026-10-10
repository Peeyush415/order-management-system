# AI Usage

This project was built with an AI coding assistant (Claude Code) as a pair programmer.

## How it was used

- I wrote the design (tables, flows, rules) and a step-by-step build plan before coding. The assistant worked one step at a time from that plan.
- For each step, the assistant proposed the work first. I approved it before any file changed.
- The assistant ran the checks (`php artisan test`, `pint --test`, `npm run test`, `npm run build`) before each step was marked done.
- I reviewed every diff and made every commit myself.
- When generated code differed from the plan, the assistant flagged it and I made the call.

## Step log

One line per finished step: what the assistant did, and what I checked or changed.

- **1.1 MySQL connection.** Switched `.env` / `.env.example` from SQLite to MySQL and set database drivers for cache, queue and session. Checked `migrate:status` and the tables in phpMyAdmin.
- **1.2 Sanctum + health API.** Installed Sanctum, enabled `statefulApi()`, added the `/api/v1` prefix and `GET /api/v1/health`. Opened the endpoint in the browser.
- **1.3 Response format + errors.** Added the `ApiResponse` envelope, a `BusinessException` base and central JSON exception rendering, plus feature tests. Reviewed that no internal details leak in 500 responses.
- **1.4 Vue 3 SPA shell.** Set up Vue, Router, Pinia, Vue Query and the axios client, the `app.blade.php` shell and the catch-all route. The assistant tightened the catch-all regex so pages like `/updates` are not excluded, and added a test for it.
- **1.5 Clone-and-run.** Updated the `composer setup` script, moved PHPUnit to a MySQL `oms_testing` database (with `force="true"` so tests can never hit `oms`), and added Vitest with a sample test, a Pint config, this file and the README Quick Start. Verified `composer setup` against a fresh database.
- **2.1 Users + roles/permissions.** Added `is_active`, `last_login_at` and soft deletes to users, installed spatie/laravel-permission, and seeded 20 permissions, 3 roles and 6 demo users. The test spells out the permission matrix by hand instead of reusing the seeder constants, so a wrong seeder cannot pass its own test. I asked to keep it simple, so demo users live in `DatabaseSeeder` instead of a separate seeder.
- **2.2 Customers.** Added the `CustomerStatus` enum, the customers migration with named indexes (unique email, status + created_at, FULLTEXT on name/email/company), the model, factory and model tests. Checked the indexes with `SHOW INDEX` and that the migration rolls back cleanly.
- **2.3 Products.** Added the `ProductStatus` enum, the products migration (price in cents, tax in basis points, unsigned stock, named indexes), the model with `stock_quantity` kept out of `$fillable`, a factory with inactive/discontinued/low-stock states, and model tests, including one that proves the database refuses negative stock.
