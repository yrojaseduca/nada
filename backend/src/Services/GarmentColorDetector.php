<?php

declare(strict_types=1);

namespace App\Services;

final class GarmentColorDetector
{
    public function detect(string $imagePath): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $contents = file_get_contents($imagePath);
        $image = $contents === false ? false : @imagecreatefromstring($contents);
        if ($image === false) return null;

        $width = imagesx($image);
        $height = imagesy($image);
        $samples = [];
        for ($y = (int) ($height * 0.2); $y < (int) ($height * 0.8); $y += max(1, (int) ($height / 24))) {
            for ($x = (int) ($width * 0.2); $x < (int) ($width * 0.8); $x += max(1, (int) ($width / 24))) {
                $rgb = imagecolorat($image, $x, $y);
                $samples[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
        }
        imagedestroy($image);
        if ($samples === []) return null;

        $average = [0, 0, 0];
        foreach ($samples as [$red, $green, $blue]) {
            $average[0] += $red;
            $average[1] += $green;
            $average[2] += $blue;
        }
        $average = array_map(static fn (int $channel): int => (int) round($channel / count($samples)), $average);
        [$red, $green, $blue] = $average;
        $maximum = max($average);
        $minimum = min($average);
        if ($maximum < 55) return 'Negro';
        if ($minimum > 220) return 'Blanco';
        if ($maximum - $minimum < 22) return $maximum < 145 ? 'Gris' : 'Beige';
        if ($red > $green * 1.18 && $red > $blue * 1.22) return $green > 120 ? 'Rosa' : ($red - $green > 65 ? 'Rojo' : 'Marrón');
        if ($green > $red * 1.12 && $green > $blue * 1.05) return 'Verde';
        if ($blue > $red * 1.08 && $blue > $green * 1.03) return 'Azul';
        if ($red > 150 && $green > 125 && $blue < 130) return 'Amarillo';

        return 'Marrón';
    }
}
