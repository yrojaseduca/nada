<?php

declare(strict_types=1);

namespace App\Services;

final class GarmentImageEnhancer
{
    public function enhance(string $sourcePath, string $destinationPath, string $mimeType, bool $smoothWrinkles = false, bool $enhanceLight = true): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $contents = file_get_contents($sourcePath);
        $image = $contents === false ? false : @imagecreatefromstring($contents);
        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);
            return false;
        }

        // Gentle gamma correction lifts dark photos while preserving the existing color palette.
        if ($enhanceLight) {
            imagegammacorrect($image, 1.0, 0.88);
            imagefilter($image, IMG_FILTER_CONTRAST, -3);
            imagefilter($image, IMG_FILTER_SMOOTH, 3);
        }
        if ($smoothWrinkles) {
            $softened = imagecreatetruecolor($width, $height);
            imagecopy($softened, $image, 0, 0, 0, 0, $width, $height);
            imagefilter($softened, IMG_FILTER_GAUSSIAN_BLUR);
            imagecopymerge($image, $softened, 0, 0, 0, 0, $width, $height, 18);
            imagedestroy($softened);
        }

        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destinationPath, 92),
            'image/png' => imagepng($image, $destinationPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $destinationPath, 92) : false,
            default => false,
        };
        imagedestroy($image);

        return $saved;
    }
}
