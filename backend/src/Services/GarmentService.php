<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class GarmentService
{
    public function list(int $userId, ?string $category = null, ?string $search = null): array
    {
        $conditions = ['user_id = :user_id'];
        $parameters = ['user_id' => $userId];

        if ($category !== null && $category !== '') {
            $conditions[] = 'category = :category';
            $parameters['category'] = $category;
        }

        if ($search !== null && $search !== '') {
            $conditions[] = '(name LIKE :search OR brand LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }

        $sql = sprintf(
            'SELECT id, name, category, subcategory, primary_color, secondary_colors, season, style, brand, notes, image_path, is_favorite, created_at, updated_at FROM garments WHERE %s ORDER BY created_at DESC',
            implode(' AND ', $conditions)
        );
        $statement = Connection::create()->prepare($sql);
        $statement->execute($parameters);

        return array_map(static function (array $garment): array {
            $garment['id'] = (int) $garment['id'];
            $garment['is_favorite'] = (bool) $garment['is_favorite'];
            $garment['secondary_colors'] = $garment['secondary_colors'] === null
                ? []
                : json_decode((string) $garment['secondary_colors'], true, 512, JSON_THROW_ON_ERROR);

            return $garment;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(int $userId, array $data): array
    {
        $connection = Connection::create();
        $statement = $connection->prepare(
            'INSERT INTO garments (user_id, name, category, subcategory, primary_color, secondary_colors, season, style, brand, notes, image_path) VALUES (:user_id, :name, :category, :subcategory, :primary_color, :secondary_colors, :season, :style, :brand, :notes, :image_path)'
        );
        $statement->execute([
            'user_id' => $userId,
            'name' => $data['name'],
            'category' => $data['category'],
            'subcategory' => $data['subcategory'] ?? null,
            'primary_color' => $data['primary_color'],
            'secondary_colors' => isset($data['secondary_colors']) ? json_encode($data['secondary_colors'], JSON_THROW_ON_ERROR) : null,
            'season' => $data['season'] ?? null,
            'style' => $data['style'] ?? null,
            'brand' => $data['brand'] ?? null,
            'notes' => $data['notes'] ?? null,
            'image_path' => $data['image_path'] ?? null,
        ]);

        return $this->find($userId, (int) $connection->lastInsertId());
    }

    public function find(int $userId, int $id): array
    {
        $statement = Connection::create()->prepare('SELECT * FROM garments WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $garment = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($garment)) {
            throw new \RuntimeException('Prenda no encontrada.');
        }

        $garment['id'] = (int) $garment['id'];
        $garment['is_favorite'] = (bool) $garment['is_favorite'];
        $garment['secondary_colors'] = $garment['secondary_colors'] === null
            ? []
            : json_decode((string) $garment['secondary_colors'], true, 512, JSON_THROW_ON_ERROR);

        return $garment;
    }

    public function update(int $userId, int $id, array $data): array
    {
        $statement = Connection::create()->prepare('UPDATE garments SET name = :name, category = :category, primary_color = :primary_color, season = :season, style = :style, brand = :brand, notes = :notes WHERE id = :id AND user_id = :user_id');
        $statement->execute([
            'id' => $id,
            'user_id' => $userId,
            'name' => trim((string) $data['name']),
            'category' => trim((string) $data['category']),
            'primary_color' => trim((string) $data['primary_color']),
            'season' => $data['season'] ?? null,
            'style' => $data['style'] ?? null,
            'brand' => $data['brand'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->find($userId, $id);
    }

    public function delete(int $userId, int $id): void
    {
        $statement = Connection::create()->prepare('DELETE FROM garments WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);

        if ($statement->rowCount() === 0) {
            throw new \RuntimeException('Prenda no encontrada.');
        }
    }

    public function toggleFavorite(int $userId, int $id): array
    {
        $statement = Connection::create()->prepare('UPDATE garments SET is_favorite = NOT is_favorite WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);

        return $this->find($userId, $id);
    }
}
