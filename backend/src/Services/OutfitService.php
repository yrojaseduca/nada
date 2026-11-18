<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;

final class OutfitService
{
    public function list(int $userId, ?string $occasion = null, ?string $style = null): array
    {
        $conditions = ['user_id = :user_id'];
        $parameters = ['user_id' => $userId];
        if ($occasion !== null && $occasion !== '') { $conditions[] = 'occasion = :occasion'; $parameters['occasion'] = $occasion; }
        if ($style !== null && $style !== '') { $conditions[] = 'style = :style'; $parameters['style'] = $style; }
        $statement = Connection::create()->prepare(sprintf('SELECT * FROM outfits WHERE %s ORDER BY created_at DESC', implode(' AND ', $conditions)));
        $statement->execute($parameters);
        $outfits = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($outfits as &$outfit) {
            $outfit = $this->hydrate($outfit, $userId);
        }

        return $outfits;
    }

    public function create(int $userId, array $data): array
    {
        $garmentIds = array_values(array_unique(array_map('intval', $data['garment_ids'] ?? [])));
        if ($garmentIds === []) {
            throw new RuntimeException('Selecciona al menos una prenda.');
        }

        $connection = Connection::create();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare('INSERT INTO outfits (user_id, name, occasion, style, season, notes) VALUES (:user_id, :name, :occasion, :style, :season, :notes)');
            $statement->execute([
                'user_id' => $userId,
                'name' => trim((string) ($data['name'] ?? 'Mi look')),
                'occasion' => $data['occasion'] ?? null,
                'style' => $data['style'] ?? null,
                'season' => $data['season'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $outfitId = (int) $connection->lastInsertId();
            $garmentStatement = $connection->prepare('INSERT INTO outfit_garments (outfit_id, garment_id, position) SELECT :outfit_id, id, :position FROM garments WHERE id = :garment_id AND user_id = :user_id');
            foreach ($garmentIds as $position => $garmentId) {
                $garmentStatement->execute(['outfit_id' => $outfitId, 'garment_id' => $garmentId, 'user_id' => $userId, 'position' => (string) $position]);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->find($userId, $outfitId);
    }

    public function find(int $userId, int $id): array
    {
        $statement = Connection::create()->prepare('SELECT * FROM outfits WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $outfit = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($outfit)) throw new RuntimeException('Look no encontrado.');

        return $this->hydrate($outfit, $userId);
    }

    public function update(int $userId, int $id, array $data): array
    {
        $garmentIds = array_values(array_unique(array_map('intval', $data['garment_ids'] ?? [])));
        if ($garmentIds === []) throw new RuntimeException('Selecciona al menos una prenda.');
        $connection = Connection::create();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare('UPDATE outfits SET name = :name, occasion = :occasion, style = :style, season = :season, notes = :notes WHERE id = :id AND user_id = :user_id');
            $statement->execute(['id' => $id, 'user_id' => $userId, 'name' => trim((string) $data['name']), 'occasion' => $data['occasion'] ?? null, 'style' => $data['style'] ?? null, 'season' => $data['season'] ?? null, 'notes' => $data['notes'] ?? null]);
            $connection->prepare('DELETE FROM outfit_garments WHERE outfit_id = :outfit_id')->execute(['outfit_id' => $id]);
            $relation = $connection->prepare('INSERT INTO outfit_garments (outfit_id, garment_id, position) SELECT :outfit_id, id, :position FROM garments WHERE id = :garment_id AND user_id = :user_id');
            foreach ($garmentIds as $position => $garmentId) $relation->execute(['outfit_id' => $id, 'garment_id' => $garmentId, 'user_id' => $userId, 'position' => (string) $position]);
            $connection->commit();
        } catch (\Throwable $exception) { $connection->rollBack(); throw $exception; }
        return $this->find($userId, $id);
    }

    public function delete(int $userId, int $id): void
    {
        $statement = Connection::create()->prepare('DELETE FROM outfits WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        if ($statement->rowCount() === 0) throw new RuntimeException('Look no encontrado.');
    }

    public function toggleFavorite(int $userId, int $id): array
    {
        $statement = Connection::create()->prepare('UPDATE outfits SET is_favorite = NOT is_favorite WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $this->find($userId, $id);
    }

    private function hydrate(array $outfit, int $userId): array
    {
        $statement = Connection::create()->prepare('SELECT g.* FROM garments g INNER JOIN outfit_garments og ON og.garment_id = g.id WHERE og.outfit_id = :outfit_id AND g.user_id = :user_id ORDER BY og.position');
        $statement->execute(['outfit_id' => $outfit['id'], 'user_id' => $userId]);
        $outfit['id'] = (int) $outfit['id'];
        $outfit['is_favorite'] = (bool) $outfit['is_favorite'];
        $outfit['garments'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $outfit;
    }
}
