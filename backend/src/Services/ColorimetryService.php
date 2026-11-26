<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class ColorimetryService
{
    public function get(int $userId): ?array
    {
        $statement = Connection::create()->prepare('SELECT image_path, result_json FROM user_colorimetry WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'image_path' => $row['image_path'],
            'result' => $row['result_json'] === null ? null : json_decode((string) $row['result_json'], true),
        ];
    }

    public function save(int $userId, string $imagePath, array $result): void
    {
        $statement = Connection::create()->prepare(
            'INSERT INTO user_colorimetry (user_id, image_path, result_json) VALUES (:user_id, :image_path, :result_json) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), result_json = VALUES(result_json)'
        );
        $statement->execute([
            'user_id' => $userId,
            'image_path' => $imagePath,
            'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
        ]);
    }

    public function delete(int $userId): ?string
    {
        $record = $this->get($userId);
        $statement = Connection::create()->prepare('DELETE FROM user_colorimetry WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return $record['image_path'] ?? null;
    }
}
