# CONFIG.sample.md — BoneRADF Local Environment Template

Copy this file to `CONFIG.md` and fill in local values. **Never commit
`CONFIG.md`.**

## PHP

| Key | Value |
|-----|-------|
| PHP binary path | *(e.g. `C:\php\php.exe`)* |
| PHP version | *(e.g. 8.1.x)* |
| opcache | ON |
| FPM pool | *(optional)* |

## .env

Generate locally. File perms `0600`. Never commit.

| Key | Notes |
|-----|-------|
| `BONERADF_APP_KEY` | 32-byte hex |
| `BONERADF_ENV` | `development` / `production` |
| `BONERADF_DB_DSN` | MySQL / MariaDB / SQLite / Postgres DSN |
| `BONERADF_DB_USER` | per-DB |
| `BONERADF_DB_PASS` | per-DB |
| `BONERADF_CACHE_DRIVER` | `file` (default) or `memory` |
| `BONERADF_LOG_PATH` | outside webroot |

## Dev

```bash
php -S 0.0.0.0:8080 -t public
bone serve
```

## Tests

```bash
php bin/bone test
```
