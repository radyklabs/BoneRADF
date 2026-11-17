<?php declare (strict_types=1);

/**
 * Shared test-env bootstrap. Call this at the top of any test that boots
 * the kernel or calls Database::fromEnv() — it sets the BONERADF_* env
 * vars to the dev-only LocalWP DSN so the fail-closed Config path is
 * satisfied without a real .env file.
 *
 * Dev-only values; production overrides via real env.
 */
putenv ('BONERADF_APP_KEY=test-app-key-32-bytes-hex-1234567890ab');
putenv ('BONERADF_DB_DSN=mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4');
putenv ('BONERADF_DB_USER=root');
putenv ('BONERADF_DB_PASS=root');