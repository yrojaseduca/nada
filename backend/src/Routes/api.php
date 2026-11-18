<?php

declare(strict_types=1);

use App\Config\Config;
use App\Database\Connection;
use App\Http\JsonResponse;
use App\Services\AuthService;
use RuntimeException;

return static function (Base $f3): void {
    $f3->route('GET /api/health', static function (): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['service' => 'nada', 'environment' => Config::get('APP_ENV', 'development')], JSON_THROW_ON_ERROR);
    });

    $f3->route('GET /api/health/database', static function (): void {
        Connection::create()->query('SELECT 1');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
    });

    $auth = new AuthService();

    $f3->route('POST /api/auth/login', static function () use ($auth): void {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $email = is_array($payload) ? (string) ($payload['email'] ?? '') : '';
        $password = is_array($payload) ? (string) ($payload['password'] ?? '') : '';

        if ($email === '' || $password === '') {
            JsonResponse::send(['message' => 'Email y contraseña son obligatorios.'], 422);
        }

        try {
            JsonResponse::send($auth->login($email, $password));
        } catch (RuntimeException $exception) {
            JsonResponse::send(['message' => $exception->getMessage()], 401);
        }
    });

    $f3->route('GET /api/auth/me', static function () use ($auth): void {
        $user = $auth->userFromToken(bearerToken());
        $user === null
            ? JsonResponse::send(['message' => 'No autenticado.'], 401)
            : JsonResponse::send(['user' => $user]);
    });

    $f3->route('POST /api/auth/logout', static function () use ($auth): void {
        $auth->logout(bearerToken());
        JsonResponse::send(['message' => 'Sesión cerrada.']);
    });
};

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : '';
}
