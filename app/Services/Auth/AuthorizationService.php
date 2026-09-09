<?php
declare(strict_types=1);

namespace App\Services\Auth;

final class AuthorizationService
{
    private const PERMISSIONS = [
        'admin' => ['*'],
        'reviewer' => [
            'admin.dashboard.view',
            'products.view',
            'sources.view',
            'collection.jobs.view',
            'snapshots.view',
            'review.view',
            'review.correct',
            'review.decide',
            'review.analytics.view',
        ],
        'operator' => [
            'admin.dashboard.view',
            'products.view',
            'sources.view',
            'sources.control',
            'sources.incident',
            'collection.jobs.view',
            'collection.jobs.requeue',
            'snapshots.view',
            'review.analytics.view',
        ],
        'user' => [],
    ];

    public function can(?array $user, string $permission): bool
    {
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return false;
        }
        $role = strtolower((string)($user['role'] ?? 'user'));
        $permissions = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function matrix(): array
    {
        return self::PERMISSIONS;
    }
}
