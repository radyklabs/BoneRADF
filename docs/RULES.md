# RULES.md — BoneRADF Development Rules

This file is the source of truth for BoneRADF development conventions.
Read it before working on this repository. `app/docs/CONFIG.md` holds
local environment specifics (PHP path, .env values) — never commit it;
`app/docs/CONFIG.sample.md` is the template.

**Files tracked for sync:** `app/docs/PLAN.md`, `app/docs/PRD.md`,
`app/docs/REPORT.md`, `app/docs/CHANGELOG.md`.

## Debugging Principle (non-negotiable)

- **Fixing ≠ trimming.** Never delete or stub out a failing line to make a
  bug disappear. Deleting the trigger hides the defect and guarantees it
  returns.
- Root cause only: find why the line/feature fails, fix the actual defect,
  and confirm with a failing-before / passing-after check under the micro
  test runner.
- Never simplify a route or module to a trivial case to dodge a kernel bug;
  protect or fix the kernel instead.

## Scope and User-Change Protection (non-negotiable)

- Before editing, deleting, moving, or otherwise changing files, inspect the
  working-tree changes and distinguish pre-existing user changes from changes
  made for the current task.
- Never overwrite, revert, delete, move, or otherwise alter pre-existing user
  changes. If a file has changes outside the explicitly requested scope, do
  not touch it without the user's explicit confirmation.
- Keep work focused on the requested task and files that evidence shows are
  implicated. Do not assume other files or code are faulty; verify the
  specific cause from relevant code, errors, or tests before expanding
  investigation or change scope.
- Expand the scope only when concrete evidence shows it is necessary. Explain
  the evidence and get the user's confirmation before modifying files outside
  the task scope.

## Decision Framework

For logic or major changes:
1. Verify the current code state by reading the actual files.
2. Cross-reference the four project documents (PLAN, PRD, REPORT, CHANGELOG).
3. If a decision is needed, ask the user to choose between clear options before
   proceeding (use `AskUserQuestion` when the choice is not conventional).
4. Record the decision in PLAN, REPORT, and CHANGELOG.
5. Do not execute code or implement the change until the user confirms.

## Secure Coding (injection-hack-free contract)

- No `eval()`, no `assert()` with string args.
- No `exec()`, `passthru()`, `system()`, `shell_exec()`, `proc_open()`.
- No `unserialize()` on untrusted data.
- Prepared statements ONLY — never concatenate user input into SQL.
- `declare(strict_types=1)` in every PHP file.
- Validate and type-check all input at the trust boundary (request entry).
- Never pass user-controlled strings into SQL or logs without binding.
- Secrets never in code, never in logs.

## Testing

- Use the built-in micro test runner (D7, D10). No PHPUnit, no Pest, no
  external test framework.
- Every module ships with its own acceptance tests under `tests/`.
- A failing assertion exits non-zero; the runner prints pass/fail counts.
- "Tests pass" is a claim — show the failing-then-passing output.

## File & Code Conventions

- PSR-4 under `Boneradf\` (e.g. `Boneradf\Router\...` → `src/Router/...`).
- Module layout: `modules/<Name>/` = `Controllers/` (HTTP only), `Models/`
  (table access — the only SQL in a module), `Services/` (business logic,
  zero SQL), `Config/`, `{Name}Module.php` (bootstrap, wiring only). DB
  settings are system-level (`Database::fromEnv()` via `Config`), never in modules.
  A module is auto-discovered from `Config/modules.php`; its `routing.php`
  is both the route contributions AND the activation switch (empty = inert).
  A module that needs the DB but has no `BONERADF_DB_DSN` fails **boot** with
  the affected-module list, not a per-request 500. Use
  `bone make:module <Name> [--with-db]` to scaffold, and set `BONERADF_*`
  env vars (see `CONFIG.sample.md`) before running seeds/tests.
- One service per project install (D4).
- Defense-by-default: secure options are the defaults; opting out is explicit.
- Boring over clever. Minimal diff. No drive-by refactors.
- Match existing style; no second way of doing something that already exists.
- Mark deliberate simplifications with a `ponytail:` comment naming the
  ceiling and upgrade path.

## Documentation Sync

On every task change, QA decision, code commit, or architecture decision,
update all affected docs in `app/docs/` (PLAN, PRD, REPORT, CHANGELOG) and
verify: PLAN matches REPORT status; PRD reflects implemented vs planned;
REPORT matches actual code state; CHANGELOG includes all committed changes.

## Status Legend

- ✅ Done
- ⚠️ Open
- 🔵 Info
- 🟠 Major
- 🔴 Critical
