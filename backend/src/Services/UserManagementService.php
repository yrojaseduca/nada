<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;

final class UserManagementService
{
    public function list(): array
    {
        return Connection::create()->query('SELECT id, email, name, active, is_superadmin, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleActive(int $id, int $currentUserId): void
    {
        if ($id === $currentUserId) {
            throw new RuntimeException('No puedes desactivar tu propio usuario.');
        }

        $statement = Connection::create()->prepare('UPDATE users SET active = NOT active WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
