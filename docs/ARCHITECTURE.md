# BoneRADF — Architecture

Decision-record style. Project root is `app/`. Paths below are relative to `app/` unless noted.

---

## 1. System overview & request lifecycle

BoneRADF is a headless JSON/REST microservice framework: one install = one microservice. No views, no HTML rendering. PHP 8.1+, zero runtime dependencies (custom PSR-4 autoloader, no Composer required at runtime).

```
HTTP request
    |
    v
public/index.php            (front controller: 3 lines, delegates to Boot)
    |
    v
Kernel                      (boot pipeline, see §3)
    |
    v
Middleware pipeline         (ordered, outer -> inner)
    |   SecurityHeaders, CSRF, Auth, RBAC, RateLimit (defense-by-default, ON)
    |   each: pre-process -> next() -> post-process
    v
Router                      (method + pattern match, param extraction)
    |
    v
Controller / Handler        (application logic; resolves deps from container)
    |
    v
Response (JSON)             (uniform envelope, see §9)
    |
    v
Middleware unwind           (post-process, inner -> outer; errors bubble up)
    |
    v
HTTP response              (status + JSON body + headers)
```

Unhandled exceptions at any stage are caught by the Kernel, converted to the uniform JSON error envelope (§9), logged (§10), and returned — never leaked as stack traces in production.

---

## 2. Process model

One PHP-FPM worker = one service instance. There is no multi-service orchestrator inside the framework; each install is a single, self-contained microservice.

Why one-service-per-install wins for this project:

- **Isolation** — config, modules, cache files, and logs are private to the install. One compromised service does not share state with another. No shared global registry between services (§7: no `global()` leakage).
- **Scale** — scale is the FPM pool. Add workers, add capacity. Horizontal scaling is a deployment concern (multiple identical installs behind a load balancer), not a framework concern.
- **Deploy** — a deploy is a directory swap (plus opcache reset/revalidate). No inter-service orchestration, no job queue in v1 (locked decision: no queue/worker system in v1).
- **Simplicity** — matches the "one install = one microservice" contract. Multi-service compositions talk over HTTP/JSON like any external client.

Trade-off accepted: no built-in service discovery or inter-service RPC. Services find each other via configuration (TBD: service registry is out of scope for v1).

---

## 3. Kernel boot sequence

Precise order. Steps are numbered; each must complete before the next.

1. **Autoloader** — `src/Kernel/Boot.php` registers the custom PSR-4 autoloader for root namespace `Boneradf\` (and module namespaces, step 5). No Composer at runtime. `Boot::init()` is static and boot-scope only; it returns an instance `Kernel` so no request state is held across requests.
2. **Config load** — read typed, env-aware config (§7): `.env` or JSON, environment overrides applied. Fail fast on missing required keys.
3. **Container** — instantiate the light DI container (§4), register core bindings (Config, Logger, Router, PDO factory, etc.).
4. **Logging** — create the structured logger (§10) and bind it; from here boot failures are logged, not just fatal-echoed.
5. **Middleware registration** — register core middleware in fixed defense-by-default order (SecurityHeaders → CSRF → Auth → RBAC → RateLimit), then module-registered middleware (§5).
6. **Router build** — collect routes from core + modules (§6), compile route groups/prefixes, build the match table. Done once per boot, not per request match.
7. **Dispatch** — match request method + path; run the middleware pipeline; invoke the controller/handler with route params + container-resolved dependencies.
8. **Response render** — serialize the handler result to the uniform JSON envelope; unwind middleware; emit HTTP response.

Failure at steps 1–4 is a boot failure: JSON error envelope (if logging is up, log first) + non-2xx exit. Failure at steps 5–8 is a request failure: same envelope, request continues to serve other requests.

---

## 4. DI container design

Light, first-class, explicit. Deliberately not a full container.

- **Explicit binding only** — `bind(id, factory)` / `instance(id, object)`. No auto-wiring by reflection magic: no constructor-graph scanning, no `new $class` inference. What is not bound is not resolvable; missing bindings throw a typed exception at resolve time.
- **Why no reflection auto-wiring** — zero-dependency constraint (no doctrine/annotations), predictable boot cost, and explicit wiring makes dependencies greppable in review. Cost accepted: more boilerplate per service; acceptable because module contracts (§5) keep bindings localized to `boot()`.
- **Scoped per-request** — the container used during dispatch is request-scoped: entries resolved for one request are discarded at request end. Cross-request state lives in config, cache module, or PDO — never in the container. Singleton-per-worker bindings (e.g., compiled route table) are registered at boot into a boot-scope that the request scope inherits read-only (exact mechanism: planned).
- **Lazy** — factories run on first resolve, not at bind time. Binding is cheap; constructing is deferred.

---

## 5. Module contract

A BoneRADF module is a PSR-4 package directory under `modules/` that
self-registers via one interface. **Implemented post-v1.0** (was planned at the
v1.0 candidate).

```
modules/
  <ModuleName>/
    Config/
      modules.php    (manifest: class, depends_on)
      routing.php    (aliases: 'METHOD /path/{param:type}' => [Controller::class, 'method'])
    Controllers/      (namespace Modules\<ModuleName>\Controllers — HTTP only)
    Models/           (namespace Modules\<ModuleName>\Models — table access via Boneradf\Database\Model)
    Services/         (namespace Modules\<ModuleName>\Services — business rules, zero SQL)
    <ModuleName>Module.php  (module bootstrap class, at module root — wiring only)
    <module>.php      (optional: seed / migration / bootstrap scripts)
```

Autoloading: `Boot::autoload()` maps `Modules\<Name>\<Rest>` →
`modules/<Name>/<Rest>.php` (nested dirs included — full-MVC layout; the
legacy `modules/<Name>/src/` concept is retired). No Composer.

Framework-side layout conventions:

```
src/Contracts/     interfaces + traits (Module, Middleware, Logger, CacheDriver)
src/Controller/    core controllers (DefaultController)
src/Manifest/      data-only manifests (system route aliases)
```

Minimal contract (implemented):

```
interface Module  // src/Contracts/Module.php
{
    public function name(): string;
    public function boot(Container $container, Router $router, Pipeline $pipeline): void;
}
```

A module may, inside `boot()`:

- bind services into the container (explicit bindings only, §4);
- register middleware onto the pipeline (§7 order: core defense middleware is
  registered first and cannot be reordered away by modules; modules append);
- register config keys with defaults.

Data access is system-level: `Database::fromEnv()`
(`src/Database/Database.php`) reads `BONERADF_DB_*` once and memoizes one
shared PDO per process. `boot()` wires `new XModel(Database::fromEnv())`
into services; table access lives exclusively in `Models/` as subclasses of
`Boneradf\Database\Model` (active-record over the query builder — prepared
statements only, rows stay arrays, eager relations via `with()`).
`Services/` must contain zero SQL; `Controllers/` contain HTTP only.

Routes are **declarative**, not registered in `boot()`: they live in
`modules/<Name>/Config/routing.php` as `'METHOD /path/{param:type}' =>
[Controller::class, 'method']` (array callable — no closures, no string
resolver). `Boot` merges system aliases (`src/Manifest/Routing.php`) with
each active module's aliases and fails boot on duplicates.

A module must NOT: write to globals, emit output during boot, or mutate
another module's bindings. Installable security modules (SecurityHeaders,
CSRF, Auth JWT+cookie, RBAC, RateLimit) ship as modules and are ON by default
— they follow the same contract; they are simply pre-registered by the Kernel.

**Composition root** (`src/Kernel/Boot.php`) is the single place modules are
discovered, resolved, and booted. Two layers:

- **Auto-discovery (system)**: every `modules/<Name>/Config/modules.php`
  makes the module *known* — no central registry, adding a module requires
  zero core edits. The autoloader resolves `Modules\*` classes on demand.
- **Activation (explicit, by the module itself)**: a discovered module is
  booted only when its `Config/routing.php` declares ≥1 route; an empty file
  keeps it inert (fail-closed). Boot order is topological over each
  manifest's `depends_on`; cycles, unknown/inactive dependencies, missing
  classes, and duplicate route aliases all fail boot.

This preserves §7's "no `global()` leakage" rule. `public/index.php` stays
the front controller and only calls
`Boot::init()->handle(Request::fromGlobals())->emit()`.

The first module is `modules/Auth/` (login + refresh + CORS + `users` seed).

---

## 6. Routing

- **Match key**: HTTP method + path pattern. e.g. `GET /users/{id:int}`.
- **Parameter extraction**: `{id}` segments become route params passed to the handler. Typed placeholders constrain the match at the router level: `{id:int}` → `[0-9]+`, `{id:num}`, `{id:slug}`, `{id:uuid}`, `{id:any}`, or a custom regex `{id:/…/}`. A non-matching type does not match the route at all (404, not a 422). Final coercion/validation is still the handler/validator's job — strict Validator is a core component. Legacy `:id` syntax is matched as untyped.
- **Route groups**: shared prefix + shared middleware + shared handlers namespace, composed additively:

```
router->group('/api/v1', [AuthMiddleware::class], function (Router $r) {
    $r->get('/users/{id}', [UserController::class, 'show']);
    $r->post('/users',     [UserController::class, 'create']);
});
```

- **Versioning**: via URL prefix (`/api/v1`, `/api/v2`) inside route groups. No header-based versioning in v1. Multiple versions can coexist as parallel groups during migration.
- **Route table** is built once at boot (step 6) and is read-only during dispatch. Match is a static structure, not a linear scan over closures where avoidable (exact matching structure: planned — first-match-wins over an ordered table is the baseline).

Unknown method vs unknown path are distinguished in the error envelope (405 vs 404) — planned detail.

---

## 7. Middleware pipeline

PSR-15-style (request handler / middleware shape) without claiming formal PSR package compliance (zero runtime deps).

- **Ordered**: registration order = execution order on the way in; reverse on the way out. Core security middleware is registered first so it wraps everything.
- **Contract** (planned): `process(Request $req, Handler $next): Response`. `Handler::handle($req)` invokes the next stage.
- **`next()`**: advance inward. Returning a `Response` without calling `next()` short-circuits the pipeline (e.g., RateLimit returning 429, Auth returning 401).
- **`abort(Response $r)`** (or returning a response — exact API: TBD): explicit short-circuit; no further inner stages run; unwind still processes outer middleware.
- **Error bubbling**: an exception thrown by an inner stage or the handler propagates outward through middleware (so outer middleware can log/translate), until the Kernel catch converts it to the uniform error envelope (§9). Middleware that must convert errors does so with typed exceptions, never string matching:

```
HttpException(404) | ValidationException(errors) | AuthenticationException |
AuthorizationException | RateLimitException | ... (typed, planned)
```

---

## 8. Query builder

PDO-based, prepared statements ONLY. No raw-SQL escape hatch in core.

- **API-level enforcement**: the builder's only query path is `execute($sql, $params)`. Values never concatenate into `$sql`; callers hand over a parameter map, and the builder passes it through to PDO prepared execution. There is intentionally no `raw()` / `whereRaw()` in the core API.
- **Why**: SQL injection defense by construction. If the only way to reach the database requires a placeholder map, injection is not a code-review concern — it is structurally impossible through the public API. Trade-off accepted: exotic queries (database-specific hints, complex joins) are awkward or impossible in core; escape hatch is out of scope for v1 (if ever added, it must be a separate, loudly-named opt-in — not planned).
- **Fluent surface** (planned): `from()`, `select()`, `where()`, `orderBy()`, `limit()` — chainable, immutable-or-mutable (exact: TBD), terminal `execute()` / `first()` / `get()`.
- **Parameterized**: every value binds as a parameter; identifiers (table/column names) validated against an allowlist pattern at build time — not bound (PDO cannot bind identifiers).
- **Typed rows**: results return as typed rows (associative arrays with declared column expectations, or row objects — exact shape: planned). Fetch mode is fixed at the builder level, not caller-selectable per call.

Connections come from the container (PDO factory binding). Connection options, charset, and error mode (exceptions) are set once at factory time.

---

## 9. Config & error envelope

### Config

- **Typed** — keys have declared types (string/int/bool/…); loading coerces and validates; a type mismatch fails boot (step 2) with a clear message. No stringly-typed `get('key')` returning mixed surprises.
- **Env-aware** — environment selection (dev/staging/prod) drives defaults and strictness (e.g., debug details in envelope only when env allows).
- **Source** — per-service `.env` or JSON file. Precedence: environment variables > env file > defaults (exact precedence table: planned).
- **No `global()` leakage** — config is an injected object (container-bound), never a global function or superglobal. Code that needs config receives `Config`. This keeps installs isolated and tests free of global state.

### Error envelope

Uniform JSON error shape for every non-2xx response:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Human-readable summary.",
    "request_id": "01J...",
    "details": { "field": "reason" }
  }
}
```

- `code` — stable machine-readable identifier (SCREAMING_SNAKE).
- `message` — safe for clients; never contains stack traces, SQL, or file paths in production.
- `request_id` — correlates response ↔ log lines (§10).
- `details` — optional structured context (validation field errors, rate-limit retry-after, etc.).

Success responses use the project's success envelope (shape: planned; see API_GUIDE.md when published).

---

## 10. Logging

- **Structured JSON lines**, one object per line: `ts`, `level`, `msg`, `request_id`, plus context fields. Machine-parseable; greppable; ships anywhere.
- **Minimal LogInterface** — PSR-3-*style* method names (`debug/info/warning/error` with message + context array). We do NOT claim PSR-3 package compatibility (zero-dependency rule: no `psr/log` package). If a bridge is ever needed, it is an adapter, not a dependency.
- Logger is container-bound at boot (step 4) and request-correlated via `request_id` injected into context at dispatch start.
- Handler/file destination, rotation, and level thresholds are config-driven (planned).

---

## 11. Security architecture

Summary only — full detail in `app/docs/SECURITY.md`.

Defense-by-default: the security modules (SecurityHeaders, CSRF, Auth (JWT + cookie session), RBAC, RateLimit) are ON by default and registered in the outermost middleware positions so every route is covered unless explicitly (and audibly) exempted. Query builder forbids raw SQL at API level (§8). Config never leaks to globals (§9). Error envelopes never leak internals in production (§9).

---

## 12. Extension points

| Extension | Mechanism | Contract |
|-----------|-----------|----------|
| Modules | `modules/` PSR-4 dirs | `Module::boot()` (§5) |
| Middleware | pipeline registration | ordered `process()` / `next()` (§7) |
| Drivers | container bindings | replace a bound id (cache, storage — e.g., file vs redis-if-ext available); explicit binding swap, no driver registry magic |
| Routes | `Router` groups | method + pattern (§6) |

The container is the single composition root: extending the framework = binding something different, not subclassing the Kernel.

---

## 13. Performance notes

- **Zero-dep autoloader cost** — a simple PSR-4 prefix map lookup; no Composer classmap generation required at runtime. Classmap caching for hot paths is a possible optimization (planned, not required for v1).
- **Lazy binding** — container factories defer construction; unused services cost nothing per request.
- **Boot vs request split** — route table and core bindings built once per worker boot where the SAPI allows (planned: boot-time caching of compiled state; exact persistence mechanism TBD). Per-request work is: match, pipeline, handler, serialize.
- **opcache expectations** — production must run opcache with `opcache.enable=1` and revalidate settings appropriate to deploys (directory-swap deploys: `opcache.validate_timestamps=0` + explicit reset on deploy, or timestamp revalidation — operator's choice, documented in deployment notes: planned). With opcache on, the framework's PHP overhead per request is dominated by I/O (DB, file cache), not framework code.
- **Cache module** — in-memory + file backends always; redis backend only if `ext-redis` is available, otherwise silently skipped (locked decision).

---

## 14. Out of scope for v1 (locked)

- Queue/worker system.
- Views/HTML rendering.
- Composer as a runtime requirement.
- Multi-service orchestration / service discovery.
- Raw-SQL escape hatch in the query builder.

---

Last synced: 2026-11-17 — §5 auto-discovery + activation-via-routing (no central module registry; `routing.php` is the module's activation switch); suite p0–p8 201/201; project root at `app/`.
