# PLAN — Post-v1 P9.5: Review-driven Hardening (2026-11-17)

Source: `docs/REVIEW-2026-11-17-v2.md` (Top-5 recommendations + minor cleanup + RBAC wiring).
User decisions (locked 2026-11-17):

| # | Decision | Value |
|---|---|---|
| 1 | `bone make:module` timing | **Now (pre-1.0)** — Rec #1 |
| 2 | `Config` strategy | **Wire `Config` into `Boot` + delete `root/root` fallbacks in `Database`** |
| 3 | DB-availability activation | **Boot fails with a clear message when an active module's DB is unavailable** |
| 4 | `Model::find()` semantics | **`find()` respects the queued `where()`** |
| 5 | RBAC | **Wire `RbacGuard` into the default pipeline for protected routes** (D5 amended to ON-by-default-wired) |
| 6 | Scope | **5 recommendations + all minor cleanup + RBAC wiring; EXCLUDE `modules/Test` cleanup** |

Out of scope: `modules/Test/` WIP cleanup (user's own work-in-progress; leave untouched).

---

## Phase P9.5 — Review-driven hardening (post-v1)

### P9.5.1 — `Model::find()` respects the queued `where()`  (Rec #2, M1)
`src/Database/Model.php`: `find($id)` currently builds a fresh `QueryBuilder` and discards the queued `where()`.
Change: `find($id)` = `where($this->primaryKey, '=', $id)->first()` so a queued `where('status','active')` composes.
- Update `tests/p7.php` — add a case: `where(...)->find($id)` applies both; `find($id)` alone still works.
- Update `Model.php` docblock for `find()`.
- Verify: p0–p8 green (find callers in `UserModel`/`ResourceModel` unaffected — they don't queue `where` first).

### P9.5.2 — `Route::compileRegex()` metachar-escape  (Rec #5 part a, m1)
`src/Router/Route.php:112`: comment promises "Escape anything left that PCRE would treat as a metacharacter"; no code does it.
Change: `preg_quote` the literal (non-placeholder) segments of the pattern before wrapping in `~^…$~`.
- Add a test (p1 or p7): a literal `.` in a pattern no longer matches any char (`/api/a.b` must NOT match `/api/aXb`).

### P9.5.3 — Pipeline middleware compiled at boot, not per-request  (Rec #5 part b, M2)
`src/Kernel/Kernel.php:117-125` + `src/Kernel/Boot.php`: route-specific middleware is re-registered on the shared pipeline every request.
Change: resolve each route's middleware into a per-route compiled list at boot (`Boot`); `Kernel::dispatch` runs the compiled list without mutating the shared pipeline.
- Keep `Pipeline` immutable across requests.
- Verify: p1 middleware-ordering tests still pass; add a test that the shared pipeline is not mutated after two dispatches.

### P9.5.4 — `Config` wired into `Boot` + DB fail-closed  (Rec #4, M3 + C2 + M4-DB)
`src/Config/Config.php` (currently dead on the runtime path) + `src/Kernel/Boot.php` + `src/Database/Database.php`:
- Load `BONERADF_*` env into a typed `Config` instance inside `Boot::init()`; bind it into the `Container`.
- `Database::fromEnv()` reads from the bound `Config` (or `Config` statics) — **delete the `root/root/local/10028` fallbacks**; fail-closed: throw `RuntimeException("BONERADF_DB_DSN is not set")` when unset.
- `AuthGuard` / `CsrfGuard` read their secrets/TTLs from `Config` (kill inline `getenv` at call sites).
- **DB-availability-aware activation**: when a module's `routing.php` is non-empty (active) but `Database::fromEnv()` can't connect, `Boot::init()` throws `RuntimeException("module '<Name>' is active but the database is not configured (BONERADF_DB_DSN unset/unreachable)")` — fail at boot, not 500-per-request.
- Add a test (p8 or new p9): boot with no DB env → clear "database not configured" error naming the module.
- Update `docs/CONFIG.md` / `CONFIG.sample.md` to list the now-required `BONERADF_*` keys.

### P9.5.5 — `bone make:module` generator  (Rec #1, C1)
`bin/bone`: new subcommand `make:module <Name> [--with-db]`.
- Generates `modules/<Name>/{Config/modules.php, Config/routing.php, <Name>Module.php, Controllers/<Name>Controller.php, Models/<Name>Model.php, Services/<Name>Service.php}` with namespaces pre-filled (`Modules\<Name>\…`).
- `--with-db` adds a `seed.php` using `Database::fromEnv()`.
- Refuse to overwrite an existing module (fail-closed).
- Update `CLAUDE.md` custom-commands table + `docs/` (D8 note: `make:module` now shipped pre-1.0, superseding the v1.1 deferral).
- Add a test: run the generator into a temp dir, assert files + namespaces exist; assert "already exists" guard.

### P9.5.6 — RBAC wired into the default pipeline  (D5 amendment, m10)
`src/Security/RbacGuard.php` + `src/Kernel/Boot.php`: currently `RbacGuard` exists but nothing registers it (unreachable at runtime).
- Wire `RbacGuard` into the default pipeline for protected (non-public) routes; deny-by-default per the locked decision.
- Public routes (system `GET /`, `GET /health`) remain unguarded.
- Update CLAUDE.md D5 wording from "RBAC available" to "RBAC ON by default for protected routes (wired)".
- Add a test: an unauthenticated request to a protected route → 403 (deny-by-default).

### P9.5.7 — Minor cleanup batch  (m2–m9)
- **m2** `src/Database/QueryBuilder.php`: `count()` clobbers `select`, `first()` clobbers `limit`. Clone the builder for terminal calls (or restore state after). Add a test.
- **m3** `src/Logger/FileLogger.php`: `redact` is one level deep. Make it recursive. Add a test (`context['user']['password']` redacted).
- **m4** `tests/p8.php`: remove `exec()` (violates RULES.md "no exec()"). Replace the `bone modules` output check with a direct manifest/`routing.php` read.
- **m6** Centralize the duplicated DSN/credentials: `modules/Auth/seed.php`, `modules/Resources/seed.php`, `tests/p6.php` → one `tests/support/db.php` (or read from `Config`). (Seed scripts keep a small local copy only if CLI can't autoload `Config`.)
- **m7** `src/Validator/Validator.php`: `in` rule casts value to string — coerce both sides so `in:admin,2` matches int `2`. Add a test.
- **m8** `src/Kernel/Boot.php` `handler()`: add `method_exists` check → clear `RuntimeException("method 'logn' not found on <Class>")` instead of a generic 500. Add a test.
- **m9** `public/index.php` catch block: replace the HTML `die()` with `Response::error('BOOT_FAILED', …, 500)` emission (keep the `error_log($th)`). The headless JSON contract must hold even on boot failure.

### P9.5.8 — Docs + suite sync
- `CHANGELOG.md` `[Unreleased]`: entries for P9.5.1–P9.5.7 (grouped).
- `PLAN.md` (this file) + `REPORT.md` row 14/15 + decision 23 (RBAC wired) + decision 24 (Config wired / DB fail-closed).
- `ARCHITECTURE.md`: §3 step 6 (middleware compiled at boot), §9 (Config now wired), §5 (module DB-availability activation), D5/D3 notes.
- `RULES.md`: note `make:module` subcommand; "no `exec()`" reaffirmed (p8 now compliant).
- Full suite run: `p0–p9` green (record the new total).

---

## Milestone gate (P9.5 done = ready for 1.0 candidate)

| Gate | Check |
|---|---|
| G1 | `Model::find()` queue-respecting — p7 case added + green |
| G2 | `Route` metachar-escape — dotted-pattern test green |
| G3 | Pipeline not mutated across dispatches — p1 green |
| G4 | `Config` wired; `Database` has no `root/root` fallback; boot fails with a clear DB message — p8/p9 green |
| G5 | `bone make:module` generates a working module skeleton — test green |
| G6 | RBAC wired; protected route → 403 deny-by-default — test green |
| G7 | m2–m9 all fixed; p8 no longer uses `exec()` — p0–p9 green |
| G8 | Docs (CHANGELOG/PLAN/REPORT/ARCHITECTURE/RULES) synced; suite total recorded |

All G1–G8 green → P9.5 ✅ → v1.0.0 candidate (re-run `>release` checklist).

---

## Dependencies & ordering

- P9.5.4 (Config) is a prerequisite for P9.5.5's `--with-db` seed and for the DB-fail-closed message; do it before the generator.
- P9.5.3 (pipeline at boot) should land before P9.5.6 (RBAC wiring) so the RBAC middleware is compiled, not re-registered per request.
- P9.5.1, P9.5.2, P9.5.7 are independent and can parallelize.
- P9.5.8 (docs + suite) is last, after all code + tests.

Order: **P9.5.4 → P9.5.3 → P9.5.6 → P9.5.1 → P9.5.2 → P9.5.7 → P9.5.5 → P9.5.8** (generator last so it emits the new `Config`-based `seed.php`).

---

*Last synced: 2026-11-17 — P9.5 hardening plan drafted from `REVIEW-2026-11-17-v2.md`; 5 user decisions locked; `modules/Test` excluded.*
