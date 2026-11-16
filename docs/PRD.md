# PRD: BoneRADF — Super Nano Headless PHP Microservice Framework

**Status**: Draft (decisions locked)
**Author**: Product + Planning Lead
**Last Updated**: 2026-11-14
**Version**: 1.0-draft
**Source of truth**: Locked decisions from user Q&A (2026-11-14)

---

## 1. Vision & Problem Statement

PHP remains one of the most widely deployed runtimes on shared and containerized
infrastructure, yet building a small, secure JSON/REST microservice typically
means adopting a full framework with a large dependency tree, a composer-driven
deploy step, and many features (views, ORM, queues, bus systems) that a nano
service never uses.

**Problem**: Small API services in PHP carry disproportionate weight —
dependency management, framework config surface, and security features that are
opt-in and therefore often missed.

**Vision**: BoneRADF is a **super nano, headless, zero-dependency** PHP
microservice framework. Copy one folder, write a route, return JSON. Secure
behaviors are **on by default** (defense-by-default), not plugins you remember
to enable. Minimal requirement: **PHP 8.1+**.

Scope words (locked): easy deploy, familiar, light maintain, effortless use,
super secure, injection-hack-free, common structure, modular, fast response,
lightweight.

### Decision records (locked — source of truth)

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | Name/namespace `BoneRADF`, PSR-4 root namespace `Boneradf\` | Locked by user |
| D2 | Zero-dependency modularity; **no composer runtime requirement**; custom PSR-4 autoloader + built-in module registry; copy-folder deploy. `composer.json` may exist later **only** as a dev-time convenience | Easy deploy, lightweight, no runtime deps |
| D3 | Runtime shape: JSON/REST API only. No views, no HTML rendering. Router + middleware pipeline + light DI + PSR-15-style request/response | Headless; keeps core nano |
| D4 | Deploy model: one framework install per microservice; multiple services = multiple small projects sharing the same structure ("common structure") | Familiar, light maintain |
| D5 | Security: core minimal + installable security modules; core enforces at minimum PDO prepared-statement-only query builder (no raw query API in core), strict input validation, security headers (CSP/HSTS/X-Frame-Options/X-Content-Type-Options/Referrer-Policy), signed cookies; plus CSRF, rate-limit, auth (JWT + session), RBAC modules. Secure behaviors ON, not opt-in | Super secure, injection-hack-free |
| D6 | DB layer: built-in PDO query builder, prepared statements enforced; MySQL/MariaDB/SQLite/Postgres via DSN; **no raw-escape-hatch in core** | Injection-hack-free |
| D7 | Testing: lightweight built-in micro test runner; **no PHPUnit dependency** | Zero-dependency |
| D8 | CLI: minimal `bone` entry (route registration, module list, serve). No full worker/queue system; **no SSE, no background workers in v1** | Nano scope control |
| D9 | Memory footprint target: **< 25 MB RSS** for a booted kernel (no routes hit) under PHP 8.1 FPM + opcache; measure at P0 end, hold as NFR ceiling | Lightweight, fast response |
| D10 | Config precedence: **env > config file > framework defaults**; secrets always from env. Router param syntax: **`:id`** (colon, Express/Rails style). Validation failure: **422 Unprocessable**. Rate-limit storage failure: **fail-closed (block)**. Cache v1: **in-memory + file only** (redis deferred to v1.1). PSR strategy: **native interfaces, PSR-7/15/3-shaped by convention** (zero-dep D2). DI: **explicit bindings only** (no reflection autowiring). `bone` v1 subcommands: **routes, modules, serve** (test + make:* deferred to v1.1). JWT: **HS256**, access 1h + refresh 7d, secret from env. | Locked by user Q&A 2026-11-14 |

---

## 2. Non-Goals

Explicitly out of scope for v1.0:

- **No full CMS** — no content modeling, admin UI, or page tree.
- **No views / HTML rendering** — JSON responses only (D3).
- **No worker / queue / job system** — no background workers, no SSE (D8).
- **No multi-tenant SaaS platform** — one install serves one microservice (D4).
- **No composer required at runtime** — copy-folder deploy; composer.json is
  optional dev-time convenience only (D2).
- **No ORM / ActiveRecord** — query builder over PDO only (D6).
- **No plugin marketplace / dynamic code loading** — modules ship in-tree,
  registered explicitly (D2, D5).
- **No bundled sample feature in v1.0** — v1 ships the framework core (kernel,
  router, middleware, config, DI, logger, DB builder, validator, security
  modules, cache, test runner, CLI). The **module system** (ARCHITECTURE §5:
  `src/Module/Module.php` + module-aware autoloader + composition-root
  registration) is implemented post-v1 as the development-layer separation
  between core and feature code. The sample *Auth module*
  (`modules/Auth/` → `/api/auth/login` + `/api/auth/refresh` + `users`
  table seed + CORS) is the first module; it is reference code, not part of
  the framework core, and may be removed without a framework breaking change.

---

## 3. Target Users

**Primary persona**: PHP developers (8.1+) building small JSON/REST API
services — internal tools, BFF endpoints, webhooks, service-to-service APIs —
who want a familiar structure, minimal maintenance, and secure defaults without
dependency management.

**Secondary personas**:
- DevOps deploying via copy-folder to PHP-FPM or container images.
- Small teams standardizing a "common structure" across multiple microservices.

---

## 4. Functional Requirements (per module)

Each requirement lists acceptance criteria (AC). All modules are planned;
class names beyond `Request`/`Response`/`Router`/`Kernel` are TBD.

### 4.1 Kernel
- Boot sequence: autoload → config → module registry → middleware pipeline → route dispatch.
- **AC**: A request entering the front controller produces exactly one JSON response, including on unhandled errors (500 + JSON error body, no stack trace leakage in production mode).
- **AC**: Uncaught exceptions are converted to a structured JSON error by the kernel error handler.

### 4.2 Autoloader + module registry (custom PSR-4)
- Custom PSR-4 autoloader resolves `Boneradf\` without composer.
- **AC**: Framework boots on a PHP 8.1+ install with no `vendor/` directory present.
- **AC**: Module registry lists installed/enabled modules; core modules default enabled.

### 4.3 Router
- Method + path matching (planned: path params; exact/static vs dynamic matching TBD in PLAN).
- **AC**: Registered routes are enumerable (needed for `bone` route listing).
- **AC**: Unknown route → 404 JSON; wrong method → 405 JSON.

### 4.4 Middleware pipeline (PSR-15-style)
- Ordered middleware; each can call next, short-circuit (abort), or throw.
- **AC**: Ordering test proves execution order is registration order (before/after segments).
- **AC**: Abort short-circuits downstream middleware; error thrown in a middleware bubbles to kernel error handler.

### 4.5 DI container (light)
- Container resolves constructor-injected dependencies (autowiring scope TBD: full autowiring vs explicit bindings — open question).
- **AC**: Resolving a service with a concrete dependency returns a wired instance.
- **AC**: Circular dependency detection fails loudly, not with a stack overflow.

### 4.6 Config
- Typed access to config values (env-overridable source TBD).
- **AC**: Accessing a missing required config key fails with a clear error; accessing an unset optional key returns its default.
- **AC**: Secrets can be provided via environment without editing files.

### 4.7 Logging
- Structured (JSON) log lines; levels (debug/info/warning/error); PSR-3-shaped interface optional (TBD).
- **AC**: A log write produces one structured line including timestamp, level, channel, message, context.
- **AC**: Log destination configurable (file path / stderr); no secrets in default log context.

### 4.8 DB / query builder
- PDO query builder with **prepared statements only**; DSN support for MySQL/MariaDB/SQLite/Postgres (D6).
- **AC**: Core exposes no API that concatenates user input into SQL text (no raw query API in core).
- **AC**: SQLi probe payload (`' OR 1=1 --`) passed as a bound parameter is treated as data; a test proves injection fails safely.
- **AC**: All four DSN families connect through the same builder surface (connection smoke test per driver where available).

### 4.9 Validation
- Strict input validation: required, types (string/int/float/bool/array/email as applicable), pattern, length bounds; validation failures → 422 JSON with field errors (status code TBD in PLAN).
- **AC**: Rules cover required/types/patterns/lengths; a failing rule produces a per-field error structure.
- **AC**: Request body/query/route params can each be validated before handler execution (via middleware or router hook — TBD).

### 4.10 Auth module (JWT + session)
- **AC**: Valid credential issue produces signed token/session; tampered or expired token is rejected (401).
- **AC**: Disabled/unconfigured auth fails closed, not open.

### 4.11 Security headers module
- Emits CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy on every response; ON by default (D5).
- **AC**: A test asserts all five headers present on a default response.
- **AC**: Header values are configurable; defaults are strict.

### 4.12 CSRF module
- Signed token for state-changing requests (POST/PUT/PATCH/DELETE); applies to cookie-based auth flows.
- **AC**: State-changing request with missing/invalid token → rejected (403).
- **AC**: Valid token passes; module ON by default for cookie-authenticated requests.

### 4.13 Rate-limit module
- Per-key (client/IP/token) request throttling; ON by default (D5).
- **AC**: Exceeding the limit returns 429 with a clear JSON body; under-limit requests pass.
- **AC**: Fail mode on storage failure is explicit and documented (block vs allow — open question, default TBD).

### 4.14 RBAC module
- Roles/permissions checks for routes; permission resolution tested.
- **AC**: User with role lacking permission → 403; with permission → allowed.
- **AC**: Route protection is declarative (per-route permission requirement).

### 4.15 Cache
- Light cache with a file-based (and/or PSR-16-shaped) driver; driver scope TBD.
- **AC**: Set/get round-trip returns stored value; TTL expiry returns miss.

### 4.16 Test runner (micro)
- Dependency-free runner: assertions, pass/fail output, non-zero exit on failure (D7).
- **AC**: `php bone test` (or equivalent entry) runs the suite; failing assertion exits non-zero.
- **AC**: Framework's own acceptance tests (P0–P5) run under this runner.

### 4.17 CLI (`bone`)
- Minimal entry: route registration/list, module list, serve (dev server wrapper), test (TBD which subcommands are locked vs planned).
- **AC**: `bone` lists registered routes and installed modules.
- **AC**: `bone serve` wraps the PHP built-in server for local dev.
- **AC**: No worker/queue subcommands in v1 (D8).

---

## 5. Non-Functional Requirements

| NFR | Target (not measured) | Notes |
|-----|----------------------|-------|
| Framework overhead TTFB budget | **< 5 ms** (target, NOT measured) | Overhead = kernel+router+middleware, excludes app logic and I/O |
| Memory footprint | TBD target — mark open question | Measure baseline on PHP 8.1 FPM once P0 boots |
| PHP compatibility | **PHP 8.1+** (minimal requirement) | Locked |
| Runtime dependencies | **Zero** (D2, D7) | No composer runtime; no PHPUnit |
| Response format | JSON only (D3) | UTF-8, correct `application/json` content type |
| Deploy | Copy-folder; one install per microservice (D2, D4) | Works under PHP-FPM, nginx, and `php -S` |
| Portability | Filesystem-relative, no global state assumptions | Common structure across services |

---

## 6. Usability Stories (effortless use)

**US-1 — Scaffold → route → respond**
As a developer, I create a project from the framework folder, register one
route, and return JSON — with no composer install step.
- AC: Following the quickstart, a new route answers with JSON in under 5
  minutes on a clean PHP 8.1+ machine.

**US-2 — Copy-folder deploy**
As an operator, I deploy by copying the project folder to the server; the
custom autoloader boots without `vendor/`.
- AC: Deployment on a host without composer produces a working service.

**US-3 — Common structure across services**
As a team lead, every microservice uses the same layout, so onboarding and
maintenance are uniform.
- AC: Two services built from the skeleton share identical top-level structure.

**US-4 — Secure by default, out of the box**
As a developer, security headers, prepared statements, and validation apply
without extra wiring.
- AC: A fresh route has security headers and cannot issue a raw SQL query.

---

## 7. Security Requirements (injection-hack-free)

| Threat | Control (default ON) |
|--------|----------------------|
| SQL injection | PDO prepared-statement-only query builder; **no raw query API in core** (D5, D6) |
| XSS | JSON-only responses (D3) — no HTML output, therefore no output-escaping layer required in core; consumers must handle their own rendering escaping |
| CSRF | Signed CSRF token module for state-changing requests (D5) |
| Brute force / abuse | Rate-limit module, enabled by default (D5) |
| Transport / clickjacking / MIME sniffing / referrer leaks | Security headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy — emitted by default (D5) |
| Cookie tampering | Signed cookies (D5) |
| AuthN/AuthZ | JWT + session auth module; RBAC module; fail-closed when unconfigured (D5) |
| Stack trace leakage | Kernel error handler returns structured JSON errors; detail disclosure gated by environment (production default: no trace) |

**Decision**: Secure behaviors are ON by default; disabling them must be an
explicit, visible configuration change. **Rationale**: defense-by-default is a
locked decision (D5); opt-in security is a known failure mode.

---

## 8. Versioning & Roadmap

**v1.0 = the scope in this PRD**: kernel, autoloader, router, middleware, DI,
config, logging, DB query builder, validation, security headers, CSRF, auth
(JWT+session), RBAC, rate-limit, cache, micro test runner, `bone` CLI — per
locked decisions D1–D8.

**Roadmap sketch (open questions only — nothing committed):**
- v1.1+ candidates: SSE? background workers? queue/job system? Each is
  explicitly **out of v1** (D8) and requires a new decision record before
  inclusion.
- `composer.json` as dev-time convenience (D2) — when and in what shape: open.
- Additional cache drivers, OpenAPI generation, pagination helpers: open.

---

## 9. Resolved Decisions (was: Open Questions)

All 10 open questions resolved 2026-11-14 via user Q&A. Now locked as D9–D10.

| # | Question | Resolution |
|---|----------|------------|
| 1 | Memory footprint target | **< 25 MB RSS** (D9) |
| 2 | DI autowiring scope | **Explicit bindings only** (D10) |
| 3 | Validation failure status | **422 Unprocessable** (D10) |
| 4 | Rate-limit fail mode | **Fail-closed (block)** (D10) |
| 5 | Cache driver scope v1 | **In-memory + file only** (D10) |
| 6 | Config source precedence | **env > file > defaults** (D10) |
| 7 | Router path param syntax | **`:id` colon** (D10) |
| 8 | `bone` v1 subcommands | **routes, modules, serve** (D10) |
| 9 | PSR interface strategy | **Native interfaces, PSR-shaped by convention** (D10) |
| 10 | JWT algorithm + lifetime | **HS256, access 1h + refresh 7d, secret from env** (D10) |

**No open questions remain.**

---

Last synced: 2026-11-14 — project root moved to app/; decisions from user Q&A locked.
