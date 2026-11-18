# Changelog — BoneRADF

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/).
Versions follow [Semantic Versioning](https://semver.org/).

## Conventions

- **Versioning**: semver. `MAJOR` breaks public API, `MINOR` adds features,
  `PATCH` fixes. Pre-1.0 work is tracked as `1.0.0-dev`; the `-dev` label
  denotes unreleased, in-progress state.
- **Added**: new features, modules, documentation files.
- **Changed**: changes to existing behavior or public API.
- **Deprecated**: soon-to-be-removed features; replacement noted inline.
- **Removed**: features removed in this release.
- **Fixed**: bug fixes.
- **Security**: vulnerability fixes and hardening — always accompanied by a
  note on impact and required user action, if any.
- **Rule**: every commit that changes code must add a line to this
  changelog under the current unreleased version. Docs-only commits may be
  grouped under a single `Added` entry per docs batch.
- **Unreleased section**: entries accumulate under `[1.0.0-dev]` until the
  first release; on tag, retitle to `[1.0.0] — YYYY-MM-DD` and open a new
  empty `[Unreleased]` section.
- **Ordering**: newest version first; within a version, sections in the
  canonical order Added → Changed → Deprecated → Removed → Fixed → Security.

## [Unreleased]

### Added

- **Composition root moved to `src/Kernel/Boot.php`**: static `Boot::init()`
  registers the PSR-4 autoloader (single source of truth — replaces
  `src/Kernel/autoload.php`, now removed), builds Router / Pipeline /
  Container, discovers + boots modules, merges system + module route aliases,
  and returns a per-request `Kernel`. `Boot::withoutModules()` for the
  no-DB test harness; `Boot::routes()` + `Boot::registerAutoloader()` for
  `bin/bone`. `public/index.php` is now 3 lines (require → `init()` →
  `handle()` → `emit()`). `public/test.php` (gitignored scratch) drives the
  same chain through `.config` / `.supports` for bootstrap testing.
- **`src/Manifest/{Routing}.php`**: system-level default route
  aliases (`GET /`, `GET /health`). Modules are auto-discovered from
  `modules/<Name>/Config/modules.php` (no central list — growth is a folder
  per module, zero core edits); activation is the module's own
  `Config/routing.php` (≥1 route = booted, empty = inert).
- **Module manifests + declarative routes**: each module declares
  `modules/<Name>/Config/modules.php` (class, `depends_on`) and
  `modules/<Name>/Config/routing.php` (`'METHOD /path/{param:type}' =>
  [Controller::class, 'method']`). `Boot` scans `modules/`, filters to
  active modules (non-empty `routing.php`), topologically sorts by
  `depends_on` (fail-closed on cycle / unknown or inactive dep / missing
  class), and fails boot on duplicate aliases across system + module
  sources. Handlers resolve from the container when bound, else `new`.
- **Typed route segments** (`{id:int}` / `{id:num}` / `{id:slug}` /
  `{id:uuid}` / `{id:any}` / custom `{id:/regex/}`) in `Route::match()`;
  legacy `:param` still matched as untyped. `/api/resources/{id:int}` now
  404s on `/api/resources/abc`.
- **Controllers**: `Controller\DefaultController` (welcome + health),
  `Modules\Auth\AuthController` (login, refresh, csrfToken, preflight),
  `Modules\Resources\ResourceController` (list/show/create/update/delete +
  preflight) — route bodies moved out of `AuthModule` / `ResourcesModule`,
  which now only wire container bindings.
- `.config` / `.supports` are tracked (bootstrap contract) with a
  `!defined('basepath')` direct-access guard (404 + exit).
- **Docs + config batch**: `ARCHITECTURE.md` (§1 diagram, §3 boot steps, §5
  module layout + declarative routes + Contracts/Controller/Manifest
  conventions), `PLAN.md` (actual-state note, P6.1 row), `PRD.md` +
  `API_GUIDE.md` (`Boneradf\Contracts\Module`), `REPORT.md` (composition-root
  row, module-system row), `CLAUDE.md` (directory layout), `.gitignore`
  (`/public/test.php` scratch), `.vscode/settings.json` (`.config` → php).
- **Active-record Model layer (in-tree, no raw SQL)**: abstract
  `src/Database/Model.php` + `src/Database/Relation.php` — chain
  (select/where/whereIn/orderBy/limit/offset/with) + terminals
  (get/first/find/insert/update/delete/count) over `QueryBuilder`
  (prepared statements only, rows stay arrays). Relations: `hasOne`,
  `hasMany`, `belongsTo`, `belongsToMany` — eager `with()` loads each with
  one bulk `IN(...)` query (N+1-guarded; unknown relation →
  RuntimeException). Explicit decision 2026-11-17: D6 stays "no raw query
  API"; AR sits on top of the builder. ponytail: no dot-path eager, lazy
  proxies, or morphs (upgrade path in the class docblock).
- **System-level DB factory** `src/Database/Database.php` —
  `Database::fromEnv()` reads `BONERADF_DB_DSN/USER/PASS` once (env >
  framework defaults, keys unchanged) and memoizes one PDO per process.
- **Full-MVC module layout**: `modules/<Name>/{Controllers,Models,Services}/`
  with PSR-4 namespaces `Modules\<Name>\<Layer>`; `{Name}Module.php` stays
  at the module root as bootstrap; `Config/` + `seed.php` unchanged.
  Routing manifests import the new controller namespaces.
- **Module models**: `Modules\Auth\Models\UserModel` (`findByUsername` —
  the only `users` query) and `Modules\Resources\Models\ResourceModel`
  (`listAll`); services hold business logic with zero SQL.
- **`tests/p7.php`** — 26/26: factory memoization, CRUD chains + terminal
  state reset, all four relation types (eager arrays, orphan → null, pivot
  belongsToMany), prepare-count N+1 guard, fail-loud unknown relation.
- **Module auto-discovery + routing-based activation**: `src/Manifest/Modules.php`
  deleted (no central module whitelist). `Boot` scans
  `modules/*/Config/modules.php` — a module is *known* from its folder alone,
  adding a module requires zero core edits. Activation is explicit and
  module-owned: `Config/routing.php` with ≥1 route = active (routes merged +
  `boot()` called); empty array = inert (never booted, fail-closed). A module
  `depends_on` an inactive module → boot fails. `bin/bone modules` scans
  `modules/` and prints active/inactive + route count.
- **`tests/p8.php`** — 9/9: discovery of both modules, Auth + Resources
  active with their route counts, inert empty-`routing.php` module discovered
  but not booted, `bone modules` status/count output; full suite p0–p8 201/201.
- **Docs sync for the P9 batch**: `ARCHITECTURE.md` §5 (two-layer activation
  wording, autoloader mapping), `PLAN.md` (P9.0–P9.2 rows), `REPORT.md`
  (row 13, decision 21, checklist re-verified 2026-11-17), `CLAUDE.md`
  (directory layout).
- **HTML view responses (public landing)**: `Response::html(string)` +
  `Response::view(name)` — opt-in `text/html` responses; views live in
  `src/Views/<name>.php` (zero data, plain output; path traversal
  guarded by a name pattern check). Amandemen scope D2 ("no views/HTML"):
  headless default preserved — view rendering is opt-in per route, JSON
  remains the default envelope. Sample view `src/Views/landing.php`.
  `tests/p0.php` +6 (html pass-through, view render, traversal reject,
  missing view); suite p0–p8 207/207.
- **Codebase reformat sweep**: 33 `src/` files normalized to the project
  brace style (`)` on its own line, house helpers `call_user_func`/`define`
  captured, docblock `@return` shapes clarified). No behavior change — suite
  p0–p8 still 207/207.
- **Review + hardening plan (P9.5)**: `docs/REVIEW-2026-11-17-v2.md`
  (world-class review, module-developer POV, 4 factual corrections to the
  prior draft) + `docs/PLAN-P9.5.md` (P9.5.1–P9.5.8 task plan, G1–G8 gate,
  user decisions locked 2026-11-17: generator now, Config wired + DB
  fail-closed, DB-availability activation, `find()` queue-respecting, RBAC
  wired, minor cleanup, exclude `modules/Test`).
- **`modules/Test/` + `public/assets/css/style.css`** — new WIP module
  (`Manifest.php` bootstrap, `Controllers/TestController`) + landing
  stylesheet.
- **P9.5 hardening (review-driven)** — Config wired into Boot, DB
  fail-closed, DB-availability activation, `find()` queue-respecting, route
  metachar escape, pipeline-per-request, RBAC wiring semantics, minor
  cleanup; see `PLAN-P9.5.md` + `REVIEW-2026-11-17-v2.md`.
- **Dot-notated `.env` + named DB connections** (user-approved 2026-11-18):
  flat `key=value` file with dot keys — `app.key`,
  `database.<name>.{driver,host,port,name,charset,user,pass}` (ships
  `default` + `test`), `database.env` runtime selector. `Config::loadSystem()`
  parses the file directly; real env (`BONERADF_DB_ENV`,
  `DATABASE_<NAME>_*`, `APP_KEY`, …) always wins. Adding a connection = a
  `.env` block, zero code change. `.env` gitignored; `.env.sample` tracked.
- **Live smoke verified 2026-11-18**: `GET /` 200 (landing HTML),
  `GET /health` 200 JSON, `POST /api/auth/login` 200 (JWT issued);
  suite p0–p8 212/212.

### Changed

- **`public/index.php` boot-failure `error_log` fixed** — was
  `error_log($th)` (TypeError: needs string); now logs
  `message in file:line`; envelope stays JSON `BOOT_FAILED`.
- **`Boot::loadDotEnv()` removed** — the flat `BONERADF_*` putenv loader
  collided with dot-notation keys; superseded by `Config::parseDotEnv()`.
- **`AuthModule` reads `app.key` / `cors.origins`** from Config (dot keys).
- **`tests/env.php` slimmed** — selects `BONERADF_DB_ENV=test`; all
  values come from `.env` (single source).

- **`public/index.php` adopts the `test.php` bootstrap structure verbatim**:
  captured built-ins (`$call` / `$lock_as` / `$is_locked_as`), `basepath` +
  `sep` constants, the `$config` namespace map, `@require` of `.config`
  (one level up, guarded by `!defined('basepath')`), then
  `$bootkey::init()->handle(Request::fromGlobals())->emit()`. Boot failures
  log the raw `Throwable` via `error_log()` and die with the generic
  envelope; the raw HTML dump stays in a comment for dev. `test.php`
  remains a gitignored scratch copy of the same structure.
- **`src/Module/Module.php` → `src/Contracts/Module.php`** (namespace
  `Boneradf\Contracts`) — `Contracts/` holds framework interfaces/traits.
  References updated in `AuthModule`, `ResourcesModule`, `Boot`, `API_GUIDE`.
- **All remaining interfaces moved to `src/Contracts/`**: `Logger`
  (was `src/Logger/`), `CacheDriver` (was `src/Cache/`), `Middleware`
  (was `src/Middleware/`) — namespace `Boneradf\Contracts`. Implementations
  stay in their domain folders (`FileLogger`, `MemoryDriver`/`FileDriver`,
  `Pipeline`, security guards). Callers updated: `Kernel`, `Router`,
  `Pipeline`, `Cache`, drivers, `Security/*`, `tests/p1`.
- **`Manifest/DefaultController` → `src/Controller/DefaultController.php`**
  (namespace `Boneradf\Controller`) — `src/Manifest/` is data-only
  (`Routing.php`, `Modules.php`); handlers live in `src/Controller/`.
- **`bin/bone routes`** lists the real merged route table via
  `Boot::routes()` (columns: METHOD / PATTERN / ALIAS); **`bin/bone modules`**
  scans `modules/*/Config/{modules,routing}.php` and prints
  active/inactive + route count instead of a central manifest.
- Route handlers are `[Controller::class, 'method']` (array callable) instead
  of closures; `Route` now carries the source alias as its name.
- Entry points (`bin/bone`, `tests/p0–p6`, `modules/*/seed.php`) require
  `src/Kernel/Boot.php`; unit tests call `Boot::withoutModules()` so they do
  not need `pdo_mysql`.
- `.config` boot guard fixed: `!isset($config['boot']) && …` (never fired) →
  `class_exists($bootkey, false)` after `require_once`; the dead
  `$config['boot'] = true` self-mutation in `Boot.php` is gone.
- **Module `boot()` = wiring only**: `AuthModule`/`ResourcesModule` no longer
  read `BONERADF_DB_*` or construct PDO — one `Database::fromEnv()` call
  each; `LoginService` takes `UserModel`, `ResourceService` takes
  `ResourceModel`. DB settings exist at system level only.
- **Class moves + namespace renames (full-MVC)**:
  `Modules\Auth\AuthController` → `Modules\Auth\Controllers\AuthController`,
  `Modules\Auth\LoginService` → `Modules\Auth\Services\LoginService`,
  `Modules\Resources\ResourceController` → `Modules\Resources\Controllers\ResourceController`,
  `Modules\Resources\ResourceService` → `Modules\Resources\Services\ResourceService`;
  callers updated (`routing.php` manifests, both `{Name}Module` classes,
  `tests/p6.php`). The `modules/*/src/` concept is retired.

### Removed

- **Hardcoded DB dev fallbacks** (`root`/`root`/`local`/port `10028`) in
  `Database::fromEnv()` — removed; fail-closed, and `tests/env.php` supplies
  the dev DSN for tests.
- `src/Manifest/Modules.php` — the central active-module whitelist. Modules
  are now auto-discovered from `modules/*/Config/modules.php`; activation is
  each module's own `Config/routing.php` (see Added).
- `src/Kernel/autoload.php` — replaced by `Boot::autoload()` (one source of
  truth, class-based). All 11 entry points updated in the same change.

### Fixed

- **`CLAUDE.md` markdown formatting**: removed a stray duplicate closing
  code fence after the `src/ + modules/ layout` block that caused the rest
  of the file to render as a code block.

- **Duplicate per-request PDO connections**: `AuthModule` and
  `ResourcesModule` each opened their own connection (and `AuthModule` bound
  an `instance('db')` nothing ever read). Now one shared memoized PDO via
  `Database::fromEnv()`; the dead `db` binding is removed. Verified: module
  code contains zero `BONERADF_DB_*` reads; suite p0–p7 192/192.

- **`bone routes` / `bone modules` fatal (`require /.supports`)**: CLI calls
  `Boot::registerAutoloader()` directly without `init()`, so `Boot::$basepath`
  was still `''` when `autoload()` ran. `autoload()` now derives `basepath`
  from `__DIR__` on first use — every autoload path is safe without booting
  the kernel. Failing-before (fatal) → passing-after: 14 routes, 2 modules,
  p0–p6 166/166.

- **Module system (ARCHITECTURE §5, planned → implemented)**: `src/Module/Module.php`
  (`boot(Container, Router, Pipeline)` interface) + module-aware autoloader
  (`Boneradf\*` → `src/`, `Modules\*` → `modules/<Name>/src/` and
  `modules/<Name>/`). Composition root `public/index.php` now registers
  modules explicitly (no auto-discovery, no global registry).
- **Auth module** (`modules/Auth/`): `AuthModule` (self-registers routes +
  global CORS), `src/LoginService.php` (validate → query `users` →
  `password_verify` → issue access + refresh JWT, timing-uniform dummy hash,
  fail-closed on DB error), `seed.php` (idempotent `users` table + sample user
  `boneradf`/`secret123` in DB `local`). Routes: `POST /api/auth/login`,
  `POST /api/auth/refresh`, plus `OPTIONS` preflights, and the global
  `GET /api/csrf-token` boot endpoint. `tests/p6.php` — 16 assertions.
- **Resources module** (`modules/Resources/`): `ResourcesModule` + `src/ResourceService.php`
  + `seed.php` (idempotent `resources` table + Alpha/Beta/Gamma rows). CRUD:
  `GET /api/resources` (list), `POST /api/resources`, `GET/PUT/DELETE
  /api/resources/:id`. Name validated (`required|string|1..191`) → 422 with
  field errors; unknown id → 404; delete → 204.
- `src/Security/CorsMiddleware.php` (origin-allowlisted, preflight-aware,
  global pipeline).

### Changed

- **`Router::group` default URL prefix** — `group($prefix)` now prepends
  `Router::DEFAULT_URL_PREFIX` (`/api`) so a module registers
  `group('/auth', …)` → `/api/auth/…` without writing `/api` itself. Custom
  or no prefix via explicit `$urlPrefix` arg: `group('/health', …, '')` →
  `/health/…`. Direct `add()` routes (e.g. `/health`, `/hello` in
  `public/index.php`) are unaffected — they resolve as written. `AuthModule`
  now calls `group('/auth')` instead of `group('/api/auth')`. Public URLs
  unchanged; the change is that module-scoped prefixes no longer carry the
  API segment.
- `AuthModule` registers endpoints via `Router::group('/auth', …)` (relative
  paths) instead of flat `/api/auth/*` registrations. Public URLs unchanged.
- `AuthGuard::issue()` now stamps `type: 'access'`; new
  `AuthGuard::issueRefresh()` stamps `type: 'refresh'` with the 7d refresh TTL
  (D10). `LoginService::refresh()` only accepts `type: 'refresh'` tokens and
  returns a fresh access token — no refresh-token rotation (documented
  ponytail; rotate when logout/revocation lands).
- Autoloader extended for the `Modules\` namespace (PSR-4 per module).

### Security

- `CorsMiddleware` is origin-allowlisted and fails closed (no CORS headers for
  unknown origins); preflight `OPTIONS` routes return 204. `users` table uses
  a prepared statement keyed on `username`; passwords stored via
  `password_hash()` (Bcrypt/Pbkdf2 via `PASSWORD_DEFAULT`).

## [1.0.0] — 2026-11-16

### Added

- Docs scaffold: `PRD.md`, `ARCHITECTURE.md`, `SECURITY.md`, `API_GUIDE.md`,
  `DEPLOY.md`, `PLAN.md`, `CHANGELOG.md`, `REPORT.md`.
- Locked decisions D9–D10: memory target (<25 MB RSS), config precedence
  (env > file > defaults), router param syntax (`:id`), validation status
  (422), rate-limit fail mode (fail-closed), cache v1 (in-memory + file),
  PSR strategy (native, PSR-shaped), DI (explicit bindings), `bone` v1
  subcommands (routes, modules, serve), JWT defaults (HS256, 1h + 7d refresh).
- Added `app/docs/RULES.md` (conventions), `app/docs/CONFIG.md` (local env,
  never commit), `app/docs/CONFIG.sample.md` (template).
- Updated `app/CLAUDE.md`: custom commands (`>branch`, `>push`, `>sync`,
  `>track`, `>audit`, `>release`), branching model (main/dev/feat/fix/
  hotfix/patch), debugging + scope-protection rules, pointer to RULES.md.
- `app/CLAUDE.md` locked-files section now lists `sql/` and `.envrc`
  (legacy LocalWP dump + env) alongside `conf/` and `logs/`.
- `app/CLAUDE.md` documents `sample_frontdev/` as a gitignored frontend test
  scaffold — separate git repo, target for frontend testing against the
  headless framework; framework work never modifies it.
- `>branch` command now guards against uncommitted/unpushed work on the
  current branch: runs `>push` first if `git status` or `git log @{u}..`
  is non-empty, before branching from the current branch.
- `README.md` added (project description placeholder).
- P0 kernel: `src/Kernel/autoload.php` (custom PSR-4 autoloader for
  `Boneradf\`, no composer), `src/Kernel/Request.php` (immutable HTTP
  request), `src/Kernel/Response.php` (JSON response + uniform error
  envelope), `src/Kernel/Kernel.php` (boot pipeline, unhandled exception
  → 500 JSON), `public/index.php` (front controller).
- `tests/p0.php` — P0 verification: 20 assertions (autoloader, Request,
  Response, Kernel 404/500 contract).
- P1 routing + middleware: `src/Router/Router.php` (`:param` matching, route
  enumeration, groups), `src/Router/Route.php` (pattern matching, param
  extraction, handler invocation), `src/Middleware/Middleware.php`
  (PSR-15-shaped interface), `src/Middleware/Pipeline.php` (ordered pipeline,
  `next`, abort, error bubbling), `src/Kernel/Kernel.php` (wired router +
  pipeline; 404 / 405 / 500 JSON envelopes).
- `tests/p1.php` — P1 verification: 20 assertions (router matching, groups,
  enumeration, middleware ordering, abort, kernel 404/405/500).
- P2 config + DI + logging: `src/Config/Config.php` (typed, env > file >
  defaults precedence, required-key enforcement), `src/Container/Container.php`
  (explicit bindings only, circular detection, request-scoped caching),
  `src/Logger/Logger.php` (PSR-3-shaped interface), `src/Logger/FileLogger.php`
  (structured JSON-line output, secret redaction), `src/Kernel/Kernel.php`
  (now accepts optional Config / Container / Logger; logs unhandled exceptions).
- `tests/p2.php` — P2 verification: 27 assertions (config precedence, type
  coercion, required keys; container bindings, factories, circular detection;
  logger JSON shape, redaction; kernel wiring).
- P3 database + validation: `src/Database/QueryBuilder.php` (PDO prepared
  statements only, no raw SQL API, identifier allowlist, SQLi-safe),
  `src/Database/ConnectionFactory.php` (DSN-based PDO factory),
  `src/Validator/Validator.php` (strict rule engine: required, types, min/max,
  pattern, in, callback, email, date), `src/Validator/ValidationResult.php`
  (per-field error container).
- `tests/p3.php` — P3 verification: 25 assertions (query builder SELECT/
  UPDATE/DELETE, SQLi probe bound as data, identifier validation, WHERE IN;
  validator rules, pattern, nullable, in, min/max).
- P4 security modules: `src/Security/SecurityHeaders.php` (5 headers ON by
  default, overridable), `src/Security/CsrfGuard.php` (HMAC-signed token,
  state-changing methods only, 403 on fail), `src/Security/AuthGuard.php`
  (JWT HS256, access+refresh TTLs, fail-closed), `src/Security/RbacGuard.php`
  (deny-by-default role/permission gate), `src/Security/RateLimiter.php`
  (sliding window, fail-closed on storage error, 429 on exceed),
  `src/Security/SignedCookie.php` (HMAC-signed cookie, constant-time verify).
- `src/Kernel/Request.php` — added `attribute()`, `user()`, `withAttribute()`,
  `withUser()` (immutable; used by AuthGuard to attach identity).
- `tests/p4.php` — P4 verification: 36 assertions (all five security headers,
  CSRF pass/fail, JWT issue/verify/tamper/expired, RBAC deny/allow, rate
  limiter 429 + per-IP isolation + reset, signed cookie sign/verify/tamper).
- P5 cache + test runner + CLI + deploy: `src/Cache/Cache.php` (facade),
  `src/Cache/CacheDriver.php` (interface), `src/Cache/MemoryDriver.php`
  (per-request), `src/Cache/FileDriver.php` (filesystem, sha256-keyed files),
  `src/Test/Runner.php` (micro runner: `test()`, `assert_true()`, `assert_eq()`,
  `assert_null()`, `assert_contains()`, `test_run()` — non-zero exit on failure;
  `assert()` is reserved, hence `assert_true()`), `bin/bone` (CLI: routes,
  modules, serve), `Dockerfile` (dev-only php:8.1-cli recipe).
- `tests/p5.php` — P5 verification: 21 assertions (memory/file cache
  round-trip, TTL, delete, clear; facade; runner self-test incl. throwing
  assertions).
- Milestones M1–M3 marked ✅ Done in PLAN (149/149 tests pass across P0–P5;
  v1.0.0 candidate).
- `.gitignore`: added `/sample_frontdev` (frontend test scaffold, separate
  git repo, not part of framework).

### Security

- P4 delivered the defense-by-default modules (SecurityHeaders, CsrfGuard,
  AuthGuard, RbacGuard, RateLimiter, SignedCookie). Headers ON, CSRF
  enforced on state-changing methods, JWT HS256 with exp/nbf + fail-closed,
  RBAC deny-by-default, rate-limit fail-closed with 429 + Retry-After body,
  HMAC-signed cookies verified with `hash_equals`.
- P5 hardening: `FileDriver` keys files by `hash('sha256', $key)` (no
  user-controlled path segments); `bin/bone serve` no longer spawns a
  server process — prints the command instead (secure-coding rule: no
  `proc_open`/`exec` in project code).

---

## [Unreleased] — In progress

### Added

- `public/.htaccess` — front-controller rewrite (non-file paths → `index.php`)
  so clean paths like `/hello` resolve via the router under Apache/LocalWP.
  Snippet was already documented in `DEPLOY.md` §5; now active in dev.

### Changed

### Deprecated

### Removed

### Fixed

### Security

---

**Last synced: 2026-11-16 — v1.0.0 released: tag `v1.0.0` → merge commit `b77d382` (PR #1 dev→main).**
