<?php declare (strict_types=1);

/**
 * Shared DB connection helper for seed scripts + p6.
 *
 * Uses `Database::fromEnv()` so credentials come from BONERADF_DB_* (set by
 * the runtime; for CLI seeds, export them first — the helper prints a clear
 * message when the env is unset).
 */
function boneradf_test_pdo (): PDO
{
    if (!getenv ('BONERADF_DB_DSN')) {
        fwrite (STDERR, "boneradf DB: BONERADF_DB_DSN is not set. Export it before running this script.\n");
        exit (1);
    }
    return Boneradf\Database\Database::fromEnv ();
}