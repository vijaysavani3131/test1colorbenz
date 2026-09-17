<?php

namespace App\Services;

class ImageCompressionService
{
    public const TARGET_BYTES = 307200;

    /**
     * Best-effort compression for JPG/PNG/WebP evidence.
     * Returns the original bytes unchanged when GD is unavailable or decoding fails.
     */
    public function compress(string $bytes, string $mime, int $targetBytes = self::TARGET_BYTES): array
    {
        $mime = strtolower(trim($mime));
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)
            || !function_exists('imagecreatefromstring')) {
            return $this->result($bytes, $mime, false);
        }

        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            return $this->result($bytes, $mime, false);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $best = $bytes;
        $bestSize = strlen($bytes);
        $compressed = false;

        $maxDimensions = [2200, 1800, 1500, 1280, 1100, 960, 840, 720];
        $qualities = [86, 82, 78, 74, 72];

        foreach ($maxDimensions as $maxDimension) {
            $scale = min(1, $maxDimension / max($width, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            if (!$canvas) {
                continue;
            }

            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if ($mime === 'image/png') {
                $candidate = $this->encodePng($canvas);
                if ($candidate !== null && strlen($candidate) < $bestSize) {
                    $best = $candidate;
                    $bestSize = strlen($candidate);
                    $compressed = true;
                }
                imagedestroy($canvas);
                if ($bestSize <= $targetBytes) {
                    break;
                }
                continue;
            }

            foreach ($qualities as $quality) {
                $candidate = $mime === 'image/webp'
                    ? $this->encodeWebp($canvas, $quality)
                    : $this->encodeJpeg($canvas, $quality);

                if ($candidate !== null && strlen($candidate) < $bestSize) {
                    $best = $candidate;
                    $bestSize = strlen($candidate);
                    $compressed = true;
                }
                if ($bestSize <= $targetBytes) {
                    break 2;
                }
            }
            imagedestroy($canvas);
        }

        imagedestroy($source);
        return $this->result($best, $mime, $compressed);
    }

    private function encodeJpeg($image, int $quality): ?string
    {
        if (!function_exists('imagejpeg')) {
            return null;
        }
        ob_start();
        $ok = imagejpeg($image, null, $quality);
        $bytes = ob_get_clean();
        return $ok && is_string($bytes) ? $bytes : null;
    }

    private function encodeWebp($image, int $quality): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $bytes = ob_get_clean();
        return $ok && is_string($bytes) ? $bytes : null;
    }

    private function encodePng($image): ?string
    {
        if (!function_exists('imagepng')) {
            return null;
        }
        ob_start();
        $ok = imagepng($image, null, 9);
        $bytes = ob_get_clean();
        return $ok && is_string($bytes) ? $bytes : null;
    }

    private function result(string $bytes, string $mime, bool $compressed): array
    {
        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'size_bytes' => strlen($bytes),
            'compressed' => $compressed,
        ];
    }
}
