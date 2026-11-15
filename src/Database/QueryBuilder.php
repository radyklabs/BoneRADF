<?php

declare(strict_types=1);

namespace Boneradf\Database;

use PDO;
use RuntimeException;

/**
 * PDO query builder — prepared statements ONLY.
 *
 * No raw SQL API in core (D5/D6). The only execute path is
 * `execute($sql, $params)`; values never concatenate into `$sql`.
 * Identifiers (table/column names) are validated against an allowlist
 * pattern at build time — PDO cannot bind identifiers.
 */
final class QueryBuilder
{
    private PDO $pdo;
    private string $table = '';
    private array $select = [];
    private array $where = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Set the table.
     *
     * @throws RuntimeException if the identifier contains invalid characters
     */
    public function table(string $name): self
    {
        $this->assertIdentifier($name);
        $this->table = $name;
        return $this;
    }

    /**
     * Select columns (default `*`).
     *
     * @param string ...$columns
     */
    public function select(string ...$columns): self
    {
        foreach ($columns as $col) {
            $this->assertIdentifier($col);
        }
        $this->select = $columns;
        return $this;
    }

    /**
     * Add a WHERE clause. Values are bound parameters.
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->assertIdentifier($column);
        $this->assertOperator($operator);
        $this->where[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    /**
     * Add a WHERE IN clause. Values are bound parameters.
     *
     * @param array<int|string, mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->assertIdentifier($column);
        if ($values === []) {
            // WHERE column IN () is a SQL syntax error; treat as always-false
            $this->where[] = ['column' => null, 'operator' => 'RAW', 'value' => '1=0', 'boolean' => 'AND'];
            return $this;
        }
        $this->where[] = ['column' => $column, 'operator' => 'IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->assertIdentifier($column);
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new RuntimeException("Query builder: invalid ORDER BY direction '{$direction}'");
        }
        $this->orderBy[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * Build and execute a SELECT query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Build and execute a SELECT, returning the first row or null.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * Insert a row. Values are bound parameters.
     *
     * @param array<string, mixed> $data  column => value
     * @return int  last insert id
     */
    public function insert(array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Query builder: insert() requires at least one column.');
        }
        $columns = array_keys($data);
        foreach ($columns as $col) {
            $this->assertIdentifier($col);
        }
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update rows. Values are bound parameters.
     *
     * @param array<string, mixed> $data  column => value
     * @return int  affected row count
     */
    public function update(array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Query builder: update() requires at least one column.');
        }
        $sets = [];
        foreach ($data as $col => $val) {
            $this->assertIdentifier((string)$col);
            $sets[] = $col . ' = :set_' . $col;
        }
        [$whereSql, $whereParams] = $this->buildWhere();
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        // Rename bound keys to avoid collisions with WHERE params
        $params = [];
        foreach ($data as $col => $val) {
            $params['set_' . $col] = $val;
        }
        $params = array_merge($params, $whereParams);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows.
     *
     * @return int  affected row count
     */
    public function delete(): int
    {
        [$whereSql, $whereParams] = $this->buildWhere();
        if ($whereSql === '') {
            throw new RuntimeException('Query builder: delete() requires at least one WHERE clause.');
        }
        $sql = 'DELETE FROM ' . $this->table . ' WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->rowCount();
    }

    /**
     * Count rows.
     */
    public function count(): int
    {
        $this->select = ['COUNT(*)'];
        $row = $this->first();
        return $row !== null ? (int)reset($row) : 0;
    }

    // ------------------------------------------------------------------
    // Build helpers
    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSelect(): array
    {
        if ($this->table === '') {
            throw new RuntimeException('Query builder: table not set.');
        }
        $cols = $this->select === [] ? '*' : implode(', ', $this->select);
        $sql = 'SELECT ' . $cols . ' FROM ' . $this->table;

        [$whereSql, $whereParams] = $this->buildWhere();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        if ($this->orderBy !== []) {
            $parts = [];
            foreach ($this->orderBy as $o) {
                $parts[] = $o['column'] . ' ' . $o['direction'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return [$sql, $whereParams];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(): array
    {
        if ($this->where === []) {
            return ['', []];
        }
        $parts = [];
        $params = [];
        $idx = 0;
        foreach ($this->where as $w) {
            $bool = $idx === 0 ? '' : $w['boolean'] . ' ';
            if ($w['operator'] === 'RAW') {
                $parts[] = $bool . '(' . $w['value'] . ')';
                continue;
            }
            if ($w['operator'] === 'IN') {
                $values = $w['value'];
                $keys = [];
                foreach ($values as $v) {
                    $key = ':w' . $idx . '_' . count($keys);
                    $keys[] = $key;
                    $params[$key] = $v;
                }
                $parts[] = $bool . $w['column'] . ' IN (' . implode(', ', $keys) . ')';
                $idx++;
                continue;
            }
            $key = ':w' . $idx++;
            $parts[] = $bool . $w['column'] . ' ' . $w['operator'] . ' ' . $key;
            $params[$key] = $w['value'];
        }
        return [implode(' ', $parts), $params];
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    /**
     * Identifiers: letters, digits, underscores, dots (for table.column).
     * No spaces, quotes, or semicolons.
     */
    private function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
            throw new RuntimeException("Query builder: invalid identifier '{$name}'");
        }
    }

    private function assertOperator(string $op): void
    {
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array(strtoupper($op), $allowed, true)) {
            throw new RuntimeException("Query builder: invalid operator '{$op}'");
        }
    }
}
