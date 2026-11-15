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
| P5.3 | `bone` CLI: route list, module list, serve — D8 | P5 | ✅ Done |
| P5.4 | Dockerfile / PHP-FPM deploy recipe + verification | P5 | ✅ Done |

---

## Milestones

| Milestone | Scope | Gate (definition of done) | Status |
|-----------|-------|---------------------------|--------|
| **M1** | P0–P1 | Route served (via `php -S`); middleware ordering, abort, error bubbling verified | ⚠️ Open |
| **M2** | P2–P3 | Container resolves + typed config + structured logs; prepared-only DB with SQLi probe failing safely; validation rules covered | ⚠️ Open |
| **M3** | P4–P5 | Each security module has passing + failing test, default-ON asserted; micro test runner green; `bone` lists routes; Dockerfile/PHP-FPM recipe works → **v1.0 candidate** | ⚠️ Open |

---

## Rules of engagement

- Locked decisions D1–D8 (PRD §1) are not revisited here.
- New file paths in `src/` are planned until created; do not cite them as existing.
- Scope changes mid-phase: accept / defer / reject explicitly — never silently absorb.

Last synced: 2026-11-14 — project root moved to app/; decisions from user Q&A locked.
