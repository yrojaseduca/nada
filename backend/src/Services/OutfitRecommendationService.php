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
        if ($palette !== null) {
            $score += self::paletteColorScore((string) $garment['primary_color'], $palette);
        }

        return $score;
    }

    private static function paletteColorScore(string $color, string $palette): int
    {
        $garmentColors = [
            'negro' => '#282724', 'blanco' => '#ffffff', 'gris' => '#8e857a', 'azul' => '#426a8c',
            'rojo' => '#b95750', 'verde' => '#668267', 'marrón' => '#8b654d', 'marron' => '#8b654d',
            'beige' => '#dccbb9', 'rosa' => '#d99ba4', 'amarillo' => '#d7b84b',
        };
        $palettes = [
            'warm' => ['#A64E3C', '#D58B55', '#D8B65C', '#6D8054', '#2F625A', '#8C514B'],
            'cool' => ['#6D7896', '#788DA8', '#658A83', '#8B7189', '#A96878', '#525E78'],
            'neutral' => ['#8A7867', '#B49B7D', '#879078', '#7E8585', '#A08078', '#655E58'],
        ];
        $normalizedColor = mb_strtolower(trim($color));
        $hex = $garmentColors[$normalizedColor] ?? null;
        if ($hex === null || !isset($palettes[$palette])) return 0;
        $rgb = self::hexToRgb($hex);
        $minimumDistance = min(array_map(static fn (string $recommended): float => self::colorDistance($rgb, self::hexToRgb($recommended)), $palettes[$palette]));

        return $minimumDistance < 45 ? 8 : ($minimumDistance < 75 ? 4 : 0);
    }

    private static function hexToRgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function colorDistance(array $first, array $second): float
    {
        return sqrt(($first[0] - $second[0]) ** 2 + ($first[1] - $second[1]) ** 2 + ($first[2] - $second[2]) ** 2);
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
