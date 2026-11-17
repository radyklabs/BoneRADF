<?php declare (strict_types=1);

namespace Boneradf\Database;

use RuntimeException;

/**
 * Relation spec — data only, returned by Model::hasOne/hasMany/belongsTo/
 * belongsToMany. Resolution + bulk loading live in Model::applyEager().
 *
 * Key meaning per type:
 *   hasOne/hasMany     foreignKey on related table, localKey on this table
 *   belongsTo          foreignKey on this table, ownerKey on related table
 *                      ('' = related primary key)
 *   belongsToMany      pivot table set; foreignKey on pivot → this,
 *                      ownerKey on pivot → related, localKey on this table
 */
final class Relation
{
    public const HAS_ONE         = 'hasOne';
    public const HAS_MANY        = 'hasMany';
    public const BELONGS_TO      = 'belongsTo';
    public const BELONGS_TO_MANY = 'belongsToMany';

    /**
     * @param class-string<Model> $related
     * @param string      $type       one of the HAS_ / BELONGS_ constants
     * @param string      $foreignKey see class doc
     * @param string      $localKey   see class doc
     * @param string      $ownerKey   see class doc
     * @param string|null $pivot      belongsToMany pivot table only
     */
    public function __construct(
        public readonly string $type,
        public readonly string $related,
        public readonly string $foreignKey,
        public readonly string $localKey,
        public readonly string $ownerKey = '',
        public readonly ?string $pivot = null,
    ) {
        if (!in_array($type, [self::HAS_ONE, self::HAS_MANY, self::BELONGS_TO, self::BELONGS_TO_MANY], true)) {
            throw new RuntimeException("Relation: unknown type '{$type}'");
        }
        if ($type === self::BELONGS_TO_MANY && ($pivot === null || $pivot === '')) {
            throw new RuntimeException('Relation: belongsToMany requires a pivot table.');
        }
    }
}
