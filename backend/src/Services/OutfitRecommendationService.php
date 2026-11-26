<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class OutfitRecommendationService
{
    public function suggest(int $userId, ?string $occasion = null, ?string $style = null, ?string $season = null, ?string $palette = null): array
    {
        $statement = Connection::create()->prepare('SELECT id, name, category, primary_color, style, season, image_path, is_favorite FROM garments WHERE user_id = :user_id ORDER BY is_favorite DESC, created_at DESC');
        $statement->execute(['user_id' => $userId]);
        $garments = $statement->fetchAll();

        $groups = [];
        foreach ($garments as $garment) {
            $garment['_score'] = $this->garmentScore($garment, $occasion, $style, $season, $palette);
            $groups[$garment['category']][] = $garment;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => $right['_score'] <=> $left['_score']);
        }
        unset($group);

        $dress = $groups['dress'][0] ?? null;
        $top = $groups['top'][0] ?? null;
        $bottom = $groups['bottom'][0] ?? null;
        $shoes = $groups['shoes'][0] ?? null;
        $selected = $dress !== null ? [$dress, $shoes] : [$top, $bottom, $shoes];
        $selected = array_values(array_filter($selected));

        if ($dress === null && $top !== null && $bottom !== null && !$this->colorsWorkTogether($top, $bottom)) {
            $bottom = $this->bestCompatible($groups['bottom'] ?? [], $top) ?? $bottom;
            $selected = array_values(array_filter([$top, $bottom, $shoes]));
        }
        $selected = array_map(static function (array $garment): array {
            unset($garment['_score']);

            return $garment;
        }, $selected);

        if (count($selected) < 2) {
            return ['data' => [], 'message' => 'Aún faltan prendas para crear una combinación. Añade una parte superior, una parte inferior o un vestido y unos zapatos.'];
        }

        $selectedIds = array_map(static fn (array $garment): int => (int) $garment['id'], $selected);
        $alternatives = [];
        foreach ($selected as $garment) {
            $candidates = array_filter($groups[$garment['category']] ?? [], static fn (array $candidate): bool => !in_array((int) $candidate['id'], $selectedIds, true));
            $candidates = array_values(array_filter($candidates, fn (array $candidate): bool => $this->isCompatibleAlternative($candidate, $selected)));
            $alternatives[(string) $garment['id']] = array_map(static function (array $candidate): array {
                unset($candidate['_score']);

                return $candidate;
            }, array_slice($candidates, 0, 3));
        }

        $description = sprintf('He elegido una combinación %s para %s%s%s.', $style ?: 'versátil', $occasion ?: 'tu día', $style ? ' con un toque ' . $style : '', $season ? ' y la temporada ' . $season : '');
        $explanation = 'La propuesta equilibra las categorías disponibles y combina colores compatibles';
        if ($style) {
            $explanation .= ' y prioriza prendas de estilo ' . $style;
        }
        if ($season) {
            $explanation .= ' para ' . $season;
        }

        if ($palette !== null) {
            $explanation .= ' y prioriza los colores de tu paleta de colorimetría';
        }

        return ['data' => [['garments' => $selected, 'alternatives' => $alternatives, 'explanation' => $description, 'reason' => $explanation . '.']]];
    }

    private function garmentScore(array $garment, ?string $occasion, ?string $style, ?string $season, ?string $palette): int
    {
        $score = (int) $garment['is_favorite'];
        if ($style !== null && $style !== '' && strcasecmp((string) $garment['style'], $style) === 0) {
            $score += 4;
        }
        if ($season !== null && $season !== '' && strcasecmp((string) $garment['season'], $season) === 0) {
            $score += 4;
        }
        $occasionStyles = ['oficina' => ['elegante', 'minimalista'], 'evento' => ['elegante'], 'viaje' => ['cómodo', 'casual']];
        if ($occasion !== null && in_array((string) $garment['style'], $occasionStyles[$occasion] ?? [], true)) {
            $score += 2;
        }
        if ($palette !== null && in_array(mb_strtolower(trim((string) $garment['primary_color'])), self::paletteColors($palette), true)) {
            $score += 5;
        }

        return $score;
    }

    private static function paletteColors(string $palette): array
    {
        return match ($palette) {
            'warm' => ['rojo', 'marrón', 'marron', 'beige', 'amarillo', 'verde'],
            'cool' => ['azul', 'gris', 'rosa', 'verde', 'morado', 'blanco'],
            'neutral' => ['negro', 'blanco', 'gris', 'beige', 'marrón', 'marron'],
            default => [],
        };
    }

    private function bestCompatible(array $candidates, array $garment): ?array
    {
        foreach ($candidates as $candidate) {
            if ($this->colorsWorkTogether($garment, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isCompatibleAlternative(array $candidate, array $selected): bool
    {
        foreach ($selected as $garment) {
            if ($garment['category'] !== $candidate['category'] && !$this->colorsWorkTogether($candidate, $garment)) {
                return false;
            }
        }

        return true;
    }

    private function colorsWorkTogether(array $first, array $second): bool
    {
        $neutral = ['negro', 'blanco', 'gris', 'beige', 'marrón', 'marron', 'azul marino', 'denim'];
        $firstColor = mb_strtolower(trim((string) $first['primary_color']));
        $secondColor = mb_strtolower(trim((string) $second['primary_color']));
        if ($firstColor === '' || $secondColor === '' || $firstColor === $secondColor) {
            return true;
        }
        if (in_array($firstColor, $neutral, true) || in_array($secondColor, $neutral, true)) {
            return true;
        }

        $compatible = ['rojo' => ['azul', 'verde'], 'azul' => ['rojo', 'amarillo', 'naranja'], 'verde' => ['rojo', 'rosa'], 'rosa' => ['verde', 'gris'], 'amarillo' => ['azul', 'morado'], 'naranja' => ['azul'], 'morado' => ['amarillo']];

        return in_array($secondColor, $compatible[$firstColor] ?? [], true) || in_array($firstColor, $compatible[$secondColor] ?? [], true);
    }
}
