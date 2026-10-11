# Technical notes

A short companion to the README: how the system is built, why, what was hard, and what comes next.

## Architecture

A single Laravel 13 application serves both the REST API (`/api/v1`) and the Vue 3 SPA (one catch-all Blade view). Same origin means Sanctum can use ordinary session cookies with CSRF protection, with no CORS setup and no tokens stored in the browser.

A request goes through four layers, each with one job:

1. **Route** with `->can(...)`: the permission check runs before validation, so a user without permission gets 403, not a list of field errors that reveals the payload shape.
2. **FormRequest**: validation only. Fields the server owns (totals, prices, `created_by`, stock) are not in the rules, so they can't be set by the client.
3. **Controller**: thin. Authorizes again with `Gate::authorize()`, calls one service method, returns an API Resource in the `{ success, message, data, meta }` envelope.
4. **Service**: the business rules, inside `DB::transaction` with row locks where two users could collide.

Side effects that don't need to happen inside the request (notifications) go through events and queued listeners that run **after commit**. Side effects that must be atomic with the change (audit rows, status history, stock ledger, activity feed) are written in the same transaction.

On the frontend, TanStack Vue Query owns server state (caching, background refetch, invalidation after mutations), Pinia keeps only the session user and toasts, and list filters live in the URL so Back, refresh and shared links keep them.

## Major decisions

- **Permissions over roles.** 20 granular permissions (spatie). Policies check permissions plus ownership; no code checks a role name except the admin-lockout safeguard. Roles are editable bundles in the UI. The admin role is locked to every permission so the system can never lose the ability to manage itself.
- **Scoped queries, not just policies.** `Order::visibleTo($user)` is applied to every order query, so a staff user can't see someone else's order through a list, a search, a filter or a guessed id.
- **Money as integers.** Cents for amounts, basis points for tax and percent discounts. `OrderPricingCalculator` is pure and integer-only: the order discount is prorated across lines (leftover cents to the largest line), tax is applied after discount per line, rounding is half up. The assessment example comes out at exactly 36300. The frontend preview uses a BigInt port of the same algorithm, tested against the same cases, but the server total is the only one stored.
- **Snapshots on order items.** Name, SKU, unit price and tax rate are copied into `order_items`, so later product edits never change past orders.
- **A state machine in an enum.** `OrderStatus::allowedTransitions()` is the single source of truth; the policy adds who may make each move (staff: only forward operational steps on their own orders; cancelling needs `orders.cancel`).
- **Stock is reserved at confirmation**, not at creation: drafts and pending orders don't block stock. Cancelling a confirmed order restocks. Every change goes into a `stock_movements` ledger with the balance after it, so stock can always be explained.
- **Two histories, on purpose.** `order_activities` is a readable per-order feed for everyone who can see the order; `audit_logs` is an admin-only compliance record of raw old/new values across all entities.

## Concurrency: what happens if two users update the same order?

- **Editing an order:** optimistic locking. The client sends the `version` it loaded; the update runs `SELECT … FOR UPDATE`, compares versions, and returns **409** if someone saved first. The UI shows "This order was changed by someone else" with a reload button. Nothing is silently overwritten.
- **Status changes and assignment:** the order row is locked for the transition, so two people confirming at once can't both deduct stock. The second sees the new status and gets "invalid transition".
- **Stock:** a conditional update (`WHERE stock_quantity >= ?`) is atomic in MySQL, so two orders racing for the last units can't oversell; the loser gets 422 "Insufficient stock" and its whole confirmation rolls back. Products are locked in id order to avoid deadlocks between multi-line orders.
- **Last admin:** deactivating or demoting an admin locks all active admin rows (in id order) before counting, so two admins removing each other at the same moment can't both succeed.
- **Notifications** are dispatched after commit, so a rolled-back change never notifies anyone.

## Challenges

- **FULLTEXT and test transactions.** InnoDB FULLTEXT indexes only see committed rows, so search tests can't run inside `RefreshDatabase`'s transaction. Those tests use `DatabaseTruncation`.
- **N+1 queries.** `Model::preventLazyLoading()` is on outside production, so any lazy relation throws in dev and tests. Query-count tests assert that list endpoints run the same number of queries for 5 rows and for 50.
- **The dashboard index wasn't covering.** `EXPLAIN` showed the planned covering index still read the table because of soft deletes (`deleted_at IS NULL`). Adding `deleted_at` to the index made it index-only ("Using index").
- **spatie inside API requests.** `Role::withCount('users')` failed with a 500 because spatie resolves the user model from the current guard, which is `sanctum` in API requests. User counts are read from `model_has_roles` directly.
- **Sanctum outside the browser.** Postman and curl need a stateful `Referer`, `Accept: application/json` and the `X-XSRF-TOKEN` header. The Postman collection's scripts handle this.
- **Responsive layout.** Detail pages overflowed on phones: a CSS grid with only `lg:grid-cols-*` sizes its single mobile column to the widest table. A base `grid-cols-1` (`minmax(0, 1fr)`) fixed it.

## Performance considerations

- **Indexes chosen per query and checked with `EXPLAIN`:** e.g. `(assigned_to, status, order_date)` for staff lists, `(customer_id, order_date)` for customer history, `(order_date, status, grand_total, deleted_at)` as a covering index for dashboard aggregates, FULLTEXT on customers and products, and `(auditable_type, auditable_id, created_at)` / `(event, created_at)` / `(user_id, created_at)` for the audit log filters.
- **Aggregates in SQL**, one query per dashboard panel, cached for 5 minutes under versioned keys that writes invalidate after commit.
- **Eager loading everywhere** (lazy loading throws in dev), selected columns for list endpoints, and name lookups in the audit log done once per page, not per row.
- **Bulk inserts** for order items and seed data (600 orders seed in about 2 seconds).
- **Background work** (notifications) is queued so requests don't wait for it.
- **Frontend:** route-level code splitting, Vue Query caching with `keepPreviousData` so pagination doesn't flash, debounced search, and lookups cached for 5 minutes.

### How would this scale to 1 million orders?

The current design holds up well at that size because every hot query is index-driven and paginated. The next steps, in order of need:

1. **Pagination:** `COUNT(*)` for "page X of Y" gets slow on big filtered sets. Switch deep lists to cursor (keyset) pagination on `(order_date, id)` and show "about N results" or no total.
2. **Dashboard:** pre-aggregate into a daily summary table (per date and status: count, booked revenue), filled by a scheduled job and on order events, so the dashboard reads hundreds of rows instead of millions.
3. **Search:** move to Laravel Scout with Meilisearch or OpenSearch for typo tolerance and relevance, keeping MySQL for filtering.
4. **Infrastructure:** Redis for cache, queue and sessions; Horizon for workers; a read replica for reports and the dashboard.
5. **Data growth:** archive or partition `audit_logs` and `order_activities` by month; they grow faster than orders.

## Security considerations

- **Authentication:** Sanctum session cookies (HttpOnly) with CSRF protection; sessions regenerate on login and are invalidated on logout. Login is throttled to 5 attempts per minute per email + IP. Wrong passwords and deactivated accounts get the same message. A deactivated user is logged out on their next request by middleware.
- **Authorization at the API, every time:** route `->can()`, `Gate::authorize()` in controllers, policies with ownership checks, and scoped queries. Hiding buttons in the SPA is only a convenience.
- **Input:** FormRequests validate every endpoint; models declare `#[Fillable]` explicitly (never `$guarded = []`); server-owned fields are not fillable. Sorting uses whitelisted columns; FULLTEXT operators are stripped from search input; all queries use bindings.
- **Business integrity:** totals and prices are never accepted from the client; stock can't go negative (unsigned column plus conditional update); every important write is audited inside its transaction.
- **Output:** API Resources control exactly which fields leave the server (no password hashes, no tokens); Vue escapes output and `v-html` is never used on user content.
- **Secrets:** `.env` is not committed; `.env.example` has placeholders only.

Known gaps, listed under future improvements: no general API rate limit beyond login, no password reset or email verification, no two-factor authentication, no security headers (CSP, HSTS) configured, no audit-log retention policy.

## AI tools used

Claude Code (Anthropic), as a pair programmer working through a written plan one step at a time. Details, including the generated code that needed significant changes, are in [`AI_USAGE.md`](AI_USAGE.md).

## Future improvements

- Kanban board for orders, CSV export, duplicate order, PDF invoices (the API has room for them).
- Real-time notifications with Laravel Reverb + Echo (the events are already there).
- Cursor pagination and a daily dashboard summary table (see scaling).
- API rate limiting per user, password reset, email verification, 2FA, security headers.
- CI pipeline (Pint, PHPUnit, Vitest, build) on every push, and static analysis with Larastan.
- Docker setup for a one-command environment.
- End-to-end browser tests (Playwright) for the main flows.
