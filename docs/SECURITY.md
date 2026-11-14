# BoneRADF — Security Architecture

Status: Accepted (v1 locked)
Scope: Threat model, secure defaults, injection contract, audit checklist for the BoneRADF microservice framework.

---

## 1. Threat Model (STRIDE-lite)

### Assets

- Database (customer/app data, credentials tables)
- Sessions (JWT signing keys, cookie session state)
- API secrets (JWT keys, app HMAC secret, DB DSN credentials in `.env`)
- Rate-limit state (availability)

### Actors

- Unauthenticated external client (internet)
- Authenticated low-privilege client (valid token, limited RBAC)
- Authenticated high-privilege client (admin RBAC)
- Compromised dependency / supply chain (mitigated: zero runtime deps)
- Malicious module author (code running inside `modules/`)

### Trust Boundaries

```
   Internet (untrusted)
        |
        |  HTTP
        v
+-------------------+     Trust boundary 1: TLS termination (edge/proxy)
|  Edge / proxy     |     (BoneRADF assumes HTTPS in prod; HSTS enforced)
+-------------------+
        |
        v
+-------------------+     Trust boundary 2: public/index.php -> Kernel
|  public/          |     (only entry; everything else is internal)
|  index.php        |
+-------------------+
        |
        v
+-------------------+     Trust boundary 3: middleware pipeline
|  Middleware       |     (SecurityHeaders, CSRF, Auth, RBAC, RateLimit
|  (security ON)    |      run BEFORE any handler sees the request)
+-------------------+
        |
        v
+-------------------+     Trust boundary 4: handler -> container -> DB
|  Handler / Domain |     (prepared statements only; no raw SQL API)
|  Query builder    |
+-------------------+
        |
        v
   Database (trusted store; parameterized queries only)
```

STRIDE mapping (lite):

| Threat              | Primary control                                   |
|---------------------|---------------------------------------------------|
| Spoofing            | JWT verify + signed cookie session (Auth module)  |
| Tampering           | HMAC-signed cookies; JWT signature check          |
| Repudiation         | Structured logs with `request_id` correlation     |
| Information disclosure | Uniform error envelope (no stack traces/secrets); headers |
| Denial of service   | RateLimit module (per-IP + per-token)             |
| Elevation of privilege | RBAC deny-by-default gate                       |

## 2. Injection Vectors Matrix

| Vector             | Status in core                                             | Control / rule                                             |
|--------------------|------------------------------------------------------------|------------------------------------------------------------|
| SQLi               | Mitigated                                                  | PDO-only builder; only query path is `execute($sql, $params)`; no raw SQL API in core; identifiers allowlisted |
| XSS                | N/A (by design)                                            | JSON-only responses, no HTML output, no views/templates    |
| Command injection  | Forbidden in core                                          | No `exec`/`passthru`/`system`/`shell_exec`/`proc_open` in core (rule, §10); modules must not add shell calls without review |
| Path traversal     | Mitigated                                                  | Module loader restricts to `modules/` dir; canonicalize + prefix check before include |
| SSRF               | Not in v1 core                                             | Any outbound HTTP module MUST validate URL against allowlist + block private ranges (planned control; not yet implemented) |
| Template injection | N/A (by design)                                            | No templating engine in framework                          |
| Deserialization    | Forbidden in core                                          | No `unserialize()` in core (rule, §10); use JSON only      |

## 3. Secure Defaults

Every security module is ON by default. Disabling is explicit and loud.

| Module         | ON by default | How to disable                     | What you lose                                              |
|----------------|---------------|------------------------------------|------------------------------------------------------------|
| SecurityHeaders| Yes           | `SECURITY_HEADERS=false` in config | Browser-side XSS/clickjacking/mime-sniff protections; HSTS |
| CSRF           | Yes           | `CSRF=false` in config             | Cross-site request forgery protection on state-changing methods (GET stays safe) |
| Auth           | Yes           | `AUTH=false` in config             | Request identity; every route becomes public — only for fully public read-only services |
| RBAC           | Yes           | `RBAC=false` in config             | Deny-by-default authorization; all authenticated actions become permitted |
| RateLimit      | Yes           | `RATE_LIMIT=false` in config       | Brute-force and abuse protection; DoS resistance at app layer |

Rule: disabling a security module requires an explicit config flag, never a code path. Production boot should warn (planned: loud log warning on any disabled module in `APP_ENV=prod`).

## 4. Authentication (AuthN)

Two mechanisms, both in the Auth module:

### JWT

- Algorithms: HS256 (shared secret) and RS256 (key pair). `none` and other algos rejected.
- Verification: signature check + `exp` + `nbf` + `iss`/`aud` (if configured). Algorithm pinned per config — no alg negotiation from token header.
- Refresh policy (planned): short-lived access token (default 15 min, config) + refresh token rotated on use; refresh token reuse = revoke family (one-time refresh tokens). Logout revokes refresh server-side (planned: token store or denylist — TBD, no queue/worker in v1 so store-based revocation).

### Cookie sessions

- Signed cookie: HMAC-SHA256 with app secret (§9); value tampering fails verification.
- Flags: `httponly=true` always; `samesite=Strict` default (`Lax` configurable for cross-site navigation cases); `secure=true` in prod (enforced when `APP_ENV=prod`).
- Cookie contents are opaque session identifiers — no PII or roles in the cookie body.

## 5. Authorization (AuthZ) — RBAC

- Model: roles → permissions (string keys, e.g. `users.create`).
- Gate: middleware checks the required permission for the route before the handler runs.
- Deny-by-default: no permission declared = denied. Missing RBAC config = deny, never allow.
- Privilege changes take effect on token refresh (documented; no live revocation in v1 beyond refresh-token rotation).

## 6. CSRF

- Token delivered via header (`X-CSRF-Token`) + double-submit cookie (non-`httponly` so JS can read the header value; cookie itself signed).
- Same-site: `Strict` (or `Lax` if configured) on the session cookie complements the token check.
- Scope: module ON by default for state-changing methods (`POST`, `PUT`, `PATCH`, `DELETE`). `GET`/`HEAD` must be safe (no state change) — enforced by convention + audit.
- Failure → `403 CSRF_TOKEN_INVALID` in the uniform envelope.

## 7. Rate Limiting

- Algorithm: sliding window.
- Dimensions: per-IP (default) and per-token (authenticated) — both tracked when Auth is on.
- Config (planned): `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW` (seconds), per-route overrides.
- Response headers on every limited route:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1767139200
```

- Exceeded → `429 RATE_LIMITED` + `Retry-After`.
- State store: in-memory (per worker) in v1 — noted limitation in §11.

## 8. Security Headers (exact list)

Set by SecurityHeaders module, recommended values:

| Header                      | Recommended value                                            |
|-----------------------------|--------------------------------------------------------------|
| `Content-Security-Policy`   | `default-src 'none'; frame-ancestors 'none'` (JSON API — no scripts; tighten/extend if serving anything else) |
| `Strict-Transport-Security` | `max-age=63072000; includeSubDomains; preload` (prod only)   |
| `X-Frame-Options`           | `DENY`                                                       |
| `X-Content-Type-Options`    | `nosniff`                                                    |
| `Referrer-Policy`           | `no-referrer`                                                |
| `Cross-Origin-Opener-Policy`| `same-origin`                                                |
| `Cross-Origin-Resource-Policy` | `same-origin`                                              |
| `Cross-Origin-Embedder-Policy` | `require-corp`                                             |
| `Permissions-Policy`        | `geolocation=(), microphone=(), camera=()`                   |

No `X-XSS-Protection` (obsolete; CSP is the control).

## 9. Cookies & Secrets

### Cookie signature

- Scheme: `value.hmac_sha256(value, app_secret)` (planned encoding: base64url for cookie safety).
- Verification: constant-time compare (`hash_equals`) of recomputed MAC.
- Rotation: introduce new secret as primary, keep previous as verifier during a bounded overlap window; re-issue cookies on next request with new secret; drop old secret after max cookie lifetime.

### Secrets management

- All secrets live in `.env` (or env vars) per service — never in code, never in config committed to VCS.
- Never logged: secure coding rule (§10); log redaction for keys matching `SECRET|TOKEN|PASSWORD|KEY`.
- Per-service secret: each install has its own app secret and JWT keys — compromise does not cross services.
- Rotation runbook (planned, per service):
  1. Generate new secret; add as secondary verifier.
  2. Deploy; new tokens/cookies signed with new secret.
  3. Wait > max token/cookie lifetime.
  4. Remove old secret; force re-login if overlap exceeded.

## 10. Secure Coding Rules (the injection-hack-free contract)

Mandatory for core and any accepted module:

1. No `eval()`, no `assert()` with string args, no `create_function`.
2. No unescaped user strings in SQL — bound parameters only; identifiers from allowlist.
3. No `exec` / `passthru` / `system` / `shell_exec` / `proc_open` in core (and none in modules without explicit security review).
4. No `unserialize()` on any input — JSON only.
5. No dynamic property access / `$$var` variable variables on untrusted input.
6. `declare(strict_types=1)` in every PHP file.
7. Input validation at the boundary: every request body/query/path param passes the strict Validator before reaching a handler.
8. No secrets in code, logs, or error messages.
9. No raw SQL API in core — the query builder's only execute path takes `(sql, params)`.

## 11. Audit Checklist (pre-release)

- [ ] All five security modules ON in default config; disable paths documented.
- [ ] JWT alg pinned; `none` rejected; `exp` enforced.
- [ ] Cookie flags: `httponly`, `secure` (prod), `samesite` set; HMAC verified with `hash_equals`.
- [ ] CSRF enforced on all state-changing routes; token compared constant-time.
- [ ] RBAC deny-by-default: route with no permission = 403.
- [ ] RateLimit headers present; 429 path tested.
- [ ] Error envelope leaks no stack traces, SQL, paths, or secrets in any environment.
- [ ] Module loader rejects paths outside `modules/` (traversal test).
- [ ] Grep gate: no `eval(`, `unserialize(`, `exec(`, `system(`, `passthru(`, `shell_exec(`, `proc_open(` in core.
- [ ] `.env` not committed; secrets absent from logs (sample log review).
- [ ] `request_id` present on error responses and correlated in logs.

## 12. Known Risks & Residual Risk (honest)

- **No live session revocation in v1**: without a queue/worker, logout before access-token expiry relies on short TTL + refresh-token rotation. Long-lived stolen access tokens remain valid until `exp`. Mitigation: keep access TTL short (15 min default).
- **Rate-limit state is per-worker (in-memory)**: limits multiply by worker count; not a global cap. Redis-backed limiter only if `ext-redis` available (planned; not guaranteed in v1).
- **Modules are trusted code**: a malicious module runs with full process privileges. Path restriction limits traversal, not capability. v1 has no module sandbox — module review is a human process.
- **No SSRF controls in core**: outbound HTTP is not in v1 core; any future HTTP module must implement allowlisting or it becomes an SSRF hole.
- **Dependencies**: zero runtime deps removes most supply-chain risk, but PHP extensions (PDO, openssl) are still trusted platform components.
- **Not a substitute for edge controls**: TLS termination, WAF, and network-level DoS protection live outside BoneRADF.

Last synced: 2026-11-14 — project root moved to app/; decisions from user Q&A locked.
