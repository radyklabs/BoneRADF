<?php declare (strict_types=1);

/**
 * Shared test-env bootstrap. Selects the `test` named connection from .env
 * (database.test.*). Real env always wins over .env.
 */
putenv ('BONERADF_DB_ENV=test');