<?php declare (strict_types=1);

namespace Boneradf\Database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Active-record base — rows stay arrays (ar-arrays), prepared statements only.
 *
 * Every query goes through QueryBuilder; no raw SQL lives here. Chain methods
 * (select/where/whereIn/orderBy/limit/offset/with) accumulate on an internal
 * builder; terminal methods (get/first/find/insert/update/delete/count)
 * execute, attach eager relations, then reset the chain.
 *
 * Relations are declared as methods returning a Relation:
 *
 *     protected function posts(): Relation { return $this->hasMany(PostModel::class, 'author_id'); }
 *
 * `with('posts')` bulk-loads them (1 extra query per relation — no N+1) and
 * attaches the results as nested arrays on each row.
 *
 * ponytail: no dot-path nested eager (`with('a.b')`), no lazy loading/proxy,
 * no morphs/polymorphic, no pagination scope. Upgrade path: split dot-paths
 * in applyEager() and add a Page value object when those needs land.
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    private ?QueryBuilder $qb = null;

    /** @var string[] relation names queued for the next terminal call */
    private array $eager = [];

    public function __construct(protected PDO $pdo)
    {
        if ($this->table === '') {
            throw new RuntimeException(static::class . ': $table is not set.');
        }
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    // ------------------------------------------------------------------
    // Chain
    // ------------------------------------------------------------------

    public function select(string ...$columns): static
    {
        $this->query()->select(...$columns);
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $this->query()->where($column, $operator, $value);
        return $this;
    }

    /**
     * @param array<int|string, mixed> $values
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query()->whereIn($column, $values);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->query()->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query()->limit($limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query()->offset($offset);
        return $this;
    }

    /**
     * Queue eager relations for the next terminal call (see class doc).
     *
     * @param string ...$names  relation method names on this model
     */
    public function with(string ...$names): static
    {
        foreach ($names as $name) {
            $this->eager[] = $name;
        }
        return $this;
    }

    // ------------------------------------------------------------------
    // Terminals
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $rows = $this->query()->get();
        $this->applyEager($rows);
        $this->reset();
        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $row = $this->query()->first();
        if ($row !== null) {
            $rows = [$row];
            $this->applyEager($rows);
            $row = $rows[0];
        }
        $this->reset();
        return $row;
    }

    /**
     * Fresh lookup by primary key — discards any pending chain (queued
     * with() still applies).
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->table($this->table)
            ->where($this->primaryKey, '=', $id)
            ->first();
        if ($row !== null) {
            $rows = [$row];
            $this->applyEager($rows);
            $row = $rows[0];
        }
        $this->reset();
        return $row;
    }

    /**
     * @param array<string, mixed> $data  column => value (bound parameters)
     * @return int  last insert id
     */
    public function insert(array $data): int
    {
        $id = $this->query()->insert($data);
        $this->reset();
        return $id;
    }

    /**
     * UPDATE on the accumulated chain — chain a where() first unless an
     * unconstrained update is deliberate.
     *
     * @return int  affected rows
     */
    public function update(array $data): int
    {
        $affected = $this->query()->update($data);
        $this->reset();
        return $affected;
    }

    /**
     * DELETE on the accumulated chain — a WHERE is required (QueryBuilder
     * enforces it).
     *
     * @return int  affected rows
     */
    public function delete(): int
    {
        $affected = $this->query()->delete();
        $this->reset();
        return $affected;
    }

    public function count(): int
    {
        $count = $this->query()->count();
        $this->reset();
        return $count;
    }

    // ------------------------------------------------------------------
    // Relations — declare on the concrete model, return these
    // ------------------------------------------------------------------

    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null): Relation
    {
        return new Relation(Relation::HAS_ONE, $related, $foreignKey, $localKey ?? $this->primaryKey);
    }

    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null): Relation
    {
        return new Relation(Relation::HAS_MANY, $related, $foreignKey, $localKey ?? $this->primaryKey);
    }

    protected function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null): Relation
    {
        return new Relation(Relation::BELONGS_TO, $related, $foreignKey, $this->primaryKey, $ownerKey ?? '');
    }

    /**
     * @param string $foreignKey  pivot column pointing at this model
     * @param string $relatedKey  pivot column pointing at the related model
     */
    protected function belongsToMany(string $related, string $pivot, string $foreignKey, string $relatedKey): Relation
    {
        return new Relation(Relation::BELONGS_TO_MANY, $related, $foreignKey, $this->primaryKey, $relatedKey, $pivot);
    }

    // ------------------------------------------------------------------
    // Eager loading
    // ------------------------------------------------------------------

    /**
     * Attach queued relations onto rows as nested arrays — one bulk query
     * per relation (IN(...) sets), never one per row.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function applyEager(array &$rows): void
    {
        if ($this->eager === [] || $rows === []) {
            return;
        }

        foreach ($this->eager as $name) {
            $rel = $this->resolveRelation($name);
            /** @var class-string<Model> $related */
            $related = $rel->related;
            $target  = new $related($this->pdo);

            switch ($rel->type) {
                case Relation::HAS_MANY:
                case Relation::HAS_ONE:
                    $keys = $this->keyValues($rows, $rel->localKey);
                    $children = $keys === [] ? [] : $target
                        ->whereIn($rel->foreignKey, $keys)
                        ->orderBy($target->primaryKey(), 'ASC')
                        ->get();
                    $groups = [];
                    foreach ($children as $child) {
                        $groups[(string)$child[$rel->foreignKey]][] = $child;
                    }
                    foreach ($rows as &$row) {
                        $bucket = $groups[(string)($row[$rel->localKey] ?? '')] ?? [];
                        $row[$name] = $rel->type === Relation::HAS_ONE ? ($bucket[0] ?? null) : $bucket;
                    }
                    unset($row);
                    break;

                case Relation::BELONGS_TO:
                    $ownerKey = $rel->ownerKey !== '' ? $rel->ownerKey : $target->primaryKey();
                    $keys     = $this->keyValues($rows, $rel->foreignKey);
                    $parents  = $keys === [] ? [] : $target->whereIn($ownerKey, $keys)->get();
                    $index    = [];
                    foreach ($parents as $parent) {
                        $index[(string)$parent[$ownerKey]] = $parent;
                    }
                    foreach ($rows as &$row) {
                        $fk = $row[$rel->foreignKey] ?? null;
                        $row[$name] = $fk === null ? null : ($index[(string)$fk] ?? null);
                    }
                    unset($row);
                    break;

                case Relation::BELONGS_TO_MANY:
                    $pivot     = (string)$rel->pivot;
                    $keys      = $this->keyValues($rows, $rel->localKey);
                    $pivotRows = $keys === [] ? [] : (new QueryBuilder($this->pdo))
                        ->table($pivot)
                        ->whereIn($rel->foreignKey, $keys)
                        ->get();
                    $relatedIds = [];
                    foreach ($pivotRows as $pivotRow) {
                        $relatedIds[] = $pivotRow[$rel->ownerKey];
                    }
                    $relatedIds = array_values(array_unique($relatedIds, SORT_REGULAR));
                    $byId       = [];
                    if ($relatedIds !== []) {
                        foreach ($target->whereIn($target->primaryKey(), $relatedIds)->get() as $t) {
                            $byId[(string)$t[$target->primaryKey()]] = $t;
                        }
                    }
                    $perParent = [];
                    foreach ($pivotRows as $pivotRow) {
                        $child = $byId[(string)$pivotRow[$rel->ownerKey]] ?? null;
                        if ($child !== null) {
                            $perParent[(string)$pivotRow[$rel->foreignKey]][] = $child;
                        }
                    }
                    foreach ($rows as &$row) {
                        $row[$name] = $perParent[(string)($row[$rel->localKey] ?? '')] ?? [];
                    }
                    unset($row);
                    break;

                default:
                    throw new RuntimeException(static::class . "::{$name}(): unknown relation type.");
            }
        }
    }

    private function resolveRelation(string $name): Relation
    {
        if (!method_exists($this, $name)) {
            throw new RuntimeException(static::class . ": relation '{$name}' does not exist.");
        }
        try {
            $relation = $this->{$name}();
        } catch (Throwable $e) {
            throw new RuntimeException(static::class . "::{$name}() is not a relation method.", 0, $e);
        }
        if (!$relation instanceof Relation) {
            throw new RuntimeException(static::class . "::{$name}() must return a Relation.");
        }
        return $relation;
    }

    /**
     * Unique, non-null key values from rows — the IN(...) set for eager loads.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, mixed>
     */
    private function keyValues(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }
        return array_values(array_unique($values, SORT_REGULAR));
    }

    private function query(): QueryBuilder
    {
        return $this->qb ??= (new QueryBuilder($this->pdo))->table($this->table);
    }

    private function reset(): void
    {
        $this->qb    = null;
        $this->eager = [];
    }
}
