<?php

namespace App\Support;

class Permissions
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * Roles that can be assigned from the Team screen, with labels.
     *
     * @return array<string,string>
     */
    public static function assignableRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Admin (limited to granted permissions)',
            self::ROLE_SUPER_ADMIN => 'Super admin (full access)',
        ];
    }

    /**
     * @return array<string, array<string,string>>  group => [key => label]
     */
    public static function groups(): array
    {
        return config('permissions.groups', []);
    }

    /**
     * Every permission key.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::groups() as $perms) {
            foreach (array_keys($perms) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * Keep only recognised permission keys.
     *
     * @param  iterable<string>  $keys
     * @return list<string>
     */
    public static function sanitize(iterable $keys): array
    {
        $valid = [];
        foreach ($keys as $key) {
            if (is_string($key) && self::isValidKey($key)) {
                $valid[$key] = true;
            }
        }

        return array_values(array_keys($valid));
    }
}
