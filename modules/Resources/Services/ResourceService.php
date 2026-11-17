<?php

declare(strict_types=1);

namespace Modules\Resources\Services;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Validator\Validator;
use Modules\Resources\Models\ResourceModel;
use PDOException;

/**
 * Resources service — validation + orchestration. All table access lives in
 * ResourceModel (QueryBuilder, prepared statements); handlers validate at the
 * trust boundary (name: required|string|1..191).
 *
 * ponytail: the `resources` table is a sample domain (one `name` column) —
 * cross-table use-cases belong here when a richer schema lands; table access
 * stays in Models/.
 */
final class ResourceService
{
    private ResourceModel $model;

    public function __construct(ResourceModel $model)
    {
        $this->model = $model;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->model->listAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->model->find($id);
    }

    /**
     * @param array{name: string} $data
     * @return array{0: bool, 1: array<string, mixed>|null, 2: array<string,string[]>|null}
     *         [ok, resource, validationErrors]
     */
    public function create(array $data): array
    {
        $errors = $this->validateName($data['name'] ?? '');
        if ($errors !== null) {
            return [false, null, $errors];
        }

        try {
            $id = $this->model->insert(['name' => $data['name']]);
        } catch (PDOException) {
            return [false, null, null];
        }

        $row = $this->find((string)$id);
        return [true, $row, null];
    }

    /**
     * @param array{name: string} $data
     * @return array{0: bool, 1: array<string, mixed>|null, 2: array<string,string[]>|null}
     */
    public function update(string $id, array $data): array
    {
        $errors = $this->validateName($data['name'] ?? '');
        if ($errors !== null) {
            return [false, null, $errors];
        }

        try {
            $this->model->where('id', '=', $id)->update(['name' => $data['name']]);
        } catch (PDOException) {
            return [false, null, null];
        }

        return [true, $this->find($id), null];
    }

    /**
     * @return bool  true if a row was deleted; false if not found or DB error.
     */
    public function delete(string $id): bool
    {
        try {
            return $this->model->where('id', '=', $id)->delete() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @return array<string, string[]>|null  validation errors, or null if valid
     */
    private function validateName(string $name): ?array
    {
        $result = (new Validator(['name' => $name]))->validate([
            'name' => 'required|string|min:1|max:191',
        ]);
        return $result->fails() ? $result->errors() : null;
    }

    /**
     * Shape a DB row into the API `Resource` payload.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function toResource(array $row): array
    {
        return [
            'id'        => (string)(int)$row['id'],
            'name'      => (string)$row['name'],
            'createdAt' => (string)$row['created_at'],
        ];
    }

    /**
     * Validate the `:id` route param (digits only).
     */
    public static function validId(string $id): bool
    {
        return preg_match('/^\d+$/', $id) === 1;
    }
}
