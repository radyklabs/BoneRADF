# Audit Report — BoneRADF

| Field | Value |
|---|---|
| Project | BoneRADF — zero-dependency headless PHP microservice framework |
| Report date | 2026-11-14 |
| Status legend | ✅ Done · ⚠️ Open · 🔵 Info · 🟠 Major · 🔴 Critical |

## Scope

Audit covers documentation completeness and implementation readiness only.
No framework code exists in this repository yet; no code-level findings are
reported. Out of scope: `conf/`, `logs/`, `sql/`, `.envrc` (legacy), and
`sample_frontdev/` (gitignored frontend test scaffold) — all not part of
BoneRADF.
Project root is `app/` — all BoneRADF code and docs live there.

## Status by work stream

| # | Work stream | Phase | Status | Notes |
|---|---|---|---|---|
| 1 | Docs scaffold | P0-docs | ✅ Done | PRD, ARCHITECTURE, SECURITY, API_GUIDE, DEPLOY, PLAN, CHANGELOG, REPORT, RULES, CONFIG, CONFIG.sample created |
| 2 | Kernel | P0 | ✅ Done | Autoloader, Request, Response, Kernel, front controller. `tests/p0.php` 20/20 pass. Peak RSS 2.00 MB (target < 25 MB). 404 JSON via `php -S` verified. |
| 3 | Routing + Middleware | P1 | ✅ Done | Router (`:param`, groups, enumeration), Pipeline (ordering, abort, error bubbling), Kernel wired (404/405/500 envelopes). `tests/p1.php` 20/20 pass. Dev server: `/health` → 200, `/hello` → 200, unknown → 404. |
| 4 | Config + DI + Logging | P2 | ⚠️ Open | Not started |
| 5 | DB + Validation | P3 | ⚠️ Open | PDO-only query builder, validation rules not started |
| 6 | Security modules | P4 | ⚠️ Open | Defense-by-default modules not started |
| 7 | Cache + Tests + CLI + Deploy | P5 | ⚠️ Open | Not started |
| 8 | v1.0.0 candidate | release | ⚠️ Open | Blocked on P0–P5 completion |

Overall: P0 + P1 complete; P2–P5 open. M1 gate met.

## Findings

- 🔵 Zero-dependency autoloader is the main performance risk to watch; its
  load-time cost should be benchmarked against composer/PSR-4 baselines once
  the kernel lands.
- 🔵 JWT HS256 secret rotation needs an operational runbook (rotate, dual-verify
  window, revoke) — design decision deferred to the security-modules phase.
- 🔵 Redis cache is optional; absence must degrade to array/file cache with a
  clear detection and warning path documented in DEPLOY.
- 🔵 One-install-per-service constraint means multi-service orchestration is out
  of framework scope — users own process supervision and routing at the edge.
- 🔵 PDO-only query builder excludes ORM/active-record patterns by design;
  API_GUIDE must state this explicitly to set expectations early.

## Risks

| Severity | Risk | Impact | Mitigation |
|---|---|---|---|
| 🔴 Critical | No code exists yet; schedule risk for any date-dependent milestone | v1.0 cannot be scoped or estimated until P0 starts | **Resolved 2026-11-14** — P0 complete; kernel boots, serves 404 JSON, 2.00 MB peak RSS |
| 🟠 Major | Zero-dep autoloader performance unmeasured | Could miss headless-API latency targets under load | **Resolved 2026-11-14** — `tests/p0.php` run: 20 assertions pass; peak RSS 2.00 MB (target < 25 MB) |
| 🟠 Major | Security modules are ON by default but untested | Defaults could break legitimate requests or mask flaws | Thorough test matrix in P4 before any release candidate |

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

1. PLAN reflects current REPORT status — verified 2026-11-14 (both: docs only).
2. PRD reflects implemented vs planned — verified 2026-11-14 (P0 done: kernel, autoloader, request/response; P1–P5 still planned).
3. REPORT matches actual code state — verified 2026-11-14 (P0 complete: `src/Kernel/`, `public/index.php`, `tests/p0.php`).
4. CHANGELOG includes all committed changes — verified 2026-11-14 (P0 entry added).

## Next actions

1. ~~Start kernel/P0~~ — done 2026-11-14.
2. ~~Benchmark autoloader~~ — done: peak RSS 2.00 MB, recorded above.
3. ~~Start P1 router + middleware~~ — done 2026-11-16; `tests/p1.php` 20/20 pass.
4. Start P2: Config loader + DI container + structured logger.
5. Implement `bone` CLI skeleton (routes, modules, serve — D10).

---

**Last synced: 2026-11-16 — P0 + P1 complete; 40/40 tests pass, 2.00 MB peak RSS.**
