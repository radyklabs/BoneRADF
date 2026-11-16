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
  `POST /api/auth/refresh`, plus `OPTIONS` preflights. `tests/p6.php` — 16
  assertions.
- `src/Security/CorsMiddleware.php` (origin-allowlisted, preflight-aware,
  global pipeline).

### Changed

- `AuthModule` now registers its endpoints via `Router::group('/api/auth', …)`
  (module-scoped relative paths: `/login`, `/refresh`, plus `OPTIONS`
  preflights) instead of flat `/api/auth/*` paths. Public URLs are unchanged
  (`/api/auth/login`, `/api/auth/refresh`); the change is the registration
  mechanism — module routes are now scoped to the module's prefix.
- `public/index.php` no longer wires login inline — it is now a pure
  composition root that boots the `AuthModule`. `LoginService` moved from
  `src/Auth/` to `modules/Auth/src/` (development layer, not core).
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
