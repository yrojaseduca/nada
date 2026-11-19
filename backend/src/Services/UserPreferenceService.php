<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class UserPreferenceService
{
    public function get(int $userId): array
    {
        $statement = Connection::create()->prepare('SELECT preferred_style, preferred_season FROM user_preferences WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $preferences = $statement->fetch();

        return is_array($preferences) ? $preferences : ['preferred_style' => null, 'preferred_season' => null];
    }

    public function update(int $userId, ?string $style, ?string $season): array
    {
        $statement = Connection::create()->prepare(
            'INSERT INTO user_preferences (user_id, preferred_style, preferred_season) VALUES (:user_id, :preferred_style, :preferred_season) ON DUPLICATE KEY UPDATE preferred_style = VALUES(preferred_style), preferred_season = VALUES(preferred_season)'
        );
        $statement->execute([
            'user_id' => $userId,
            'preferred_style' => $style !== null ? trim($style) : null,
            'preferred_season' => $season !== null ? trim($season) : null,
        ]);

        return $this->get($userId);
    }
}
