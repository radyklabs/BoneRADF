# Audit Report — BoneRADF

| Field | Value |
|---|---|
| Project | BoneRADF — zero-dependency headless PHP microservice framework |
| Report date | 2026-11-16 |
| Status legend | ✅ Done · ⚠️ Open · 🔵 Info · 🟠 Major · 🔴 Critical |

## Scope

Audit covers documentation completeness and implementation readiness.
All phases P0–P5 are implemented; code lives under `src/`, `public/`, `bin/`,
`tests/`. Out of scope: `conf/`, `logs/`, `sql/`, `.envrc` (legacy), and
`sample_frontdev/` (gitignored frontend test scaffold) — all not part of
BoneRADF.
Project root is `app/` — all BoneRADF code and docs live there.

## Status by work stream

| # | Work stream | Phase | Status | Notes |
|---|---|---|---|---|
| 1 | Docs scaffold | P0-docs | ✅ Done | PRD, ARCHITECTURE, SECURITY, API_GUIDE, DEPLOY, PLAN, CHANGELOG, REPORT, RULES, CONFIG, CONFIG.sample created |
| 2 | Kernel | P0 | ✅ Done | Autoloader, Request, Response, Kernel, front controller. `tests/p0.php` 20/20 pass. Peak RSS 2.00 MB (target < 25 MB). 404 JSON via `php -S` verified. |
| 3 | Routing + Middleware | P1 | ✅ Done | Router (`:param`, groups, enumeration), Pipeline (ordering, abort, error bubbling), Kernel wired (404/405/500 envelopes). `tests/p1.php` 20/20 pass. Dev server: `/health` → 200, `/hello` → 200, unknown → 404. |
| 4 | Config + DI + Logging | P2 | ✅ Done | Typed Config (env > file > defaults, required keys, type coercion), light DI Container (explicit bindings, circular detection), FileLogger (JSON lines, secret redaction). `tests/p2.php` 27/27 pass. Dev server verified. |
| 5 | DB + Validation | P3 | ✅ Done | PDO query builder (prepared-only, identifier allowlist, no raw SQL API), ConnectionFactory, strict Validator (required, types, min/max, pattern, in, callback, email, date). `tests/p3.php` 25/25 pass. SQLi probe `' OR 1=1 --` bound as param, not in SQL. |
| 6 | Security modules | P4 | ✅ Done | SecurityHeaders (5 ON), CsrfGuard (HMAC-signed, 403 on fail), AuthGuard (JWT HS256, fail-closed), RbacGuard (deny-by-default), RateLimiter (sliding window, fail-closed, 429), SignedCookie (HMAC, constant-time). `tests/p4.php` 36/36 pass. |
| 7 | Cache + Tests + CLI + Deploy | P5 | ✅ Done | Cache (MemoryDriver + FileDriver + Cache facade), micro test runner (assert_true/assert_eq/assert_null/assert_contains, non-zero exit), `bone` CLI (routes/modules/serve), Dockerfile. `tests/p5.php` 21/21 pass. `bone routes` and `bone modules` verified on CLI. |
| 8 | v1.0.0 candidate | release | ✅ Done | All phases P0–P5 complete; 149 total tests pass across p0–p5. |

Overall: P0–P5 complete; v1.0.0 candidate. M1–M3 gates met.

## Findings

- 🔵 **Resolved** (P0): zero-dep autoloader load-time risk — kernel boots, peak RSS 2.00 MB, 404 JSON served.
- 🔵 JWT HS256 secret rotation still needs an operational runbook (rotate, dual-verify window, revoke). AuthGuard implements issue/verify with exp/nbf; rotation is operator procedure, not yet written up.
- 🔵 Redis cache remains deferred (D10); MemoryDriver + FileDriver shipped. DEPLOY already notes optional ext-redis.
- 🔵 One-install-per-service: multi-service orchestration stays out of framework scope.
- 🔵 PDO-only query builder excludes ORM/active-record by design; API_GUIDE documents the contract.
- 🔵 `FileDriver` uses PHP `serialize` for its own cache files (not untrusted input); marked with a `ponytail:` comment. Deviation: not JSON, reconciles if cache dir becomes externally writable.
- 🔵 `assert()` is PHP-reserved, so the micro runner exposes `assert_true()`; documented in CHANGELOG.

## Risks

| Severity | Risk | Impact | Mitigation |
|---|---|---|---|
| 🔴 Critical | No code exists yet; schedule risk for any date-dependent milestone | v1.0 cannot be scoped or estimated until P0 starts | **Resolved 2026-11-14** — P0 complete; kernel boots, serves 404 JSON, 2.00 MB peak RSS |
| 🟠 Major | Zero-dep autoloader performance unmeasured | Could miss headless-API latency targets under load | **Resolved 2026-11-14** — `tests/p0.php` run: 20 assertions pass; peak RSS 2.00 MB (target < 25 MB) |
| 🟠 Major | Security modules are ON by default but untested | Defaults could break legitimate requests or mask flaws | **Resolved 2026-11-16** — `tests/p4.php` 36/36: headers present, CSRF pass/fail, JWT tamper+expiry, RBAC deny, rate-limit 429, cookie tamper |
| 🟠 Major | Rate-limit state is per-worker in-memory | Limits multiply by FPM worker count; not a global cap | Accepted v1 limitation (SECURITY.md §7, §12). File-backed limiter / redis deferred. |
| 🔵 Info | No live session revocation in v1 | Stolen access token valid until `exp` | Documented in SECURITY.md §12; access TTL 1h default. |
| 🔵 Info | `bone serve` does not spawn the server | Prints the command instead | Secure-coding rule bans proc_open/exec; deviation marked in `bin/bone`; reconcile when rule is scoped to runtime. |

Note: earlier risk "unrelated WordPress tree occupies app/" is **resolved** —
project root moved to `app/`; the legacy tree has been superseded. `conf/`
and `logs/` at repo root remain out-of-scope.

## Decisions log

| # | Decision | Date | Status |
|---|---|---|---|
| 1 | Zero dependencies (no Composer packages at runtime) | 2026-11-14 | ✅ |
| 2 | JSON-only I/O (JSON/REST API, no HTML rendering) | 2026-11-14 | ✅ |
| 3 | One service per install (no multi-app routing) | 2026-11-14 | ✅ |
| 4 | Core-minimal + security modules (defense-by-default modules) | 2026-11-14 | ✅ |
| 5 | PDO-builder-only (query builder over PDO, no ORM) | 2026-11-14 | ✅ |
| 6 | Name: BoneRADF | 2026-11-14 | ✅ |
| 7 | Memory target < 25 MB RSS | 2026-11-14 | ✅ |
| 8 | Config: env > file > defaults | 2026-11-14 | ✅ |
| 9 | Router param syntax: `:id` | 2026-11-14 | ✅ |
| 10 | Validation failure: 422 | 2026-11-14 | ✅ |
| 11 | Rate-limit: fail-closed | 2026-11-14 | ✅ |
| 12 | Cache v1: in-memory + file only | 2026-11-14 | ✅ |
| 13 | PSR: native interfaces, PSR-shaped | 2026-11-14 | ✅ |
| 14 | DI: explicit bindings only | 2026-11-14 | ✅ |
| 15 | `bone` v1: routes, modules, serve | 2026-11-14 | ✅ |
| 17 | Branching model: main/dev/feat/fix/hotfix/patch | 2026-11-14 | ✅ |
| 18 | Custom commands: >branch, >push, >sync, >track, >audit, >release | 2026-11-14 | ✅ |
| 19 | RULES.md + CONFIG.md pattern (radykfw-style) | 2026-11-14 | ✅ |

## Doc sync checklist

1. PLAN reflects current REPORT status — verified 2026-11-16 (P0–P5 ✅ Done; v1.0.0 candidate ✅).
2. PRD reflects implemented vs planned — verified 2026-11-16 (all modules implemented; PRD remains the requirements source of truth).
3. REPORT matches actual code state — verified 2026-11-16 (149 tests pass across p0–p5; `src/` + `public/` + `bin/` + `tests/` + `Dockerfile`).
4. CHANGELOG includes all committed changes — verified 2026-11-16 (P0–P5 entries present under `[1.0.0-dev]`).

## Next actions

1. ~~Start kernel/P0~~ — done 2026-11-14.
2. ~~Benchmark autoloader~~ — done: peak RSS 2.00 MB, recorded above.
3. ~~Start P1 router + middleware~~ — done 2026-11-16; `tests/p1.php` 20/20 pass.
4. ~~Start P2 config + DI + logging~~ — done 2026-11-16; `tests/p2.php` 27/27 pass.
5. ~~Start P3 PDO query builder + validator~~ — done 2026-11-16; `tests/p3.php` 25/25 pass.
6. ~~Start P4 security modules~~ — done 2026-11-16; `tests/p4.php` 36/36 pass.
7. ~~Start P5 cache + test runner + CLI + deploy~~ — done 2026-11-16; `tests/p5.php` 21/21 pass; `bone routes` / `bone modules` verified.
8. Next: run `>release` to cut `v1.0.0` (CHANGELOG has no `[1.0.0]` section yet).

---

**Last synced: 2026-11-16 — P0–P5 complete; 149/149 tests pass, 2.00 MB peak RSS.**
