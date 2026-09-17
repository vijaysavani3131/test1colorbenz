<?php

namespace App\Services;

class ImageCompressionService
{
    public const TARGET_BYTES = 307200; // ~300 KB

    /**
     * Best-effort evidence-image optimisation.
     *
     * The target is ~300 KB, but readability wins over a hard byte cap. We keep
     * JPEG/WebP quality at 80+ and do not convert PNG/PDF into a different format.
     * If GD is unavailable or the image cannot be decoded, original bytes are kept.
     */
    public function compress(string $bytes, string $mime, int $targetBytes = self::TARGET_BYTES): array
    {
        $mime = strtolower(trim($mime));
        $originalSize = strlen($bytes);

        if ($originalSize <= $targetBytes) {
            return $this->result($bytes, $mime, false, $originalSize, true);
        }

        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)
            || !function_exists('imagecreatefromstring')) {
            return $this->result($bytes, $mime, false, $originalSize, $originalSize <= $targetBytes);
        }

        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            return $this->result($bytes, $mime, false, $originalSize, false);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $best = $bytes;
        $bestSize = $originalSize;

        // Do not collapse evidence to tiny dimensions. 960px is the safety floor.
        $maxDimensions = [2400, 2100, 1800, 1600, 1440, 1280, 1120, 960];
        // Quality floor protects text/screenshots from becoming visibly damaged.
        $qualities = [90, 86, 83, 80];
        $targetMet = false;

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
                }
                imagedestroy($canvas);
                if ($bestSize <= $targetBytes) {
                    $targetMet = true;
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
                }

                if ($bestSize <= $targetBytes) {
                    $targetMet = true;
                    break;
                }
            }

            imagedestroy($canvas);
            if ($targetMet) {
                break;
            }
        }

        imagedestroy($source);

        return $this->result(
            $best,
            $mime,
            $bestSize < $originalSize,
            $originalSize,
            $bestSize <= $targetBytes,
        );
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

    private function result(
        string $bytes,
        string $mime,
        bool $compressed,
        int $originalSize,
        bool $targetMet,
    ): array {
        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'size_bytes' => strlen($bytes),
            'original_size_bytes' => $originalSize,
            'compressed' => $compressed,
            'target_met' => $targetMet,
        ];
    }
}
