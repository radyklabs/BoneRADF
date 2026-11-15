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

## [1.0.0-dev] — Unreleased / In progress

### Added

- Docs scaffold: `PRD.md`, `ARCHITECTURE.md`, `SECURITY.md`, `API_GUIDE.md`,
  `DEPLOY.md`, `PLAN.md`, `CHANGELOG.md`, `REPORT.md`.
- No code changes yet — all work lives under `app/`.
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
- `.gitignore`: added `/sample_frontdev` (frontend test scaffold, separate
  git repo, not part of framework).

### Security

- None yet. Defense-by-default security modules are planned for phase P4.

---

**Last synced: 2026-11-14 — project root moved to app/; docs scaffold complete; no code committed.**
