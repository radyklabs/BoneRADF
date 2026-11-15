<?php

declare(strict_types=1);

/**
 * P3 verification tests: PDO query builder + Validator.
 *
 * Run:  php tests/p3.php
 * Exits non-zero on any failure.
 *
 * Note: PDO drivers are not loaded in this environment; the query builder
 * is tested via a mock PDO that records prepared SQL and params.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Database\QueryBuilder;
use Boneradf\Validator\Validator;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
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
// Mock PDO + Statement
// ------------------------------------------------------------------

class MockStatement extends \PDOStatement
{
    private string $sql;
    private array $params;

    public function __construct(string $sql, array $params = [])
    {
        $this->sql = $sql;
        $this->params = $params;
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? $this->params;
        return true;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, ...$args): array
    {
        return [];
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $orientation = \PDO::FETCH_ORI_NEXT, int $offset = 0): false|array
    {
        return false;
    }

    public function rowCount(): int
    {
        return 0;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return true;
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}

class MockPDO extends \PDO
{
    public array $statements = [];

    public function __construct()
    {
        // PDO requires DSN; we don't actually connect, just record
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $stmt = new MockStatement($query);
        $this->statements[] = $stmt;
        return $stmt;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return '1';
    }
}

// ------------------------------------------------------------------
// QueryBuilder tests
// ------------------------------------------------------------------

$pdo = new MockPDO();
$builder = new QueryBuilder($pdo);

// Basic SELECT
$builder->table('users')->select('id', 'name')->where('status', '=', 'active')->orderBy('created_at', 'desc')->limit(10);
$rows = $builder->get();
$stmt = $pdo->statements[0];
$sql = $stmt->getSQL();
check('select has FROM users', str_contains($sql, 'FROM users'));
check('select has columns', str_contains($sql, 'SELECT id, name'));
check('select has WHERE', str_contains($sql, 'WHERE status = :w0'));
check('select has ORDER BY', str_contains($sql, 'ORDER BY created_at DESC'));
check('select has LIMIT', str_contains($sql, 'LIMIT 10'));

// SQLi probe: payload as bound parameter
$builder2 = new QueryBuilder($pdo);
$builder2->table('users')->where('id', '=', "' OR 1=1 --");
$pdo->statements = [];
$builder2->get();
$stmt2 = $pdo->statements[0];
$sql2 = $stmt2->getSQL();
$params2 = $stmt2->getParams();
check('sqli payload is a param, not in SQL', !str_contains($sql2, "' OR 1=1"));
check('sqli payload is bound', in_array("' OR 1=1 --", $params2, true));

// Invalid identifier
$threw = false;
try {
    (new QueryBuilder($pdo))->table('users; DROP TABLE users');
} catch (RuntimeException) {
    $threw = true;
}
check('invalid identifier rejected', $threw === true);

// WHERE IN
$builder3 = new QueryBuilder($pdo);
$builder3->table('users')->whereIn('role', ['admin', 'editor']);
$pdo->statements = [];
$builder3->get();
$stmt3 = $pdo->statements[0];
check('whereIn produces IN clause', str_contains($stmt3->getSQL(), 'IN ('));

// Empty WHERE IN → always-false
$builder4 = new QueryBuilder($pdo);
$builder4->table('users')->whereIn('role', []);
$pdo->statements = [];
$builder4->get();
$stmt4 = $pdo->statements[0];
check('empty whereIn → 1=0', str_contains($stmt4->getSQL(), '1=0'));

// UPDATE
$builder5 = new QueryBuilder($pdo);
$builder5->table('users')->where('id', '=', 42);
$pdo->statements = [];
$affected = $builder5->update(['name' => 'Ada']);
$stmt5 = $pdo->statements[0];
check('update has SET', str_contains($stmt5->getSQL(), 'SET name'));
check('update has WHERE', str_contains($stmt5->getSQL(), 'WHERE'));

// DELETE without WHERE → exception
$threw = false;
try {
    $b = new QueryBuilder($pdo);
    $b->table('users');
    $b->delete();
} catch (RuntimeException) {
    $threw = true;
}
check('delete without WHERE rejected', $threw === true);

// ------------------------------------------------------------------
// Validator tests
// ------------------------------------------------------------------

$v = new Validator(['name' => 'Ada', 'email' => 'ada@example.com', 'age' => 30, 'role' => 'admin']);
$result = $v->validate([
    'name'  => 'required|string|min:2|max:80',
    'email' => 'required|email',
    'age'   => 'required|int|min:0|max:150',
    'role'  => 'required|in:admin,editor,viewer',
]);
check('valid data passes', $result->passes());

$v2 = new Validator(['email' => 'not-an-email', 'age' => 'abc']);
$result2 = $v2->validate([
    'email' => 'required|email',
    'age'   => 'required|int',
]);
check('invalid data fails', $result2->fails());
check('email error present', $result2->fieldErrors('email') !== null);
check('age error present', $result2->fieldErrors('age') !== null);

// Missing required
$v3 = new Validator([]);
$result3 = $v3->validate(['name' => 'required']);
check('missing required fails', $result3->fails());
check('missing required has error', $result3->fieldErrors('name') !== null);

// Pattern
$v4 = new Validator(['slug' => 'abc-123']);
$result4 = $v4->validate(['slug' => 'required|pattern:/^[a-z0-9-]+$/']);
check('pattern pass', $result4->passes());

$v5 = new Validator(['slug' => 'ABC_123']);
$result5 = $v5->validate(['slug' => 'required|pattern:/^[a-z0-9-]+$/']);
check('pattern fail', $result5->fails());

// Min/Max string
$v6 = new Validator(['code' => 'ab']);
$result6 = $v6->validate(['code' => 'required|min:3']);
check('min string fail', $result6->fails());

$v7 = new Validator(['code' => 'abcd']);
$result7 = $v7->validate(['code' => 'required|min:3|max:6']);
check('min/max string pass', $result7->passes());

// Nullable
$v8 = new Validator(['nickname' => null]);
$result8 = $v8->validate(['nickname' => 'nullable|string|min:5']);
check('nullable null passes', $result8->passes());

// In rule
$v9 = new Validator(['role' => 'superuser']);
$result9 = $v9->validate(['role' => 'required|in:admin,editor,viewer']);
check('in rule rejects invalid', $result9->fails());

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
