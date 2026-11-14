# BoneRADF — API Guide

Status: Planned contract (not implemented yet). Every example below is a
documented contract, marked `planned:` where it describes future behavior.
Decisions D1–D8 are locked (see `app/docs/PRD.md` §1); security defaults come
from `app/docs/SECURITY.md`.

---

## 1. Quick start — zero to a JSON route (5 lines, planned)

```sh
php -S 127.0.0.1:8080 -t public        # or: bone serve
```

```php
// routes/web.php (planned)
$router->get('/hello', fn ($req) => ['message' => 'Hello, world!']);
```

That is the whole loop: define a route, hit it, get JSON. No `composer
install`, no `node build`, no config file required for the default case.

---

## 2. Project layout (every service looks the same)

```
app/
  public/
    index.php        — front controller (only web-accessible file)
  config/            — typed config + .env loader
  src/
    routes/          — route definitions (one file per domain)
    controllers/     — handlers (function or class-method)
    services/        — business logic, injected via container
    modules/         — app modules (Module interface, boot())
  modules/           — shared/optional framework modules (planned)
  .env               — secrets, never committed
  bin/bone           — CLI (route list, serve, module list)
  logs/              — structured JSON logs (outside webroot)
  tests/             — micro test runner output
```

Every service copies this shape. Differences between services live in
`config/`, `.env`, and `src/` — never in framework internals.

---

## 3. Defining routes (planned)

Method + path, parameters, groups, version prefix.

```php
// routes/api.php (planned)
$router->get('/v1/users/{id}', 'UserController@show');   // class method
$router->post('/v1/users', 'UserController@store');
$router->delete('/v1/users/{id}', 'UserController@destroy');

// Group with common prefix + middleware
$router->group('/v1', function (Router $r) {
    $r->get('/health', fn () => ['status' => 'ok', 'version' => '1.0.0']);
    $r->get('/posts', 'PostController@index');
    $r->post('/posts', 'PostController@store')
       ->middleware('auth', 'csrf');          // route-specific middleware
})->middleware('rate-limit');                 // group-level middleware
```

- Path params: `{id}` in path → `$request->param('id')`.
- Versioning is a prefix convention (`/v1/...`), not a magic config key —
  register `/v2` alongside `/v1` during migration.
- `bone route list` (planned) prints every registered route for verification.

---

## 4. Controllers / handlers (planned)

Either a closure or a class method. Typed input via Validator, JSON out.

```php
// Function style (planned)
$router->post('/v1/users', function (Request $req): array {
    $data = $req->validate([
        'name'  => 'required|string|min:2|max:80',
        'email' => 'required|email|unique:users,email',
    ]);
    return app(UserService::class)->create($data);   // array → 200 JSON
});

// Class-method style (planned)
final class UserController
{
    public function store(Request $req): array { /* ... */ }
}
```

Handler return contract: return an `array` (→ 200 `application/json`), a
`Response` built with helpers (any status), or throw an exception (→ error
envelope, §9).

---

## 5. Request / Response objects (planned)

```php
$req->method();                       // 'POST'
$req->path();                         // '/v1/users'
$req->query('page');                  // query string
$req->body();                         // decoded JSON array
$req->header('Authorization');
$req->param('id');                    // path param
$req->user();                         // authenticated identity (Auth module)
$req->validate($rules);               // validated data or 422 envelope

// Response helpers
Response::json($data, 200);
Response::json($data, 201)->header('Location', '/v1/users/42');
Response::noContent(204);
Response::error('NOT_FOUND', 'User 42 not found', 404);
```

Precedence for input: path params > body > query (documented so handlers
never guess where a value came from).

---

## 6. Middleware (planned)

```php
final class AuditMiddleware implements Middleware
{
    public function handle(Request $req, callable $next): Response
    {
        $start = hrtime(true);
        $resp  = $next($req);              // continue pipeline
        log('info', 'request', [
            'path' => $req->path(),
            'ms'   => (hrtime(true) - $start) / 1e6,
        ]);
        return $resp;
    }
}
```

- **Ordering**: registration order. Global middleware (registered in
  `config/middleware.php`) runs first, then group, then route-specific.
- **Abort semantics**: return a `Response` without calling `$next` to
  short-circuit (e.g. 401/403/429). Downstream handlers never run.
- **Errors**: throw or let exceptions bubble → kernel error handler →
  uniform envelope (§9), never an HTML page.
- Security middleware (SecurityHeaders, CSRF, Auth, RBAC, RateLimit) are
  ON by default and run before any handler (see `app/docs/SECURITY.md` §3).

---

## 7. Query builder (planned) — prepared statements only

No raw SQL API in core (locked D5/D6). The only execute path takes
`(sql, params)` with bound parameters; identifiers come from allowlists.

```php
$rows = db()
    ->table('users')
    ->select('id', 'name', 'email')
    ->where('status', '=', 'active')
    ->whereIn('role', ['admin', 'editor'])
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();                            // typed rows (array of assoc arrays)

$user = db()->table('users')->where('id', '=', $id)->first();  // ?array

db()->table('users')
    ->where('id', '=', $id)
    ->update(['name' => $name]);        // bound values only
```

- Injection probe contract (planned, tested): payload `' OR 1=1 --` bound as
  a value matches zero rows — it is data, never SQL.
- DSN support: MySQL/MariaDB, SQLite, Postgres (planned).

---

## 8. Validation rules (planned)

```php
$req->validate([
    'age'    => 'required|int|min:0|max:150',
    'email'  => 'required|email',
    'slug'   => 'required|pattern:/^[a-z0-9-]+$/',
    'name'   => 'required|string|min:2|max:80',
    'role'   => 'required|in:admin,editor,viewer',
    'ref'    => 'nullable|callback:is_unique_ref',   // custom callable
]);
```

Rule set: `required`, `nullable`, type rules (`string`, `int`, `bool`,
`float`, `email`, `array`, `date`), `min`/`max` (length or numeric bounds),
`pattern` (PCRE), `in` (allowlist), `unique` (DB check), `callback`.
Failure → `422 VALIDATION_FAILED` with per-field `details` in the envelope.

---

## 9. Security module usage (planned)

All five modules are ON by default; disabling requires an explicit config
flag (`SECURITY.md` §3). Key flows:

### CSRF token flow (browser clients)
1. `GET /v1/csrf-token` (planned) returns a signed token; cookie set
   non-`httponly` so JS can read it.
2. Client sends it on `POST/PUT/PATCH/DELETE` as header
   `X-CSRF-Token: <token>`.
3. Mismatch → `403 CSRF_TOKEN_INVALID`. GET/HEAD never require it.

### JWT issuance / verification
```php
// Issue (planned)
$token = auth()->issue(['sub' => $userId, 'role' => 'editor']); // HS256/RS256

// Client sends: Authorization: Bearer <token>
// Verification: signature + exp + nbf + iss/aud; alg pinned per config;
// "none" rejected. Verified identity: $req->user().
```

### RBAC role checks (route middleware, planned)
```php
$router->post('/v1/users', 'UserController@store')
       ->middleware('auth', 'rbac:users.create');
// No permission declared → denied (deny-by-default). Missing config → deny.
```

### Rate limit config (planned)
```ini
# .env
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```
Response headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
`X-RateLimit-Reset`; exceeded → `429 RATE_LIMITED` + `Retry-After`.
State is per-worker in-memory in v1 (limitation documented in `SECURITY.md` §12).

---

## 10. Error format — uniform JSON envelope (planned)

Every error, every route, every environment:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "request_id": "01J9ZK8R6V7Q0M2X4T6Y8B1C3D",
    "details": { "email": ["The email field is required."] }
  }
}
```

- `request_id` correlates with structured logs (`app/docs/SECURITY.md` §1).
- No stack traces, SQL, paths, or secrets in the envelope — ever.
- HTTP status mapping (planned):

| Status | code (examples) |
|--------|-----------------|
| 400 | `BAD_REQUEST` |
| 401 | `UNAUTHENTICATED` |
| 403 | `FORBIDDEN`, `CSRF_TOKEN_INVALID` |
| 404 | `NOT_FOUND` |
| 405 | `METHOD_NOT_ALLOWED` |
| 422 | `VALIDATION_FAILED` |
| 429 | `RATE_LIMITED` |
| 500 | `INTERNAL_ERROR` |

---

## 11. Module authoring (planned)

```php
namespace App\Modules\Billing;

use Boneradf\Module\Module;

final class BillingModule implements Module
{
    public function boot(Container $c): void
    {
        $c->bind(PaymentGateway::class, fn () => new StripeGateway(
            $c->get(Config::class)->get('billing.key'),
        ));
    }

    public function routes(Router $r): void
    {
        $r->post('/v1/billing/checkout', 'CheckoutController@store');
    }
}
```

Register in `config/modules.php` (planned). Module loader restricts paths to
`modules/` (canonicalized + prefix-checked) — see `app/docs/SECURITY.md` §2.

---

## 12. Testing — micro test runner (planned)

No PHPUnit (locked D7). One file, plain asserts, non-zero exit on failure:

```php
// tests/UserValidatorTest.php (planned)
test('rejects missing email', function () {
    $v = new Validator(['name' => 'Ada']);
    $out = $v->validate(['email' => 'required|email']);
    assert($out->fails());
    assert($out->errors()['email'] !== null);
});
```

Run: `bone test` (planned) or `php tests/run.php`. CI gate: exit code 0 =
green.

---

## 13. API design conventions (planned)

- **Resource naming**: plural, lowercase, kebab — `/v1/users`,
  `/v1/order-items`. Nouns only; verbs live in HTTP methods.
- **Versioning**: path prefix `/v1`. Breaking change → new prefix; run
  `/v1` + `/v2` side by side during migration.
- **Idempotency**: `GET`, `PUT`, `DELETE` safe to retry; `POST` non-idempotent
  by default — clients may send `Idempotency-Key` (planned) for critical
  creates.
- **Pagination**: `?page=1&per_page=50` (default 50, max 100). Response
  envelope: `{ "data": [...], "meta": { "page": 1, "per_page": 50,
  "total": 1234 } }`.
- **Filtering**: `?status=active&created_after=2026-01-01` — filters are
  query params on the collection; never in the path.
- **Standard status codes**: `200` read, `201` created (+`Location`),
  `204` deleted, `400` malformed, `401`/`403` authz, `404` missing,
  `422` validation, `429` throttled, `500` unexpected (envelope, §10).

---

Everything above is a planned contract until the matching phase in
`app/docs/PLAN.md` is marked done. Verify against `app/docs/REPORT.md`
before citing any of it as implemented.

Last synced: 2026-11-14 — project root moved to app/; decisions from user Q&A locked.
