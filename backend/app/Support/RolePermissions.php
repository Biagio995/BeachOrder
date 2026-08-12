<?php

namespace App\Support;

use App\Models\User;

class RolePermissions
{
    public const TENANTS_MANAGE = 'tenants.manage';

    public const USERS_MANAGE = 'users.manage';

    public const MENU_MANAGE = 'menu.manage';

    public const ORDERS_MANAGE = 'orders.manage';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_UPDATE_STATUS = 'orders.update_status';

    public const QR_MANAGE = 'qr.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const PRODUCTS_AVAILABILITY = 'products.availability';

    public const WAITER_CALLS_MANAGE = 'waiter_calls.manage';

    /** @var list<string> */
    public const ALL = [
        self::TENANTS_MANAGE,
        self::USERS_MANAGE,
        self::MENU_MANAGE,
        self::ORDERS_MANAGE,
        self::ORDERS_VIEW,
        self::ORDERS_UPDATE_STATUS,
        self::QR_MANAGE,
        self::SETTINGS_MANAGE,
        self::PRODUCTS_AVAILABILITY,
        self::WAITER_CALLS_MANAGE,
    ];

    /** @var array<string, list<string>> */
    private const MAP = [
        User::ROLE_SUPER_ADMIN => self::ALL,
        User::ROLE_ADMIN => [
            self::USERS_MANAGE,
            self::MENU_MANAGE,
            self::ORDERS_MANAGE,
            self::ORDERS_VIEW,
            self::ORDERS_UPDATE_STATUS,
            self::QR_MANAGE,
            self::SETTINGS_MANAGE,
            self::PRODUCTS_AVAILABILITY,
            self::WAITER_CALLS_MANAGE,
        ],
        User::ROLE_MANAGER => [
            self::MENU_MANAGE,
            self::ORDERS_MANAGE,
            self::ORDERS_VIEW,
            self::ORDERS_UPDATE_STATUS,
            self::QR_MANAGE,
            self::PRODUCTS_AVAILABILITY,
        ],
        User::ROLE_STAFF => [
            self::ORDERS_VIEW,
            self::ORDERS_UPDATE_STATUS,
            self::WAITER_CALLS_MANAGE,
        ],
    ];

    /** @return list<string> */
    public static function forRole(?string $role): array
    {
        return self::MAP[$role] ?? [];
    }

    /** @return list<string> */
    public static function forUser(User $user): array
    {
        return self::forRole($user->role);
    }

    public static function userHas(User $user, string $permission): bool
    {
        return in_array($permission, self::forUser($user), true);
    }

    /** @return list<string> */
    public static function tenantRoles(): array
    {
        return [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_STAFF];
    }

    /** @return list<string> */
    public static function assignableRoles(): array
    {
        return self::tenantRoles();
    }
}
