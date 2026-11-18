<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use RuntimeException;

final class AuthService
{
    public function login(string $email, string $password): array
    {
        $statement = Connection::create()->prepare('SELECT id, email, name, password_hash FROM users WHERE email = :email AND active = 1 LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Credenciales incorrectas.');
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenStatement = Connection::create()->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY))'
        );
        $tokenStatement->execute([
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $plainToken),
        ]);

        unset($user['password_hash']);

        return ['token' => $plainToken, 'user' => $user];
    }

    public function userFromToken(string $token): ?array
    {
        $statement = Connection::create()->prepare(
            'SELECT u.id, u.email, u.name FROM auth_tokens t INNER JOIN users u ON u.id = t.user_id WHERE t.token_hash = :token_hash AND t.expires_at > NOW() AND u.active = 1 LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function logout(string $token): void
    {
        $statement = Connection::create()->prepare('DELETE FROM auth_tokens WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => hash('sha256', $token)]);
    }
}
