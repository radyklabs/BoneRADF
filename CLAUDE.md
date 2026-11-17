# CLAUDE.md — BoneRADF Project Instructions

## Role
Senior PHP & API microservice engineer.

## Stack
PHP 8.1+. Zero runtime dependencies. No Composer at runtime (custom PSR-4
autoloader; `composer.json` is optional, dev-time only).

## Project Overview
BoneRADF is a zero-dependency, headless JSON/REST API microservice framework.
No views, no HTML — one install equals one microservice. Entry points:
`public/index.php` (front controller) and the `bone` CLI binary. Namespace
root is `Boneradf\`.

## Project Rules

Always read `app/docs/RULES.md` before working on this repository and follow
its conventions. Read `app/docs/CONFIG.md` for local PHP paths and .env
values. Use `app/docs/CONFIG.sample.md` as the template; never commit
`app/docs/CONFIG.md`.

**Files tracked for sync:** `app/docs/PLAN.md`, `app/docs/PRD.md`,
`app/docs/REPORT.md`, `app/docs/CHANGELOG.md`.

## Directory Layout

### Present
```
app/      — BoneRADF project root (this file, docs, src, modules, tests)
  CLAUDE.md   — this file
  docs/       — project documentation (sync set + RULES + CONFIG below)
  public/     — front controller (composition root) + webroot
  src/        — PSR-4 root for Boneradf\ (core framework)
  modules/    — development layer: PSR-4 modules (namespace Modules\<Name>\)
  bin/        — CLI binary: bone
  tests/      — micro test suite (p0–p6)
  sample_frontdev/ — frontend test scaffold (gitignored, not part of framework)
conf/     — apache / php-fpm config templates; NOT part of BoneRADF
logs/     — legacy logs; NOT part of BoneRADF
```

### docs/ sync set
```
app/docs/PLAN.md            — roadmap & task tracking
app/docs/PRD.md             — product requirements
app/docs/REPORT.md          — audit/status report
app/docs/CHANGELOG.md       — version history
app/docs/RULES.md           — development conventions (always read first)
app/docs/CONFIG.md          — local env (never commit)
app/docs/CONFIG.sample.md   — config template
app/docs/…                  — additional docs as created
app/CLAUDE.md               — this file
```

### src/ + modules/ layout (present)
```
public/index.php          — front controller / composition root (boots modules)
bin/bone                  — CLI binary: routes, modules, serve
src/                      — PSR-4 root for Boneradf\ (core framework)
  Kernel/                 — bootstrap & request lifecycle
  Router/                 — routing, `:param` syntax
  Middleware/             — PSR-15-style pipeline (Pipeline)
  Container/              — light DI, explicit bindings only
  Config/                 — typed config, env > file > defaults
  Logger/                 — structured logging (FileLogger)
  Database/               — PDO query builder + AR Model/Relation (prepared
                            only, arrays out) + Database::fromEnv factory
  Validator/              — strict validation, 422 on fail
  Security/               — SecurityHeaders, CSRF, Auth (JWT HS256), RBAC,
                            RateLimit, CorsMiddleware — ON by default
  Cache/                  — in-memory + file drivers only in v1 (Cache facade + drivers)
  Test/                   — built-in micro test runner
  Contracts/              — interfaces + traits (Module, Middleware, Logger, CacheDriver)
  Controller/             — core controllers (DefaultController)
  Manifest/               — data-only manifests (system route aliases)
  Views/                  — opt-in HTML view files (public landing, Response::view)
modules/                  — development layer (PSR-4, namespace Modules\<Name>\)
  Auth/                   — first module: login + refresh + CORS + users seed
    AuthModule.php        — module bootstrap class (implements Boneradf\Contracts\Module)
    Controllers/          — HTTP handlers only (namespace Modules\<Name>\Controllers)
    Models/               — table access via AR Model — the only SQL in a module
    Services/             — business logic, zero SQL (namespace \Services)
    seed.php              — idempotent `users` table + sample user bootstrap
tests/                    — micro test suite p0–p8
```
```

## Docs Sync Rule
`app/docs/PLAN.md`, `app/docs/PRD.md`, `app/docs/REPORT.md`,
`app/docs/CHANGELOG.md` MUST stay synchronized at all times.

Sync triggers — update all affected docs on:
- task created / started / completed
- QA decision made
- code committed
- architecture decision recorded

Before finishing any task touching docs, verify: PLAN matches REPORT status;
PRD reflects implemented vs planned; REPORT matches actual code state;
CHANGELOG includes all committed changes.

## Debugging Principle (non-negotiable)

- **Fixing ≠ trimming.** Never delete or stub out a failing line to make a
  bug disappear. Root cause only; confirm with failing-before /
  passing-after check under the micro test runner.
- Never simplify a route or module to a trivial case to dodge a kernel bug;
  protect or fix the kernel instead.

## Scope & User-Change Protection (non-negotiable)

- Inspect working-tree changes before editing; distinguish pre-existing user
  changes from task changes.
- Never overwrite, revert, delete, or alter pre-existing user changes without
  explicit user confirmation.
- Expand scope only on concrete evidence; explain the evidence and confirm
  with the user before touching files outside the task.

## Locked Decisions
Do not revisit without explicit user approval:
1. PHP 8.1+, ZERO runtime deps; custom PSR-4 autoloader; `composer.json`
   optional dev-time only, never a runtime requirement.
2. Headless JSON/REST API framework. No views/HTML. One install = one
   microservice.
3. Namespace `Boneradf\`. Entry: `public/index.php` front controller + `bone`
   CLI binary.
4. Core: Kernel, Router, Middleware (PSR-15 style), light DI, typed Config,
   structured Logger, PDO query builder (prepared statements ONLY — no raw
   query API in core), strict Validator.
5. Security modules ON by default: SecurityHeaders, CSRF, Auth (JWT HS256 +
   signed cookie sessions), RBAC, RateLimit (fail-closed).
6. Cache: in-memory + file only in v1; redis deferred. Built-in micro test
   runner. CLI `bone` (routes, modules, serve). No queue/worker system in v1.
7. Project root is `app/` — not repo root. All BoneRADF code lives under `app/`.
8. Memory target: **< 25 MB RSS** for booted kernel (PHP 8.1 FPM + opcache).
9. Config precedence: **env > config file > framework defaults**; secrets
   always from env.
10. Router param syntax: **`:id`** (colon). Validation failure: **422**.
    Rate-limit storage failure: **fail-closed**. PSR: **native interfaces,
    PSR-shaped by convention** (zero-dep D2). DI: **explicit bindings only**
    (no reflection autowiring). `bone` v1 subcommands: **routes, modules,
    serve** (`test` + `make:*` deferred to v1.1). JWT: **HS256, access 1h +
    refresh 7d, secret from env**.

## Secure Coding Rules (injection-hack-free contract)
- No `eval()`, no `assert()` with string args.
- No `exec()`, `passthru()`, `system()`, `shell_exec()`, `proc_open()`.
- No `unserialize()` on untrusted data.
- Prepared statements ONLY — never concatenate user input into SQL.
- `declare(strict_types=1)` in every PHP file.
- Validate and type-check all input at the trust boundary (request entry).
- Never pass user-controlled strings into SQL or logs without binding.
- Secrets never in code or logs.

## Locked Files (out of scope)
`conf/`, `logs/`, `sql/`, and `.envrc` contain legacy apache/php-fpm config
templates, log files, and the LocalWP WordPress DB dump. They are NOT part of
BoneRADF.
Do not edit, refactor, or delete anything under them unless explicitly asked.
Future sessions: ignore these paths for all BoneRADF work.

## sample_frontdev (frontend test scaffold)
`sample_frontdev/` is a gitignored subdirectory used for frontend testing
against the headless framework. It is a separate git repo (not part of the
BoneRADF codebase). Framework work never modifies it; frontend test work
targets this directory.

## Branching Model
- `main` — release-ready only; never commit WIP directly.
- `dev` — working branch; feature work merges here.
- `feat/<slug>` — new feature branches from `dev`.
- `fix/<slug>` — bug-fix branches from `dev`.
- `hotfix/<slug>` — urgent production fixes branch from `main`, merge to
  `main` + `dev`.
- `patch/<slug>` — small fixes / docs / chores from `dev`.
- Conventional commits: `feat:`, `fix:`, `docs:`, `refactor:`, `test:`,
  `chore:`, `security:`. No `Co-authored-by:` trailers.

## Custom Commands

| Command | Action |
|---------|--------|
| `>branch <description>` | Before branching: check the current branch for uncommitted files (`git status`) or unpushed commits (`git log @{u}..`); if either is non-empty, run `>push` first. Then choose a concise prefixed kebab-case branch name (`feat/`, `fix/`, `patch/`), verify it is unused, and create and switch to it from the current branch. |
| `>push` | Inspect `git status` + diff, add a per-file summary under `[Unreleased]` in `app/docs/CHANGELOG.md`, then stage, commit (conventional), and push the current branch to `origin`. |
| `>sync` | Run `>push`, rebase/merge against `dev`, open a PR into `dev`, merge (squash or merge commit), then delete the merged branch. |
| `>track` | Compare `app/docs/PLAN.md` against actual code state; update PLAN + REPORT + CHANGELOG statuses; commit and push. |
| `>audit` | Deep audit: manual file-by-file, line-by-line review of all in-scope text files under `app/` (source, tests, config, docs); tools (micro test runner, lint) are **evidence only** — a green run is never the audit itself. Read every in-scope file end to end; keep a coverage ledger. Trace execution paths per entry point. Correlate definitions ↔ consumption across files (config keys, registry, interfaces, tests ↔ behavior, docs ↔ code). Flag dead/unreachable code, junk, and per-request redundant work — state reachability evidence before labeling. Walk every trust boundary (input, routing, authn/authz, output escaping, SQL parameterization, path guards, cache/FS writes, secrets, error disclosure, CI supply chain); for each, state the guard (file:line) or the gap. Note anomalies (style drift, stale comments, version skew). Classify findings CONFIRMED / HYPOTHESIS / INTENTIONAL-TRADE-OFF with severity. Record in `app/docs/REPORT.md` (dated entry, branch + commit, scope, coverage ledger, findings with remediation + verification plan). Update PLAN + CHANGELOG. Do not silently fix during audit. |
| `>release` | One invocation prepares, merges, and tags in one pass. Requires a clean `dev` branch. Verify release readiness (tests green, CHANGELOG complete, `vX.Y.Z` tag does not exist). Review the `dev` → `main` diff; stop if it includes unrelated changes. Move `[Unreleased]` entries to `## [X.Y.Z] - YYYY-MM-DD` in `app/docs/CHANGELOG.md`, add a fresh empty `[Unreleased]` section, update version. Commit as `chore(release): X.Y.Z`, push `dev`, open a PR to `main` titled `chore(release): X.Y.Z`. Merge with `gh pr merge --merge` when rules permit; if blocked, stop and report the blocker. Do not tag on `dev`. Tag creation is owned by CI (`.github/workflows/release-tag.yml`, deferred — see below) or manual `git tag vX.Y.Z -m "..."` + `git push origin vX.Y.Z` as a fallback until CI exists. Verify the tag points to the exact merge commit before reporting success. |

### `>release` — CI note
The `release-tag.yml` GitHub Actions workflow is **deferred** until v1.0
candidate (M3). Until it exists, `>release` uses the manual-tag fallback.
Do not create or commit the workflow during regular development.

## Status Legend
- ✅ Done
- ⚠️ Open
- 🔵 Info
- 🟠 Major
- 🔴 Critical

## Precedence
This project file governs BoneRADF work in this repo. The global `CLAUDE.md`
rules (no hallucination, no assumptions, surgical changes, docs sync,
reporting discipline) still apply. On conflict, this file wins for
project-specific decisions; global rules always win for universal discipline.
`app/docs/RULES.md` refines the conventions in this file; when they disagree,
RULES.md wins for coding conventions, this file wins for structural /
branching / command decisions.
