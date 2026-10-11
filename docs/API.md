# API reference

REST API under `/api/v1`. The Postman collection [`docs/postman/OMS.postman_collection.json`](postman/OMS.postman_collection.json) has every endpoint with its parameters, example bodies and real success and error responses.

## Authentication

Laravel Sanctum **SPA cookie sessions** (no API tokens). The Vue app runs on the same origin, so the browser handles the cookies.

1. `GET /sanctum/csrf-cookie` sets the `XSRF-TOKEN` and session cookies.
2. `POST /api/v1/auth/login` with `{ "email", "password" }` and the header `X-XSRF-TOKEN` (the URL-decoded cookie value).
3. Every later request sends the session cookie and, for writes, `X-XSRF-TOKEN`.

Outside a browser (Postman, curl) also send `Accept: application/json` and `Referer: http://localhost:8000/`: Sanctum only starts a session for requests from a stateful domain (`SANCTUM_STATEFUL_DOMAINS`). Login is throttled to 5 attempts per minute per email + IP. A deactivated user is logged out on their next request.

Demo users (password `password`): `admin@oms.test`, `manager1@oms.test`, `manager2@oms.test`, `staff1@oms.test`, `staff2@oms.test`, `staff3@oms.test`.

## Responses

```json
{ "success": true, "message": "OK", "data": { }, "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 } }
```

`meta` is present on paginated lists (`?page=` and `?per_page=`, max 100). Errors:

```json
{ "success": false, "message": "Customer must exist and be active. (and 1 more error)", "errors": { "customer_id": ["Customer must exist and be active."], "items": ["Order must contain at least one product."] } }
```

| Status | Meaning |
|---|---|
| 401 | Not logged in, or the session ended (also after deactivation). |
| 403 | Logged in but without the permission (checked before validation), or a staff user asking for an order not assigned to them. |
| 404 | Record not found (also for another user's notification). |
| 409 | Version conflict: the order changed since you loaded it (optimistic locking). Reload and try again. |
| 419 | Missing or wrong CSRF token. |
| 422 | Validation error (`errors` per field) or a business rule, e.g. invalid status transition, insufficient stock, customer has orders, last admin. |
| 429 | Too many login attempts (`Retry-After` header). |

**Money** is always integer cents (`36300` = $363.00), returned with a `*_formatted` string. **Tax rates and percent discounts** are basis points (`1000` = 10%). Clients never send totals: the server calculates them.

## Endpoints

### Auth

Sanctum SPA cookie auth. Run **Get CSRF cookie** first, then **Login**. The collection scripts copy the `XSRF-TOKEN` cookie into the `X-XSRF-TOKEN` header and send a `Referer` from `baseUrl` on every request, which Sanctum needs to treat the request as stateful. The session cookie then authenticates the following requests.

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | Public | Sets the `XSRF-TOKEN` and session cookies. Returns 204. |
| POST | `/api/v1/auth/login` | Public (throttled: 5 attempts/min per email + IP) | Starts a session. Wrong credentials and deactivated accounts get the same 422 message. |
| GET | `/api/v1/auth/me` | Logged in | The logged-in user with roles and permissions (the SPA hides buttons with them; the API still checks every request). |

### Lookups & health

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/health` | Public | API and database check. |
| GET | `/api/v1/lookups` | Logged in | Option lists for forms and filters: order statuses, priorities, and users an order can be assigned to (active managers and staff). |

### Dashboard

Figures are cached for 5 minutes; any order, customer or product change clears the cache. Money values are integer cents with a `_formatted` string beside them.

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/dashboard/summary` | `dashboard.view` | Order counts by status, booked revenue (confirmed → completed), average order value, customer and product totals for a date range. Params: `from`, `to`. |
| GET | `/api/v1/dashboard/revenue-trend` | `dashboard.view` | Booked revenue and order count per day, week or month. Empty periods are returned as zero. Params: `period`, `from`, `to`. |
| GET | `/api/v1/dashboard/status-distribution` | `dashboard.view` | Order count for every status in the range. Params: `from`, `to`. |
| GET | `/api/v1/dashboard/my-work` | `orders.view_assigned` | The caller's own assigned orders: open count and count per status. The example is a staff user's. |

### Customers

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/customers` | `customers.view` | Paginated. `search` uses a FULLTEXT index on name, email and company (prefix match). In the collection, a customer that has orders is saved as `orderCustomerId` for the order requests. Params: `search`, `status`, `sort`, `direction`, `page`, `per_page`. |
| POST | `/api/v1/customers` | `customers.create` | New customers start active. `email` must be unique. |
| GET | `/api/v1/customers/{customer}` | `customers.view` | Includes an order summary (`stats`): totals per status and booked order value. |
| PUT | `/api/v1/customers/{customer}` | `customers.update` | Full update (same fields as create). Status has its own endpoint. |
| PATCH | `/api/v1/customers/{customer}/status` | `customers.update` | Inactive customers cannot get new orders. |
| DELETE | `/api/v1/customers/{customer}` | `customers.delete` | Soft delete, only for customers without orders. A customer with orders keeps its history: deactivate it instead. |

### Products

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/products` | `products.view` | Paginated, with `is_low_stock`. Prices are integer cents. Params: `search`, `status`, `sort`, `direction`, `page`, `per_page`. |
| POST | `/api/v1/products` | `products.manage` | `price` in cents, `tax_rate_bps` in basis points (1800 = 18%). `initial_stock` writes an "Opening stock" ledger row. SKU: uppercase letters, digits and dashes, unique. |
| GET | `/api/v1/products/{product}` | `products.view` | One product. |
| PUT | `/api/v1/products/{product}` | `products.manage` | Same fields as create without `initial_stock`: stock only changes through stock adjustments and orders. |
| PATCH | `/api/v1/products/{product}/status` | `products.manage` | `active` \| `inactive` \| `discontinued`. There is no DELETE: products leave the catalogue by being discontinued. |
| POST | `/api/v1/products/{product}/stock-adjustments` | `products.manage` | Signed quantity (+ adds, − removes). Atomic: stock can never go below zero. Writes a stock ledger row. |

### Orders

Totals are always calculated on the server from current product prices; any totals sent by the client are ignored. Staff only ever see orders assigned to them (lists, filters and direct ids).

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/orders` | `orders.view_any or orders.view_assigned (staff: own orders only)` | Paginated list with filters. `search` matches an order number (ORD-10001), customer name/email or product name/SKU. Params: `search`, `status[]`, `priority[]`, `customer_id`, `assigned_to`, `date_from`, `date_to`, `sort`, `direction`, `page`, `per_page`. |
| POST | `/api/v1/orders` | `orders.create` | Creates a `draft` or `pending` order (no stock change yet). `discount_value` is cents for `fixed` and basis points for `percent` (1000 = 10%). Optional `assigned_to` (active manager/staff) and a first `note`. |
| GET | `/api/v1/orders/{order}` | `orders.view_any, or orders.view_assigned for own orders` | Order with customer, items (price snapshots, per-line discount and tax), assignee, creator and `version` (needed for updates). |
| PUT | `/api/v1/orders/{order}` | `orders.update` | Optimistic locking: send the `version` you loaded. If someone changed the order since, the API returns **409** and nothing is saved. Draft/pending orders accept every field; later states only `priority`. Status and assignee have their own endpoints. |

### Order workflow

Status machine: draft ⇄ pending → confirmed → processing → ready → completed; any non-final state → cancelled. Confirming deducts stock (atomically, all lines or none); cancelling a confirmed order restocks.

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/orders/{order}/allowed-transitions` | `view the order` | Statuses the **current user** may move this order to, and whether remarks are required. |
| POST | `/api/v1/orders/{order}/status` | `orders.change_status (+ orders.update / orders.cancel for some targets)` | Moves the order along the workflow and writes a status-history row. `remarks` is required when cancelling. |
| GET | `/api/v1/orders/{order}/status-history` | `view the order` | Every status change, oldest first: from, to, user, time, remarks. |
| POST | `/api/v1/orders/{order}/assign` | `orders.assign` | `assigned_to` must be an active manager or staff member; `null` unassigns. The new assignee gets a (queued) notification. |
| POST | `/api/v1/orders/{order}/notes` | `notes.create (and can view the order)` | Adds an internal note. |
| GET | `/api/v1/orders/{order}/notes` | `view the order` | Newest first, paginated. Params: `page`, `per_page`. |
| GET | `/api/v1/orders/{order}/activities` | `view the order` | Human-readable activity (created, updated, status changed, stock deducted, assigned, note added…), newest first. Params: `page`, `per_page`. |
| DELETE | `/api/v1/orders/{order}` | `orders.delete` | Soft delete; only draft, pending or cancelled orders. |

### Users

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/users` | `users.manage` | Paginated. Each user has one role. Params: `search`, `role`, `status`, `sort`, `page`, `per_page`. |
| POST | `/api/v1/users` | `users.manage` | Password at least 8 characters, confirmed; stored hashed and never returned. |
| GET | `/api/v1/users/{user}` | `users.manage` | One user. |
| PUT | `/api/v1/users/{user}` | `users.manage` | A blank password keeps the current one. You cannot remove your own admin role, or the last active admin. |
| PATCH | `/api/v1/users/{user}/status` | `users.manage` | A deactivated user cannot log in, and their current session is ended on the next request. You cannot deactivate yourself or the last active admin. |

### Roles & permissions

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/roles` | `users.manage or roles.manage` | Roles with their permissions and user counts. `locked`: the admin role always has every permission. |
| GET | `/api/v1/permissions` | `users.manage or roles.manage` | Every permission, grouped by module. |
| PUT | `/api/v1/roles/{role}/permissions` | `roles.manage` | Replaces the full permission list of a role. Applies to its users on their next request; audited as `permissions_changed`. |

### Audit log

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/audit-logs` | `audit.view` | Newest first. Each row: who, what (`event`), which record (`subject`) and the changed fields as readable old → new values (enum labels, money, names). Params: `auditable_type`, `auditable_id`, `event`, `user_id`, `from`, `to`, `page`, `per_page`. |

### Notifications

The caller's own database notifications (order assigned, status changed, low stock). The SPA polls the unread count every 30 seconds.

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/api/v1/notifications/unread-count` | Logged in | Number of unread notifications. |
| GET | `/api/v1/notifications` | Logged in | Newest first. `unread=1` for unread only. Params: `unread`, `page`, `per_page`. |
| POST | `/api/v1/notifications/{id}/read` | Logged in (own notifications only) | Another user's notification id returns 404. |
| POST | `/api/v1/notifications/read-all` | Logged in | Returns how many were marked. |

### Logout

| Method | Path | Access | Description |
|---|---|---|---|
| POST | `/api/v1/auth/logout` | Logged in | Ends the session and rotates the CSRF token. |
