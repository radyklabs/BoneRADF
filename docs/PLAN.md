# PLAN: BoneRADF Development Roadmap

**Status**: Draft (decisions locked)
**Owner**: Product + Planning Lead
**Last Updated**: 2026-11-14
**Source of truth**: Locked decisions D1–D10 (see `app/docs/PRD.md` §1, §9)

Phases P0–P5 build v1.0. Each phase: goal, deliverables, verification check.
Deliverable file paths inside `src/` are **planned**, not yet created, except
where noted as actual docs.

---

## Phase P0 — Kernel, autoloader, entry point

**Goal**: Boot a framework on plain PHP 8.1+ with no composer/vendor, serve one JSON route.

**Deliverables (planned)**:
- Custom PSR-4 autoloader for `Boneradf\` (no vendor/) — D2
- Front-controller entry point + `Kernel` boot sequence
- PSR-15-style `Request` / `Response` objects (JSON body handling)
- Project skeleton layout establishing the "common structure" — D4

**Actual (2026-11-17)**: autoloader + composition root consolidated in
`src/Kernel/Boot.php` (`Boot::init()` → `Kernel`); `src/Manifest/Routing.php`
holds the system route aliases; modules auto-discovered from
`modules/<Name>/Config/modules.php`, activated by their own
`Config/routing.php`. `public/index.php` is 3 lines.

**Verification check**: One route served via `php -S` (built-in server)
returning a JSON body; boots with no `vendor/` directory present.

---

## Phase P1 — Routing + middleware pipeline

**Goal**: Route dispatch through an ordered middleware pipeline with abort and error bubbling.

**Deliverables (planned)**:
- `Router` (method + path matching; route enumeration API for CLI later)
- Middleware pipeline: `next`, short-circuit (abort), exception bubbling
- JSON error responses for 404 / 405 / unhandled exceptions

**Verification check**: Middleware ordering test proves registration-order
execution; abort short-circuits downstream; an error thrown in middleware
bubbles to the kernel error handler and returns structured JSON.

---

## Phase P2 — Config + DI container + logging

**Goal**: Typed config, container resolution, structured logs.

**Deliverables (planned)**:
- Config loader with typed access + env override (merge order: open question)
- Light DI container (constructor injection; autowiring scope: open question)
- Structured JSON logger (levels, destination configurable)

**Verification check**: Container resolves a service with a concrete
dependency; config access fails clearly on missing required key; a log write
produces one structured line (timestamp, level, channel, context) in the
configured destination.

---

## Phase P3 — DB query builder + validation

**Goal**: Injection-safe data layer and strict input validation — the core of the "injection-hack-free" guarantee.

**Deliverables (planned)**:
- PDO query builder, prepared statements only, **no raw query API in core** — D5/D6
- DSN connection support: MySQL/MariaDB/SQLite/Postgres
- Validation rules: required, types, pattern, length bounds; per-field error output

**Verification check**: Tests prove prepared statements only (probe payload
`' OR 1=1 --` bound as data fails safely — no injection); validation rules
cover types/patterns/lengths/required with per-field error structure.

---

## Phase P4 — Security modules

**Goal**: All security behaviors enforced ON by default — D5.

**Deliverables (planned)**:
- Security headers module: CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- CSRF module (signed token on state-changing requests)
- Auth module (JWT + session; fail-closed when unconfigured)
- RBAC module (per-route permission requirements)
- Rate-limit module (per-key throttling, 429 on exceed)
- Signed cookies (core) — D5

**Verification check**: Each module has **one passing and one failing test**;
each is asserted ON by default in a fresh app (headers present, missing CSRF
token rejected, unauthenticated request rejected, over-limit request → 429,
missing permission → 403).

---

## Phase P5 — Cache + test runner + CLI + deploy story

**Goal**: Complete the developer loop: test, inspect, deploy.

**Deliverables (planned)**:
- Cache (file-based driver scope TBD — open question)
- Built-in micro test runner, no PHPUnit — D7
- `bone` CLI: route list, module list, serve (dev server); no workers — D8
- Deploy recipe: Dockerfile / PHP-FPM + nginx documentation

**Verification check**: Micro test runner passes the full suite (and exits
non-zero on an injected failure); `bone` lists registered routes; documented
Dockerfile/PHP-FPM recipe boots the skeleton and serves a route.

---

## Task checklist

All tasks start ⚠️ Open. Phase headers are 🔵 Info.

| ID | Task | Phase | Status |
|----|------|-------|--------|
| P0.0 | Docs scaffold: PRD, ARCHITECTURE, SECURITY, API_GUIDE, DEPLOY, PLAN, CHANGELOG, REPORT, RULES, CONFIG, CONFIG.sample, CLAUDE.md (custom commands) | P0 | ✅ Done |
| — | **P0 — Kernel, autoloader, entry point, request/response** | **P0** | 🔵 Info |
| P0.1 | Custom PSR-4 autoloader for `Boneradf\` (no vendor) | P0 | ✅ Done |
| P0.2 | Front-controller entry point + Kernel boot sequence | P0 | ✅ Done |
| P0.3 | `Request` / `Response` (JSON) PSR-15-style objects | P0 | ✅ Done |
| P0.4 | One route served via `php -S` returning JSON | P0 | ✅ Done |
| — | **P1 — Routing + middleware pipeline** | **P1** | 🔵 Info |
| P1.1 | `Router`: method + path matching, route enumeration | P1 | ✅ Done |
| P1.2 | Middleware pipeline: next / abort / error bubbling | P1 | ✅ Done |
| P1.3 | JSON error responses: 404 / 405 / unhandled exception | P1 | ✅ Done |
| P1.4 | Middleware ordering + abort + bubbling tests | P1 | ✅ Done |
| — | **P2 — Config + DI + logging** | **P2** | 🔵 Info |
| P2.1 | Config loader, typed access, env override | P2 | ✅ Done |
| P2.2 | Light DI container, constructor injection | P2 | ✅ Done |
| P2.3 | Structured JSON logger | P2 | ✅ Done |
| P2.4 | Tests: container resolution, config errors, log line shape | P2 | ✅ Done |
| — | **P3 — DB query builder + validation** | **P3** | 🔵 Info |
| P3.1 | PDO query builder, prepared-only, no raw API (D5/D6) | P3 | ✅ Done |
| P3.2 | DSN support: MySQL/MariaDB/SQLite/Postgres | P3 | ✅ Done |
| P3.3 | Validation rules: required/types/pattern/length | P3 | ✅ Done |
| P3.4 | SQLi probe test (payload fails safely) + validation tests | P3 | ✅ Done |
| — | **P4 — Security modules (all default ON)** | **P4** | 🔵 Info |
| P4.1 | Security headers module (5 headers) + tests | P4 | ✅ Done |
| P4.2 | CSRF module + pass/fail tests | P4 | ✅ Done |
| P4.3 | Auth module (JWT + session), fail-closed + tests | P4 | ✅ Done |
| P4.4 | RBAC module + allow/deny tests | P4 | ✅ Done |
| P4.5 | Rate-limit module (429) + tests | P4 | ✅ Done |
| P4.6 | Signed cookies (core) + tamper test | P4 | ✅ Done |
| — | **P5 — Cache + test runner + CLI + deploy** | **P5** | 🔵 Info |
| P5.1 | Cache (file driver scope TBD) + TTL test | P5 | ✅ Done |
| P5.2 | Micro test runner (non-zero exit on failure) — D7 | P5 | ✅ Done |
| P5.3 | `bone` CLI: route list, module list (scans `modules/`, shows active/inactive), serve — D8 | P5 | ✅ Done |
| P5.4 | Dockerfile / PHP-FPM deploy recipe + verification | P5 | ✅ Done |
| — | **Post-v1 sample: login feature (end-to-end)** | **post-v1** | 🔵 Info |
| P6.0 | Sample DB: `modules/Auth/seed.php` idempotent — `users` table + seed user in DB `local` | post-v1 | ✅ Done |
| P6.1 | Module system: `src/Contracts/Module.php` interface (was `src/Module/`) + module-aware autoloader (`Modules\*`) | post-v1 | ✅ Done |
| P6.2 | `AuthModule` (`modules/Auth/AuthModule.php`) — self-registers login/refresh routes + global CORS | post-v1 | ✅ Done |
| P6.3 | `modules/Auth/Services/LoginService.php` (validate → query → `password_verify` → issue access+refresh, timing-uniform dummy hash) | post-v1 | ✅ Done |
| P6.4 | `public/index.php` = composition root (boots `AuthModule`, no inline login wiring) | post-v1 | ✅ Done |
| P6.5 | `tests/p6.php` — 16/16 pass; live HTTP smoke (login 200, wrong 401, empty 422, preflight 204, refresh 200, evil-origin no-CORS) | post-v1 | ✅ Done |
| P7.0 | `modules/Resources/seed.php` — idempotent `resources` table + Alpha/Beta/Gamma | post-v1 | ✅ Done |
| P7.1 | `modules/Resources/Services/ResourceService.php` — CRUD (prepared statements, name validation) | post-v1 | ✅ Done |
| P7.2 | `modules/Resources/ResourcesModule.php` — GET list/create, GET/PUT/DELETE by id + OPTIONS preflights | post-v1 | ✅ Done |
| P7.3 | Live CRUD smoke: list 3 rows, create 201, update, delete 204, preflight 204 + CORS | post-v1 | ✅ Done |
| — | **Post-v1: system DB + full-MVC + in-tree AR Model** (user-approved 2026-11-17) | **post-v1** | 🔵 Info |
| P8.0 | `src/Database/Database.php` — `fromEnv()` system factory (env read once, memoized shared PDO; removes duplicate module connections + dead `instance('db')`) | post-v1 | ✅ Done |
| P8.1 | `src/Database/{Model,Relation}.php` — AR base over QueryBuilder: chains + terminals, `hasOne`/`hasMany`/`belongsTo`/`belongsToMany`, eager `with()` (bulk IN, N+1-guarded), arrays out | post-v1 | ✅ Done |
| P8.2 | Full-MVC sweep — `modules/<Name>/{Controllers,Models,Services}/` + namespaces `Modules\<Name>\<Layer>`; routing manifests + wiring updated; `src/` concept retired per module | post-v1 | ✅ Done |
| P8.3 | Query extraction — `UserModel::findByUsername`, `ResourceModel::listAll`; services/controllers hold zero SQL; module `boot()` reads no `BONERADF_DB_*` | post-v1 | ✅ Done |
| P8.4 | `tests/p7.php` — 26/26 (factory memoize, CRUD + chain reset, 4 relation types, N+1 guard, fail-loud); suite p0–p7 192/192 | post-v1 | ✅ Done |
| — | **Post-v1: module auto-discovery + routing-based activation** (user-approved 2026-11-17) | **post-v1** | 🔵 Info |
| P9.0 | `src/Manifest/Modules.php` deleted; `Boot` scans `modules/*/Config/modules.php` — adding a module needs zero core edits | post-v1 | ✅ Done |
| P9.1 | Activation switch: `Config/routing.php` ≥1 route = active/booted; empty = inert (fail-closed). `bin/bone modules` shows active/inactive + route count | post-v1 | ✅ Done |
| P9.2 | `tests/p8.php` — 9/9 (discovery, active boot, inert no-boot, route-count activation, `bone modules` output); suite p0–p8 201/201 | post-v1 | ✅ Done |
| — | **Post-v1: HTML view responses (public landing)** (user-approved 2026-11-17) | **post-v1** | 🔵 Info |
| P9.3 | `Response::html()` + `Response::view()` — opt-in text/html responses; views under `src/Views/` (traversal-guarded name); `src/Views/landing.php` sample; D2 amended | post-v1 | ✅ Done |
| P9.4 | `tests/p0.php` +6 (html pass-through, view render, traversal reject, missing view); suite p0–p8 207/207 | post-v1 | ✅ Done |

---

## Milestones

| Milestone | Scope | Gate (definition of done) | Status |
|-----------|-------|---------------------------|--------|
| **M1** | P0–P1 | Route served (via `php -S`); middleware ordering, abort, error bubbling verified | ✅ Done |
| **M2** | P2–P3 | Container resolves + typed config + structured logs; prepared-only DB with SQLi probe failing safely; validation rules covered | ✅ Done |
| **M3** | P4–P5 | Each security module has passing + failing test, default-ON asserted; micro test runner green; `bone` lists routes; Dockerfile/PHP-FPM recipe works → **v1.0 candidate** | ✅ Done |

---

## Rules of engagement

- Locked decisions D1–D8 (PRD §1) are not revisited here.
- New file paths in `src/` are planned until created; do not cite them as existing.
- Scope changes mid-phase: accept / defer / reject explicitly — never silently absorb.

Last synced: 2026-11-17 — P0–P5 complete; M1–M3 gates met; v1.0.0 candidate. Post-v1: module system (§5) + Auth (P6.0–P6.5) + Resources (P7.0–P7.3) done. New: system-level `Database::fromEnv()` + in-tree AR Model + full-MVC layout (P8.0–P8.4) + module auto-discovery & routing-based activation (P9.0–P9.2) + HTML view responses for public landing (P9.3–P9.4) ✅ — suite p0–p8 207/207.
