<?php declare (strict_types=1);

namespace Modules\Auth\Models;

use Boneradf\Database\Model;

/**
 * `users` table access for the auth flow — the only place that queries
 * `users` (QueryBuilder, prepared statements). Credential rules (password
 * verification, timing equalizer) and JWT issuance live in LoginService.
 */
final class UserModel extends Model
{
    protected string $table = 'users';

    /**
     * @return array<string, mixed>|null  credentials row (id, username,
     *                                     password_hash, role) or null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->select('id', 'username', 'password_hash', 'role')
            ->where('username', '=', $username)
            ->first();
    }
}
