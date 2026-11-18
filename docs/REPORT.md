# Audit Report — BoneRADF

| Field | Value |
|---|---|
| Project | BoneRADF — zero-dependency headless PHP microservice framework |
| Report date | 2026-11-17 |
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
| Composition root | P0 | ✅ Done | `src/Kernel/Boot.php` = autoloader + module discovery + module boot + route merge → `Kernel`. `public/index.php` 3 lines. `src/Manifest/Routing.php` + per-module `Config/{modules,routing}.php`. Typed segments `{id:int}`. Tests p0–p5 150/150, p6 16/16, p7 26/26, p8 9/9 (201/201). `bone routes` / `bone modules` read the manifests + scan `modules/`. |
| 2 | Kernel | P0 | ✅ Done | Autoloader, Request, Response, Kernel, front controller. `tests/p0.php` 20/20 pass. Peak RSS 2.00 MB (target < 25 MB). 404 JSON via `php -S` verified. |
| 3 | Routing + Middleware | P1 | ✅ Done | Router (`:param`, groups, enumeration), Pipeline (ordering, abort, error bubbling), Kernel wired (404/405/500 envelopes). `tests/p1.php` 20/20 pass. Dev server: `/health` → 200, `/hello` → 200, unknown → 404. |
| 4 | Config + DI + Logging | P2 | ✅ Done | Typed Config (env > file > defaults, required keys, type coercion), light DI Container (explicit bindings, circular detection), FileLogger (JSON lines, secret redaction). `tests/p2.php` 27/27 pass. Dev server verified. |
| 5 | DB + Validation | P3 | ✅ Done | PDO query builder (prepared-only, identifier allowlist, no raw SQL API), ConnectionFactory, strict Validator (required, types, min/max, pattern, in, callback, email, date). `tests/p3.php` 25/25 pass. SQLi probe `' OR 1=1 --` bound as param, not in SQL. |
| 6 | Security modules | P4 | ✅ Done | SecurityHeaders (5 ON), CsrfGuard (HMAC-signed, 403 on fail), AuthGuard (JWT HS256, fail-closed), RbacGuard (deny-by-default), RateLimiter (sliding window, fail-closed, 429), SignedCookie (HMAC, constant-time). `tests/p4.php` 36/36 pass. |
| 7 | Cache + Tests + CLI + Deploy | P5 | ✅ Done | Cache (MemoryDriver + FileDriver + Cache facade), micro test runner (assert_true/assert_eq/assert_null/assert_contains, non-zero exit), `bone` CLI (routes/modules/serve), Dockerfile. `tests/p5.php` 21/21 pass. `bone routes` and `bone modules` verified on CLI. |
| 8 | v1.0.0 candidate | release | ✅ Done | All phases P0–P5 complete; 149 total tests pass across p0–p5. |
| 9 | Sample login feature | post-v1 | ✅ Done | `AuthModule` (`modules/Auth/`) exposes `/api/auth/login` + `/api/auth/refresh` + global CORS, wired via composition root `public/index.php`; `modules/Auth/seed.php` creates `users` table + seed user `boneradf` in DB `local` (10028, root/root); `tests/p6.php` 16/16. Live HTTP: login 200 (CORS headers on allowed origin), wrong pass 401, empty 422, preflight 204, refresh 200, unknown origin → no CORS headers (fail-closed). |
| 10 | Module system (§5) | post-v1 | ✅ Done | `src/Contracts/Module.php` interface (`boot(Container, Router, Pipeline)`) + module-aware autoloader (`Modules\<Name>\<Rest>` → `modules/<Name>/<Rest>.php` — nested dirs resolve, full-MVC layout; legacy `src/` retired). Two-layer activation (amended 2026-11-17): system-level auto-discovery — `Boot` scans `modules/*/Config/modules.php` (central `src/Manifest/Modules.php` deleted, no core edits to add a module); activation is the module's own `Config/routing.php` — ≥1 route = active/booted, empty = inert (fail-closed). Topo-sorted, no global registry (§7). |
| 11 | Resources module | post-v1 | ✅ Done | `ResourcesModule` (`modules/Resources/`) CRUD over `resources` table: GET/POST list+create, GET/PUT/DELETE by id, name validation (422 field errors), preflight OPTIONS. Seed 3 rows (Alpha, Beta, Gamma). Live CRUD: list 3 rows, create 201, update, delete 204, preflight 204 + CORS. |
| 12 | System DB + full-MVC + AR Model | post-v1 | ✅ Done | `src/Database/Database.php` (`fromEnv()` — env read once, memoized shared PDO; removes duplicate per-request module connections + dead `instance('db')`), `src/Database/{Model,Relation}.php` (AR over QueryBuilder: chains + terminals, `hasOne`/`hasMany`/`belongsTo`/`belongsToMany`, eager `with()` bulk-IN N+1-guarded, rows stay arrays). Modules restructured to `Controllers/ + Models/ + Services/` (namespaces `Modules\<Name>\<Layer>`); SQL lives only in `Models/` (`UserModel::findByUsername`, `ResourceModel::listAll`); module `boot()` reads no `BONERADF_DB_*`. `tests/p7.php` 26/26; suite p0–p7 192/192. |
| 13 | Module auto-discovery + routing-based activation | post-v1 | ✅ Done | `src/Manifest/Modules.php` deleted (central whitelist removed); `Boot` scans `modules/*/Config/modules.php` (discovery) and treats `Config/routing.php` as the activation switch (≥1 route = booted, empty = inert). `bin/bone modules` shows active/inactive status. `tests/p8.php` 9/9; suite p0–p8 201/201. |
| 14 | HTML view responses (public landing) | post-v1 | ✅ Done | `Response::html()` + `Response::view()` — opt-in `text/html` responses; views under `src/Views/` (name pattern guards traversal); headless default preserved (JSON envelope unchanged). `src/Views/landing.php` sample. p0 +6; suite p0–p8 207/207. |
| 15 | P9.5 review-driven hardening | post-v1 | ✅ Done | `Config` wired into `Boot` (typed, env > file > defaults) + `Database::fromEnv()` fail-closed (no `root/root` fallbacks) + DB-availability-aware boot check; `Model::find()` respects the queued `where()`; `Route` escapes literal metacharacters; pipeline cloned per request; `RbacGuard` contract fixed for wiring; `bone make:module` generator; minor cleanup (recursive logger redact, `in` coercion, handler method-exists, JSON boot-failure, seed env centralization, p8 no `exec()`). Suite p0–p8 212/212. |

Overall: P0–P5 complete; v1.0.0 candidate. M1–M3 gates met. Post-v1 module system (P6) + Auth + Resources modules done; system-level DB factory + full-MVC layout + in-tree AR Model (P8) done; module auto-discovery + routing-based activation (P9.0–P9.2) + HTML view responses (P9.3–P9.4) + P9.5 review-driven hardening (Config wired + DB fail-closed, `find()` queue-respecting, route escape, pipeline-per-request, RBAC wiring, `make:module`, cleanup) done — suite p0–p8 212/212.

## Findings

- 🔵 **Resolved** (P0): zero-dep autoloader load-time risk — kernel boots, peak RSS 2.00 MB, 404 JSON served.
- 🔵 JWT HS256 secret rotation still needs an operational runbook (rotate, dual-verify window, revoke). AuthGuard implements issue/verify with exp/nbf; rotation is operator procedure, not yet written up.
- 🔵 Redis cache remains deferred (D10); MemoryDriver + FileDriver shipped. DEPLOY already notes optional ext-redis.
- 🔵 One-install-per-service: multi-service orchestration stays out of framework scope.
- 🔵 Builder-only design amended 2026-11-17 (user-approved): in-tree AR
  `Boneradf\Database\Model` (rows stay arrays, eager relations) sits over the
  query builder — no raw-SQL API, so D6's core constraint holds; "no external
  ORM package" remains (PRD §2, decision 20 below).
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
| 5 | PDO-builder-only (query builder over PDO, no ORM) | 2026-11-14 | ✅ (amended by #20: in-tree AR over the builder) |
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
| 20 | In-tree AR Model over QueryBuilder (ar-arrays + eager relations; no external ORM package, no raw-SQL API) — user-approved scope amendment to #5, together with full-MVC module layout (`Controllers/Models/Services`) and system-level `Database::fromEnv()` | 2026-11-17 | ✅ |
| 21 | Module auto-discovery + routing-based activation — `src/Manifest/Modules.php` deleted; `Boot` scans `modules/*/Config/modules.php` (known) and treats `Config/routing.php` (≥1 route) as the activation switch; inactive modules never boot; add-module needs zero core edits | 2026-11-17 | ✅ |
| 22 | HTML view responses (public landing) — opt-in `Response::html()` / `Response::view()` with views under `src/Views/`; D2 "no views/HTML" amended: headless default preserved, view rendering is a per-route opt-in, not framework-wide | 2026-11-17 | ✅ |
| 23 | RBAC wiring contract — `RbacGuard` route middleware: public routes pass through; declared `rbac:<perm>` checked deny-by-default (401/403). D5 wording softened from "RBAC ON by default" to "ON by default for protected routes (wired when a route declares an rbac:* middleware)" | 2026-11-17 | ✅ |
| 24 | Config wired into Boot + DB fail-closed — `Config::loadSystem()` builds the process-wide typed Config from `BONERADF_*`; `Database::fromEnv()` reads it and THROWS when unset (hardcoded `root/root/local/10028` fallbacks removed); `Boot::ensureSystemReady()` fails boot with the module list when active modules have no DB env | 2026-11-17 | ✅ |

## Doc sync checklist

1. PLAN reflects current REPORT status — verified 2026-11-17 (P0–P5 ✅; post-v1 P6.0–P6.5, P7.0–P7.3, P8.0–P8.4, P9.0–P9.2 ✅).
2. PRD reflects implemented vs planned — verified 2026-11-17 (§2 ORM non-goal amended: no external ORM package; in-tree AR over the builder; module system implemented).
3. REPORT matches actual code state — verified 2026-11-17 (212 tests total: p0–p5 157 + p6 16 + p7 26 + p8 13; `src/Manifest/Modules.php` deleted; module auto-discovery + routing-based activation in `Boot`; `Response::html()`/`Response::view()` + `src/Views/` present; `Config::loadSystem()` wired into `Boot`; `Database::fromEnv()` has no hardcoded fallback DSN; `bone make:module` present; suite p0–p8 212/212).
4. CHANGELOG includes all committed changes — verified 2026-11-17 (`[Unreleased]` covers system DB factory, AR layer + relations, full-MVC sweep, connection-dedupe fix, module auto-discovery + activation switch, HTML view responses).

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

**Last synced: 2026-11-17 — P0–P5 complete; 2.00 MB peak RSS. Post-v1: module system (§5) + Auth + Resources + AR Model + full-MVC layout + module auto-discovery/routing-based activation — suite p0–p8 201/201.**
