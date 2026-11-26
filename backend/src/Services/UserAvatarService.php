<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class UserAvatarService
{
    public function get(int $userId): ?string
    {
        $statement = Connection::create()->prepare('SELECT image_path FROM user_avatars WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $path = $statement->fetchColumn();

        return is_string($path) ? $path : null;
    }

    public function save(int $userId, string $imagePath): void
    {
        $statement = Connection::create()->prepare(
            'INSERT INTO user_avatars (user_id, image_path) VALUES (:user_id, :image_path) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), consented_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['user_id' => $userId, 'image_path' => $imagePath]);
    }

    public function delete(int $userId): ?string
    {
        $path = $this->get($userId);
        $statement = Connection::create()->prepare('DELETE FROM user_avatars WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return $path;
    }
}
