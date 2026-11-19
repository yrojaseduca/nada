<?php

declare(strict_types=1);

use App\Config\Config;
use App\Database\Connection;
use App\Http\JsonResponse;
use App\Services\AuthService;
use App\Services\GarmentService;
use App\Services\UserManagementService;
use App\Services\OutfitService;
use App\Services\OutfitRecommendationService;
use App\Services\UserPreferenceService;

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
    $garments = new GarmentService();
    $users = new UserManagementService();
    $outfits = new OutfitService();
    $recommendations = new OutfitRecommendationService();
    $preferences = new UserPreferenceService();

    $f3->route('POST /api/auth/login', static function () use ($auth): void {
        $payload = requestPayload();
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

    $f3->route('PATCH /api/auth/me', static function () use ($auth): void {
        $currentUser = authenticatedUser($auth);
        $payload = requestPayload();
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            JsonResponse::send(['message' => 'Nombre y email válido son obligatorios.'], 422);
        }
        try {
            JsonResponse::send(['user' => $auth->updateUser((int) $currentUser['id'], $name, $email)]);
        } catch (\PDOException $exception) {
            JsonResponse::send(['message' => 'Ese email ya está en uso.'], 422);
        }
    });

    $f3->route('GET /api/admin/users', static function () use ($auth, $users): void {
        $currentUser = authenticatedUser($auth);
        requireSuperadmin($currentUser);
        JsonResponse::send(['data' => $users->list()]);
    });

    $f3->route('POST /api/admin/users/@id/toggle-active', static function () use ($auth, $users, $f3): void {
        $currentUser = authenticatedUser($auth);
        requireSuperadmin($currentUser);
        try {
            $users->toggleActive((int) $f3->get('PARAMS.id'), (int) $currentUser['id']);
            JsonResponse::send(['message' => 'Estado del usuario actualizado.']);
        } catch (\RuntimeException $exception) {
            JsonResponse::send(['message' => $exception->getMessage()], 422);
        }
    });

    $f3->route('GET /api/outfits', static function () use ($auth, $outfits): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $outfits->list((int) $user['id'], $_GET['occasion'] ?? null, $_GET['style'] ?? null, $_GET['season'] ?? null)]);
    });

    $f3->route('GET /api/preferences', static function () use ($auth, $preferences): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $preferences->get((int) $user['id'])]);
    });

    $f3->route('PATCH /api/preferences', static function () use ($auth, $preferences): void {
        $user = authenticatedUser($auth);
        $payload = requestPayload();
        JsonResponse::send(['data' => $preferences->update((int) $user['id'], $payload['preferred_style'] ?? null, $payload['preferred_season'] ?? null)]);
    });

    $f3->route('GET /api/outfits/suggestions', static function () use ($auth, $recommendations, $preferences): void {
        $user = authenticatedUser($auth);
        $style = $_GET['style'] ?? null;
        $season = $_GET['season'] ?? null;
        if (($style === null || $style === '') || ($season === null || $season === '')) {
            $saved = $preferences->get((int) $user['id']);
            $style = $style ?: ($saved['preferred_style'] ?? null);
            $season = $season ?: ($saved['preferred_season'] ?? null);
        }
        JsonResponse::send($recommendations->suggest((int) $user['id'], $_GET['occasion'] ?? null, $style, $season));
    });

    $f3->route('POST /api/outfits', static function () use ($auth, $outfits): void {
        $user = authenticatedUser($auth);
        $payload = json_decode((string) file_get_contents('php://input'), true);
        try {
            JsonResponse::send(['data' => $outfits->create((int) $user['id'], is_array($payload) ? $payload : [])], 201);
        } catch (\RuntimeException $exception) {
            JsonResponse::send(['message' => $exception->getMessage()], 422);
        }
    });

    $f3->route('PATCH /api/outfits/@id', static function () use ($auth, $outfits, $f3): void {
        $user = authenticatedUser($auth);
        try {
            JsonResponse::send(['data' => $outfits->update((int) $user['id'], (int) $f3->get('PARAMS.id'), requestPayload())]);
        } catch (\RuntimeException $exception) {
            JsonResponse::send(['message' => $exception->getMessage()], 422);
        }
    });

    $f3->route('DELETE /api/outfits/@id', static function () use ($auth, $outfits, $f3): void {
        $user = authenticatedUser($auth);
        $outfits->delete((int) $user['id'], (int) $f3->get('PARAMS.id'));
        JsonResponse::send(['message' => 'Look eliminado.']);
    });

    $f3->route('POST /api/outfits/@id/favorite', static function () use ($auth, $outfits, $f3): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $outfits->toggleFavorite((int) $user['id'], (int) $f3->get('PARAMS.id'))]);
    });

    $f3->route('GET /api/garments', static function () use ($auth, $garments): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $garments->list($user['id'], $_GET['category'] ?? null, $_GET['search'] ?? null)]);
    });

    $f3->route('POST /api/garments', static function () use ($auth, $garments): void {
        $user = authenticatedUser($auth);
        $payload = uploadGarmentImage(requestPayload(), (int) $user['id']);
        validateGarmentPayload($payload);

        JsonResponse::send(['data' => $garments->create((int) $user['id'], $payload)], 201);
    });

    $f3->route('GET /api/garments/@id/image', static function () use ($auth, $garments, $f3): void {
        $user = authenticatedUser($auth);
        $garment = $garments->find((int) $user['id'], (int) $f3->get('PARAMS.id'));
        $relativePath = (string) ($garment['image_path'] ?? '');
        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage');
        $imagePath = $storageRoot !== false ? realpath(dirname(__DIR__, 2) . $relativePath) : false;

        if ($storageRoot === false || $imagePath === false || !str_starts_with($imagePath, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($imagePath)) {
            JsonResponse::send(['message' => 'Imagen no encontrada.'], 404);
        }

        $mimeType = mime_content_type($imagePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: private, max-age=3600');
        readfile($imagePath);
        exit;
    });

    $f3->route('PATCH /api/garments/@id', static function () use ($auth, $garments, $f3): void {
        $user = authenticatedUser($auth);
        $payload = requestPayload();
        validateGarmentPayload($payload);
        JsonResponse::send(['data' => $garments->update((int) $user['id'], (int) $f3->get('PARAMS.id'), $payload)]);
    });

    $f3->route('GET /api/garments/@id', static function () use ($auth, $garments, $f3): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $garments->find((int) $user['id'], (int) $f3->get('PARAMS.id'))]);
    });

    $f3->route('DELETE /api/garments/@id', static function () use ($auth, $garments, $f3): void {
        $user = authenticatedUser($auth);
        $garments->delete((int) $user['id'], (int) $f3->get('PARAMS.id'));
        JsonResponse::send(['message' => 'Prenda eliminada.']);
    });

    $f3->route('POST /api/garments/@id/favorite', static function () use ($auth, $garments, $f3): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $garments->toggleFavorite((int) $user['id'], (int) $f3->get('PARAMS.id'))]);
    });
};

function requestPayload(): array
{
    $payload = json_decode((string) file_get_contents('php://input'), true);

    return is_array($payload) ? $payload : $_POST;
}

function validateGarmentPayload(array $payload): void
{
    if (trim((string) ($payload['name'] ?? '')) === '' || trim((string) ($payload['category'] ?? '')) === '' || trim((string) ($payload['primary_color'] ?? '')) === '') {
        JsonResponse::send(['message' => 'Nombre, categoría y color principal son obligatorios.'], 422);
    }
}

function uploadGarmentImage(array $payload, int $userId): array
{
    $uploadedFile = $_FILES['image'] ?? null;
    if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $payload;
    }

    if (($uploadedFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($uploadedFile['size'] ?? 0) > 5 * 1024 * 1024) {
        JsonResponse::send(['message' => 'La imagen no es válida o supera los 5 MB.'], 422);
    }

    $extension = match (mime_content_type((string) $uploadedFile['tmp_name'])) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($extension === null) {
        JsonResponse::send(['message' => 'Solo se admiten imágenes JPG, PNG o WEBP.'], 422);
    }

    $directory = dirname(__DIR__, 2) . '/storage/garments/' . $userId;
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    move_uploaded_file((string) $uploadedFile['tmp_name'], $directory . '/' . $filename);
    $payload['image_path'] = '/storage/garments/' . $userId . '/' . $filename;

    return $payload;
}

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
        return trim($matches[1]);
    }

    return trim((string) ($_GET['token'] ?? ''));
}

function authenticatedUser(AuthService $auth): array
{
    $user = $auth->userFromToken(bearerToken());

    if ($user === null) {
        JsonResponse::send(['message' => 'No autenticado.'], 401);
    }

    return $user;
}

function requireSuperadmin(array $user): void
{
    if (!(bool) ($user['is_superadmin'] ?? false)) {
        JsonResponse::send(['message' => 'Se requieren permisos de superadmin.'], 403);
    }
}
