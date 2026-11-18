<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class OutfitRecommendationService
{
    public function suggest(int $userId, ?string $occasion = null, ?string $style = null): array
    {
        $statement = Connection::create()->prepare('SELECT id, name, category, primary_color, style, season, image_path FROM garments WHERE user_id = :user_id ORDER BY is_favorite DESC, created_at DESC');
        $statement->execute(['user_id' => $userId]);
        $garments = $statement->fetchAll();

        $groups = [];
        foreach ($garments as $garment) {
            $groups[$garment['category']][] = $garment;
        }

        $top = $groups['top'][0] ?? null;
        $bottom = $groups['bottom'][0] ?? null;
        $dress = $groups['dress'][0] ?? null;
        $shoes = $groups['shoes'][0] ?? null;
        $selected = $dress !== null ? [$dress, $shoes] : [$top, $bottom, $shoes];
        $selected = array_values(array_filter($selected));

        if (count($selected) < 2) {
            return ['data' => [], 'message' => 'Aún faltan prendas para crear una combinación. Añade una parte superior, una parte inferior o un vestido y unos zapatos.'];
        }

        $description = sprintf('Combinación %s para %s%s.', $style ?: 'versátil', $occasion ?: 'tu día', $style ? ' con un toque ' . $style : '');

        return ['data' => [['garments' => $selected, 'explanation' => $description]]];
    }
}
