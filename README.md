# RequestRelay

RequestRelay is a small, independent technical demonstration for a Mid–Senior Web Developer interview. A customer submits a print or service request, and staff review it and advance it through a controlled workflow. It is not a representation of Minuteman Press's actual internal architecture.

The customer page and internal dashboard consume the same PHP REST API. That HTTP boundary also allows a separate proprietary desktop application to list requests, inspect details, and update status without accessing MySQL directly. Use fictional customer information only.

## Architecture

Dockerized LAMP: Linux containers, Apache, PHP 8.4, and MySQL 8.4. Composer provides PSR-4 autoloading under the existing `MmpDemo\` namespace.

```text
Customer form ───────┐
Internal dashboard ─┼─ HTTP JSON → Apache public/index.php
Desktop client ─────┘                   ↓
                               RequestController
                                       ↓
                                RequestService
                                       ↓
                              RequestRepository
                                       ↓
                                  PDO → MySQL
```

- Controller: route matching, HTTP input, JSON responses, and response codes.
- Service: validation, workflow rules, and transaction coordination.
- Repository: prepared SQL statements, persistence, and transaction primitives.
- StatusWorkflow: a small explicit transition map.
- Browser clients: semantic HTML, CSS, and vanilla JavaScript `fetch()`.

Only `public/` is the document root. Apache's `FallbackResource` supplies clean URLs without requiring mod_rewrite. Templates, source, database scripts, credentials, and Composer files live outside that root.

## Run locally

Requires Docker Engine with Compose or Docker Desktop configured for Linux containers. Native PHP, Composer, Apache, and MySQL are not required.

1. Copy the environment template:
   ```bash
   cp .env.example .env
   ```
2. Replace both example passwords in `.env` with distinct local values.
3. Build and start:
   ```bash
   docker compose up -d --build
   docker compose ps
   docker compose logs -f app
   ```
   MySQL must become healthy. The app installs locked Composer dependencies before starting Apache; the first start requires network access and may take a minute.
4. Open [customer submission](http://localhost:8080/) or the [internal dashboard](http://localhost:8080/dashboard).

The dashboard intentionally has **no authentication or authorization**. Anyone with access can read customer details and change status. Keep this demo on a trusted machine/network until access controls are implemented. MySQL has no published host port.

Database initialization scripts run **only on a fresh MySQL volume**. Normal restarts preserve data. Changing environment passwords does not change users in an existing database.

### Upgrading the original scaffold database

The original history table had `pervious_status` and lacked `new_status`. Fresh volumes now use the corrected `001_schema.sql`. For an existing scaffold database, back it up and inspect the history table first:

```bash
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"'
```

If the table still has the original columns **and contains no history rows**, run once in that MySQL session:

```sql
ALTER TABLE request_status_history
  CHANGE pervious_status old_status VARCHAR(30) NULL,
  ADD new_status VARCHAR(30) NOT NULL AFTER old_status;
```

If history rows already exist, their missing destination statuses require an explicit data-repair decision; do not invent historical statuses. The working database used to complete this demo was empty of history and has already been upgraded.

**Destructive reset:** `docker compose down -v` permanently deletes this project's MySQL volume and every stored request/history row. Only use it when deliberately discarding demo data. A subsequent `docker compose up -d --build` initializes the corrected schema.

## REST API

Base URL: `http://localhost:8080`. Write requests require `Content-Type: application/json` and a JSON object. Timestamps are database UTC, formatted `YYYY-MM-DD HH:MM:SS`. Dates use `YYYY-MM-DD`.

### Request representation

```json
{
  "data": {
    "id": 1,
    "customer_name": "Fictional Customer",
    "customer_email": "demo@example.test",
    "service_type": "Flyers",
    "quantity": 100,
    "request_description": "Fictional interview sample",
    "due_date": "2026-12-01",
    "request_status": "submitted",
    "created_at": "2026-09-14 16:00:00",
    "updated_at": "2026-09-14 16:00:00",
    "allowed_statuses": ["acknowledged", "cancelled"]
  }
}
```

### POST /api/requests

Create a request. Name (100 characters), email (255), service type (50), and quantity are required. Quantity must be a JSON integer from 1 to 4294967295. Description is optional text, at most 65535 bytes. Due date is optional, null or empty for none; supplied dates must be real dates from year 1000 through 9999. Service type is free text; the browser offers common choices. Unknown extra fields are ignored; clients cannot set initial status.

```bash
curl -i http://localhost:8080/api/requests \
  -H 'Content-Type: application/json' \
  -d '{"customer_name":"Fictional Customer","customer_email":"demo@example.test","service_type":"Flyers","quantity":100,"request_description":"Fictional interview sample","due_date":"2026-12-01"}'
```

Returns **201**, the request representation above, and `Location: /api/requests/{id}`. Creation records an initial history entry with `old_status: null` and `new_status: "submitted"`, in the same transaction.

### GET /api/requests

No body. Returns **200** with `{"data":[...request representations...]}`, newest first by creation time and ID, or `{"data":[]}`. No pagination in this small demo.

### GET /api/requests/{id}

No body. Returns **200** with the request representation above, or **404** if absent.

### PATCH /api/requests/{id}/status

```bash
curl -i -X PATCH http://localhost:8080/api/requests/1/status \
  -H 'Content-Type: application/json' -d '{"status":"acknowledged"}'
```

Returns **200** with the updated request representation (for example `request_status: "acknowledged"`, `allowed_statuses: ["in_progress","cancelled"]`), **404** for a missing request, **422** for an unrecognized status, or **409 Conflict** for a recognized but disallowed transition. Conflict is used because the operation conflicts with the request's current state. Repeating the current status is also a conflict and creates no history entry.

```text
submitted → acknowledged → in_progress → ready → completed
    └────────────┴──────────────┴──────→ cancelled
```

Cancellation is allowed only from submitted, acknowledged, and in_progress. Completed and cancelled are terminal. PHP enforces every transition; browser controls are only a convenience. Row locking prevents concurrent updates from validating against stale state, and a transaction makes status plus history atomic.

### GET /api/requests/{id}/history

No body. Returns **200**, chronological by timestamp and then ID, or **404** for a missing request.

```json
{
  "data": [
    {"id":1,"request_id":1,"old_status":null,"new_status":"submitted","changed_at":"2026-09-14 16:00:00"},
    {"id":2,"request_id":1,"old_status":"submitted","new_status":"acknowledged","changed_at":"2026-09-14 16:01:00"}
  ]
}
```

### Errors

All API errors use this envelope:

```json
{"error":{"code":"INVALID_STATUS_TRANSITION","message":"A submitted request cannot transition to completed."}}
```

Validation errors additionally provide a `fields` map, for example:

```json
{"error":{"code":"VALIDATION_ERROR","message":"Please correct the submitted fields.","fields":{"customer_email":"Enter a valid email address."}}}
```

| HTTP | Meaning / code |
| --- | --- |
| 400 | Malformed JSON or non-object body: INVALID_JSON |
| 404 | Missing request: REQUEST_NOT_FOUND; unknown route: NOT_FOUND |
| 405 | Unsupported method: METHOD_NOT_ALLOWED (includes Allow header) |
| 409 | Disallowed transition: INVALID_STATUS_TRANSITION |
| 413 | JSON body exceeds 1 MiB: BODY_TOO_LARGE |
| 415 | Wrong content type: UNSUPPORTED_MEDIA_TYPE |
| 422 | Field validation: VALIDATION_ERROR; invalid ID: INVALID_ID; unknown status: INVALID_STATUS |
| 500 | Unexpected server failure: INTERNAL_ERROR |

IDs must be positive integers representable by PHP. Internal failures return a generic message; exception details go to PHP/Apache logs, not API clients.

## Testing and debugging

```bash
docker compose exec -T app composer test
docker compose exec -T app composer lint
docker compose exec -T app composer validate --strict
docker compose logs --tail=100 app db
```

PHPUnit 12 tests the full 6×6 status transition matrix (including all terminal-state and cancellation rules), unknown statuses, valid normalized input, optional dates, and invalid name/email/service/quantity/date/description inputs. Tests do not need a database and do not modify data. API/database verification is performed separately against the running stack.

## Design decisions and tradeoffs

- Native PHP keeps the scope small and exposes fundamentals instead of hiding them behind Laravel.
- PDO prepared statements bind input separately from SQL; native prepares are enabled.
- The service owns business rules; the repository owns SQL. No interface or framework is needed for this small application.
- MySQL InnoDB transactions and a row lock protect coordinated status/history writes.
- A string status column and an explicit PHP map keep the workflow easy to inspect. Direct database writes bypass these rules and should be restricted operationally.
- The initial submission is also audited. Foreign keys connect history to requests; status and request-history indexes support lookups.
- Dockerized LAMP preserves a familiar portable runtime; existing Compose and namespace identifiers remain unchanged.
- Vanilla JavaScript demonstrates API consumption. Dynamic content uses textContent/DOM methods rather than HTML interpolation.
- Listing is intentionally unpaginated, history records statuses/timestamps rather than staff identity, and no uploads, payments, desktop GUI, or authentication are included.

## Production improvements and deployment

Before public deployment: add staff authentication/authorization, API credentials and scopes for desktop clients, rate limiting, HTTPS, secrets management, backups/restore procedures, pagination, structured logging/monitoring, and a deployment pipeline. Add a CSRF strategy when cookie-based authentication is introduced. Define audit actor identity and retention policy alongside authentication.

The supplied Compose file is a local development/demo setup: it bind-mounts source and installs development dependencies at startup. For deployment, build an immutable application image with `composer install --no-dev --optimize-autoloader` during the build, copy application files into it, remove the source bind mount, and start Apache directly. Use protected configuration and least-privilege credentials rather than example passwords; keep MySQL internal. Protect Docker logs, which may contain diagnostic details.

On a separate Windows host, use Docker Desktop/Engine capable of **Linux containers**, not Windows container mode. The application still runs under Linux/Apache/PHP; it does not require IIS or Windows PHP. Check port 8080/firewall access and filesystem mounts, configure TLS at a reverse proxy, and provision/migrate the database deliberately. Named volumes do not transfer automatically between hosts: back up and restore MySQL. Do not copy the local .env or fictional verification data as production configuration.
