<?php declare (strict_types=1);

namespace Modules\Resources\Models;

use Boneradf\Database\Model;

/**
 * `resources` table access — the only place that queries `resources`
 * (QueryBuilder, prepared statements). Validation + response shaping live
 * in ResourceService.
 */
final class ResourceModel extends Model
{
    protected string $table = 'resources';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        return $this->select('id', 'name', 'created_at')
            ->orderBy('id', 'ASC')
            ->get();
    }
}
