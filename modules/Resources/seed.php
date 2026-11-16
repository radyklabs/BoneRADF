<?php

declare(strict_types=1);

/**
 * modules/Resources/seed.php — idempotent sample bootstrap for the Resources
 * module.
 *
 * Creates table `resources` in DB `local` and seeds three rows. Safe to
 * re-run: table is created with IF NOT EXISTS; seed rows are inserted with
 * INSERT IGNORE on the unique `name` column.
 *
 * Run:  php -d extension=pdo_mysql modules/Resources/seed.php
 */

require dirname(__DIR__, 2) . '/src/Kernel/autoload.php';

use Boneradf\Database\ConnectionFactory;

$dsn  = 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
$pdo  = (new ConnectionFactory($dsn, 'root', 'root'))->connect();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `resources` (
        `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(191)    NOT NULL,
        `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_resources_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$seeds = ['Alpha', 'Beta', 'Gamma'];
$stmt  = $pdo->prepare('INSERT IGNORE INTO `resources` (`name`) VALUES (?)');
foreach ($seeds as $name) {
    $stmt->execute([$name]);
}

echo "Seed: table `resources` ready in DB `local`.\n";
echo 'Seed: ' . count($seeds) . " sample rows present (Alpha, Beta, Gamma).\n";
