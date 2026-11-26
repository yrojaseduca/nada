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
use App\Services\ColorimetryService;
use App\Services\UserAvatarService;
use App\Services\GarmentImageEnhancer;

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
    $colorimetry = new ColorimetryService();
    $avatars = new UserAvatarService();

    $f3->route('GET /api/avatar', static function () use ($auth, $avatars): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => ['exists' => $avatars->get((int) $user['id']) !== null]]);
    });

    $f3->route('POST /api/avatar', static function () use ($auth, $avatars): void {
        $user = authenticatedUser($auth);
        if (($_POST['consent'] ?? '') !== '1') {
            JsonResponse::send(['message' => 'Debes aceptar el almacenamiento privado para continuar.'], 422);
        }
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            JsonResponse::send(['message' => 'Selecciona una foto válida de hasta 8 MB.'], 422);
        }
        $extension = match (mime_content_type((string) $file['tmp_name'])) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            JsonResponse::send(['message' => 'Solo se admiten imágenes JPG, PNG o WEBP.'], 422);
        }
        $userId = (int) $user['id'];
        $directory = dirname(__DIR__, 2) . '/storage/avatars/' . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            JsonResponse::send(['message' => 'No se pudo preparar el almacenamiento privado.'], 500);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
            JsonResponse::send(['message' => 'No se pudo guardar la foto.'], 500);
        }
        $relativePath = '/storage/avatars/' . $userId . '/' . $filename;
        try {
            $oldPath = $avatars->get($userId);
        } catch (Throwable $exception) {
            unlink($directory . '/' . $filename);
            throw $exception;
        }
        try {
            $avatars->save($userId, $relativePath);
        } catch (Throwable $exception) {
            unlink($directory . '/' . $filename);
            throw $exception;
        }
        deletePrivateStorageFile($oldPath);
        JsonResponse::send(['message' => 'Avatar guardado de forma privada.']);
    });

    $f3->route('GET /api/avatar/image', static function () use ($auth, $avatars): void {
        $user = authenticatedUser($auth);
        servePrivateStorageFile($avatars->get((int) $user['id']));
    });

    $f3->route('DELETE /api/avatar', static function () use ($auth, $avatars): void {
        $user = authenticatedUser($auth);
        deletePrivateStorageFile($avatars->delete((int) $user['id']));
        JsonResponse::send(['message' => 'Avatar eliminado.']);
    });

    $f3->route('GET /api/colorimetry', static function () use ($auth, $colorimetry): void {
        $user = authenticatedUser($auth);
        JsonResponse::send(['data' => $colorimetry->get((int) $user['id'])]);
    });

    $f3->route('POST /api/colorimetry', static function () use ($auth, $colorimetry): void {
        $user = authenticatedUser($auth);
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            JsonResponse::send(['message' => 'Selecciona una imagen válida de hasta 5 MB.'], 422);
        }
        $extension = match (mime_content_type((string) $file['tmp_name'])) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            JsonResponse::send(['message' => 'Solo se admiten imágenes JPG, PNG o WEBP.'], 422);
        }
        $payload = json_decode((string) ($_POST['result'] ?? ''), true);
        if (!is_array($payload) || !in_array($payload['season'] ?? '', ['Primavera cálida', 'Verano suave', 'Otoño cálido', 'Invierno frío', 'Paleta neutra'], true)) {
            JsonResponse::send(['message' => 'El resultado de colorimetría no es válido.'], 422);
        }

        $userId = (int) $user['id'];
        $directory = dirname(__DIR__, 2) . '/storage/colorimetry/' . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            JsonResponse::send(['message' => 'No se pudo preparar el almacenamiento privado.'], 500);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
            JsonResponse::send(['message' => 'No se pudo guardar la fotografía.'], 500);
        }
        $relativePath = '/storage/colorimetry/' . $userId . '/' . $filename;
        try {
            $oldRecord = $colorimetry->get($userId);
        } catch (Throwable $exception) {
            unlink($directory . '/' . $filename);
            throw $exception;
        }
        try {
            $colorimetry->save($userId, $relativePath, $payload);
        } catch (Throwable $exception) {
            unlink($directory . '/' . $filename);
            throw $exception;
        }
        deletePrivateStorageFile(is_array($oldRecord) ? (string) $oldRecord['image_path'] : null);
        JsonResponse::send(['data' => ['result' => $payload]]);
    });

    $f3->route('GET /api/colorimetry/image', static function () use ($auth, $colorimetry): void {
        $user = authenticatedUser($auth);
        $record = $colorimetry->get((int) $user['id']);
        servePrivateStorageFile(is_array($record) ? (string) $record['image_path'] : null);
    });

    $f3->route('DELETE /api/colorimetry', static function () use ($auth, $colorimetry): void {
        $user = authenticatedUser($auth);
        $path = $colorimetry->delete((int) $user['id']);
        deletePrivateStorageFile($path);
        JsonResponse::send(['message' => 'Fotografía y análisis eliminados.']);
    });

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

    $f3->route('GET /api/outfits/suggestions', static function () use ($auth, $recommendations, $preferences, $colorimetry): void {
        $user = authenticatedUser($auth);
        $style = $_GET['style'] ?? null;
        $season = $_GET['season'] ?? null;
        if (($style === null || $style === '') || ($season === null || $season === '')) {
            $saved = $preferences->get((int) $user['id']);
            $style = $style ?: ($saved['preferred_style'] ?? null);
            $season = $season ?: ($saved['preferred_season'] ?? null);
        }
        $palette = null;
        if (($_GET['use_colorimetry'] ?? '0') === '1') {
            $analysis = $colorimetry->get((int) $user['id']);
            if (is_array($analysis) && is_array($analysis['result'])) {
                $palette = (string) ($analysis['result']['palette'] ?? '');
            }
        }
        JsonResponse::send($recommendations->suggest((int) $user['id'], $_GET['occasion'] ?? null, $style, $season, $palette));
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

    $f3->route('POST /api/garments/preview-image', static function () use ($auth): void {
        authenticatedUser($auth);
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            JsonResponse::send(['message' => 'La imagen no es válida o supera los 5 MB.'], 422);
        }
        $mimeType = mime_content_type((string) $file['tmp_name']);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            JsonResponse::send(['message' => 'Solo se admiten imágenes JPG, PNG o WEBP.'], 422);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'nido-preview-');
        $enhancedPath = tempnam(sys_get_temp_dir(), 'nido-enhanced-');
        if ($temporaryPath === false || $enhancedPath === false || !copy((string) $file['tmp_name'], $temporaryPath)) {
            JsonResponse::send(['message' => 'No se pudo preparar la vista previa.'], 500);
        }
        $enhanced = false;
        try {
            $enhanced = (new GarmentImageEnhancer())->enhance($temporaryPath, $enhancedPath, $mimeType, ($_POST['smooth_wrinkles'] ?? '0') === '1', ($_POST['enhance_image'] ?? '1') === '1');
            $previewContents = $enhanced ? file_get_contents($enhancedPath) : false;
        } finally {
            @unlink($temporaryPath);
            @unlink($enhancedPath);
        }
        if (!is_string($previewContents ?? false)) {
            JsonResponse::send(['message' => 'No se pudo mejorar esta imagen.'], 422);
        }
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: no-store');
        echo $previewContents;
        exit;
    });

    $f3->route('POST /api/garments/bulk', static function () use ($auth, $garments): void {
        $user = authenticatedUser($auth);
        $category = trim((string) ($_POST['category'] ?? ''));
        $enhanceImage = ($_POST['enhance_image'] ?? '0') === '1';
        $files = $_FILES['images'] ?? null;
        if ($category === '' || !is_array($files) || !is_array($files['name'] ?? null)) {
            JsonResponse::send(['message' => 'Selecciona una categoría y al menos una imagen.'], 422);
        }

        $created = [];
        foreach ($files['name'] as $index => $originalName) {
            $file = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                JsonResponse::send(['message' => 'Cada imagen debe ser válida y no superar los 5 MB.'], 422);
            }
            if (!is_uploaded_file((string) $file['tmp_name'])) {
                JsonResponse::send(['message' => 'No se pudo validar una de las imágenes.'], 422);
            }

            $payload = uploadGarmentImage(['name' => pathinfo((string) $originalName, PATHINFO_FILENAME), 'category' => $category, 'enhance_image' => $enhanceImage ? '1' : '0', 'smooth_wrinkles' => ($_POST['smooth_wrinkles'] ?? '0') === '1' ? '1' : '0'], (int) $user['id'], $file);
            validateGarmentPayload($payload);
            $created[] = $garments->create((int) $user['id'], $payload);
        }

        JsonResponse::send(['data' => $created], 201);
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
    if (trim((string) ($payload['name'] ?? '')) === '' || trim((string) ($payload['category'] ?? '')) === '') {
        JsonResponse::send(['message' => 'El nombre y la categoría son obligatorios.'], 422);
    }
}

function uploadGarmentImage(array $payload, int $userId, ?array $fileOverride = null): array
{
    $uploadedFile = $fileOverride ?? ($_FILES['image'] ?? null);
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

    if (($payload['enhance_image'] ?? '0') === '1' || ($payload['smooth_wrinkles'] ?? '0') === '1') {
        $originalPath = $directory . '/' . $filename . '.original';
        if (rename($directory . '/' . $filename, $originalPath)) {
            $enhanced = (new GarmentImageEnhancer())->enhance($originalPath, $directory . '/' . $filename, 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension), ($payload['smooth_wrinkles'] ?? '0') === '1', ($payload['enhance_image'] ?? '0') === '1');
            if (!$enhanced) {
                rename($originalPath, $directory . '/' . $filename);
            }
        }
    }

    return $payload;
}

function resolvePrivateStorageFile(?string $relativePath): string|false
{
    $storageRoot = realpath(dirname(__DIR__, 2) . '/storage');
    if ($relativePath === null || preg_match('#^/storage/(?:avatars|colorimetry)/[1-9][0-9]*/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath) !== 1) {
        return false;
    }
    $imagePath = $storageRoot !== false ? realpath(dirname(__DIR__, 2) . $relativePath) : false;

    return $storageRoot !== false && $imagePath !== false && str_starts_with($imagePath, $storageRoot . DIRECTORY_SEPARATOR) && is_file($imagePath)
        ? $imagePath
        : false;
}

function servePrivateStorageFile(?string $relativePath): never
{
    $imagePath = resolvePrivateStorageFile($relativePath);
    if ($imagePath === false) {
        JsonResponse::send(['message' => 'Imagen no encontrada.'], 404);
    }
    header('Content-Type: ' . (mime_content_type($imagePath) ?: 'application/octet-stream'));
    header('Cache-Control: private, no-store');
    readfile($imagePath);
    exit;
}

function deletePrivateStorageFile(?string $relativePath): void
{
    $imagePath = resolvePrivateStorageFile($relativePath);
    if ($imagePath !== false) {
        unlink($imagePath);
    }
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
