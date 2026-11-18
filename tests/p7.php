<?php

declare(strict_types=1);

/**
 * P7 verification tests: Database::fromEnv (factory) + AR Model (CRUD,
 * eager relations) — arrays out, prepared statements only.
 *
 * Run:  php -d extension=pdo_mysql tests/p7.php
 * Exits non-zero on any failure.
 *
 * Uses dedicated p7_* tables in the local sample DB (idempotent, self-contained).
 */

require dirname(__DIR__) . '/src/Kernel/Boot.php';

// Ensure the fail-closed Config path is satisfied for this test.
require dirname(__DIR__) . '/tests/env.php';

Boneradf\Kernel\Boot::withoutModules();

use Boneradf\Database\Database;
use Boneradf\Database\Model;

$pass = 0;
$fail = 0;

function check (string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}\n";
    }
}

// ------------------------------------------------------------------
// Test model declarations (ar-arrays: rows stay arrays)
// ------------------------------------------------------------------

final class P7Author extends Model
{
    protected string $table = 'p7_author';

    public function posts (): \Boneradf\Database\Relation
    {
        return $this->hasMany (P7Post::class, 'author_id');
    }

    public function firstPost (): \Boneradf\Database\Relation
    {
        return $this->hasOne (P7Post::class, 'author_id');
    }
}

final class P7Post extends Model
{
    protected string $table = 'p7_post';

    public function author (): \Boneradf\Database\Relation
    {
        return $this->belongsTo (P7Author::class, 'author_id');
    }

    public function tags (): \Boneradf\Database\Relation
    {
        return $this->belongsToMany (P7Tag::class, 'p7_post_tag', 'post_id', 'tag_id');
    }
}

final class P7Tag extends Model
{
    protected string $table = 'p7_tag';
}

/** Counts prepare() calls = executed queries (N+1 eager guard probe). */
final class P7CountingPdo extends PDO
{
    public int $prepares = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepares++;
        return parent::prepare($query, $options);
    }
}

// ------------------------------------------------------------------
// Setup: dedicated tables + known fixture rows (idempotent)
// ------------------------------------------------------------------

$pdo = Database::fromEnv ();

check ('factory: fromEnv memoizes one PDO', Database::fromEnv () === $pdo);

$pdo->exec ('DROP TABLE IF EXISTS `p7_post_tag`');
$pdo->exec ('DROP TABLE IF EXISTS `p7_post`');
$pdo->exec ('DROP TABLE IF EXISTS `p7_tag`');
$pdo->exec ('DROP TABLE IF EXISTS `p7_author`');
$pdo->exec ('CREATE TABLE `p7_author` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec ('CREATE TABLE `p7_post` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `author_id` INT NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_post_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec ('CREATE TABLE `p7_tag` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec ('CREATE TABLE `p7_post_tag` (
    `post_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// authors: 1 salim, 2 radyk, 3 lonely (no posts) — posts: 1,2 → a1 ; 3 → a2
$pdo->exec ("INSERT INTO `p7_author` (`id`, `name`) VALUES (1, 'salim'), (2, 'radyk'), (3, 'lonely')");
$pdo->exec ("INSERT INTO `p7_post` (`id`, `author_id`, `title`) VALUES
    (1, 1, 'first'), (2, 1, 'second'), (3, 2, 'third')");
$pdo->exec ("INSERT INTO `p7_tag` (`id`, `name`) VALUES (1, 'php'), (2, 'api')");
$pdo->exec ('INSERT INTO `p7_post_tag` (`post_id`, `tag_id`) VALUES (1, 1), (1, 2), (2, 1)');

$authors = new P7Author ($pdo);
$posts   = new P7Post ($pdo);
$tags    = new P7Tag ($pdo);

// ------------------------------------------------------------------
// CRUD
// ------------------------------------------------------------------
$newId = $authors->insert (['name' => 'tempo']);
check ('insert: returns int id', is_int ($newId) && $newId > 0);

$row = $authors->find ($newId);
check ('find: returns row array', is_array ($row) && ($row['name'] ?? null) === 'tempo');

$affected = $authors->where ('id', '=', $newId)->update (['name' => 'tempo2']);
check ('update: chain where + update → 1 affected', $affected === 1);
check ('update: value persisted', ($authors->find ($newId)['name'] ?? null) === 'tempo2');

$deleted = $authors->where ('id', '=', $newId)->delete ();
check ('delete: chain where + delete → 1 affected', $deleted === 1);
check ('delete: row gone', $authors->find ($newId) === null);

check ('count: total authors = 3', $authors->count () === 3);

$all = $authors->orderBy ('id', 'ASC')->get ();
check ('chain: select-less get returns all rows', is_array ($all) && count ($all) === 3 && $all[0]['name'] === 'salim');

// chain must reset after a terminal call — next all() has no stale where
$one = $authors->where ('id', '=', 1)->get ();
check ('chain: where applied', count ($one) === 1);
$after = $authors->get ();
check ('chain: state reset after terminal (get returns 3 rows)', count ($after) === 3);

check ('first: single row or null', is_array ($authors->where ('id', '=', 2)->first ()));

// ------------------------------------------------------------------
// Eager: hasMany
// ------------------------------------------------------------------
$withPosts = $authors->with ('posts')->orderBy ('id', 'ASC')->get ();
check ('hasMany: author1.posts has 2 rows', count ($withPosts[0]['posts'] ?? []) === 2);
check ('hasMany: author2.posts has 1 row', count ($withPosts[1]['posts'] ?? []) === 1);
check ('hasMany: no cross-leak (a2 owns only "third")',
    isset ($withPosts[1]['posts'][0]) && $withPosts[1]['posts'][0]['title'] === 'third');
check ('hasMany: lonely author3.posts defaults to []',
    ($withPosts[2]['posts'] ?? null) === []);

// ------------------------------------------------------------------
// Eager: hasOne
// ------------------------------------------------------------------
$withFirst = $authors->with ('firstPost')->orderBy ('id', 'ASC')->get ();
check ('hasOne: attached as array row', is_array ($withFirst[0]['firstPost'] ?? null)
    && ($withFirst[0]['firstPost']['title'] ?? null) === 'first');
check ('hasOne: null when no child', ($withFirst[2]['firstPost'] ?? null) === null);

// ------------------------------------------------------------------
// Eager: belongsTo
// ------------------------------------------------------------------
$withAuthor = $posts->with ('author')->orderBy ('id', 'ASC')->get ();
check ('belongsTo: post1.author is salim array', is_array ($withAuthor[0]['author'] ?? null)
    && ($withAuthor[0]['author']['name'] ?? null) === 'salim');
check ('belongsTo: post3.author is radyk array', is_array ($withAuthor[2]['author'] ?? null)
    && ($withAuthor[2]['author']['name'] ?? null) === 'radyk');

// orphan post → author null
$posts->insert (['author_id' => 999, 'title' => 'orphan']);
$withOrphan = $posts->where ('title', '=', 'orphan')->with ('author')->get ();
check ('belongsTo: missing parent → null', array_key_exists ('author', $withOrphan[0])
    && $withOrphan[0]['author'] === null);
$posts->where ('title', '=', 'orphan')->delete ();

// ------------------------------------------------------------------
// Eager: belongsToMany (pivot)
// ------------------------------------------------------------------
$withTags = $posts->with ('tags')->orderBy ('id', 'ASC')->get ();
$post1Tags = array_column ($withTags[0]['tags'] ?? [], 'name');
sort ($post1Tags);
check ('belongsToMany: post1 → php+api', $post1Tags === ['api', 'php']);
check ('belongsToMany: post3 → [] (no pivot rows)', ($withTags[2]['tags'] ?? []) === []);
check ('belongsToMany: post2 → php only',
    array_column ($withTags[1]['tags'] ?? [], 'name') === ['php']);

// eager query-count sanity (N+1 guard): counting PDO counts prepare() calls
$dsnC   = getenv ('BONERADF_DB_DSN') ?: 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
$userC  = getenv ('BONERADF_DB_USER') ?: 'root';
$dbPass = getenv ('BONERADF_DB_PASS') ?: 'root';
$counting = new P7CountingPdo ($dsnC, $userC, $dbPass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
$probeAuthor = new P7Author ($counting);
$probeAuthor->with ('posts')->get ();
check ('eager: hasMany costs 2 queries total (1 main + 1 eager), not 1 per row',
    $counting->prepares === 2);

// ------------------------------------------------------------------
// Fail-loud on unknown relation
// ------------------------------------------------------------------
$threw = false;
try {
    $authors->with ('bogusRelation')->get ();
} catch (RuntimeException) {
    $threw = true;
}
check ('with(): unknown relation → RuntimeException', $threw);

echo "\n{$pass} passed, {$fail} failed.\n";
exit ($fail > 0 ? 1 : 0);
