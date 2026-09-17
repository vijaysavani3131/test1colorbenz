<?php

namespace App\Services;

class DocumentImageOptimizer
{
    public const TARGET_BYTES = 307200;

    /**
     * Optimises JPG/PNG/WEBP evidence towards ~300 KB while keeping dimensions
     * as high as practical. If GD/WebP support is unavailable, the original
     * bytes are returned unchanged. PDFs and other document types are untouched.
     */
    public function optimize(string $bytes, string $mime, int $targetBytes = self::TARGET_BYTES): array
    {
        if (strlen($bytes) <= $targetBytes || !str_starts_with($mime, 'image/')) {
            return $this->original($bytes, $mime);
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return $this->original($bytes, $mime);
        }

        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            return $this->original($bytes, $mime);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return $this->original($bytes, $mime);
        }

        $best = null;
        $scale = 1.0;
        $quality = 90;

        for ($attempt = 0; $attempt < 18; $attempt++) {
            $width = max(720, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
            if ($width > $sourceWidth) {
                $width = $sourceWidth;
                $height = $sourceHeight;
            }

            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            ob_start();
            imagewebp($canvas, null, $quality);
            $candidate = (string) ob_get_clean();
            imagedestroy($canvas);

            if ($candidate !== '' && ($best === null || strlen($candidate) < strlen($best))) {
                $best = $candidate;
            }

            if ($candidate !== '' && strlen($candidate) <= $targetBytes) {
                imagedestroy($source);
                return [
                    'bytes' => $candidate,
                    'mime' => 'image/webp',
                    'extension' => 'webp',
                    'optimized' => true,
                ];
            }

            if ($quality > 72) {
                $quality -= 6;
            } else {
                $scale *= 0.86;
                $quality = 86;
            }
        }

        imagedestroy($source);

        if ($best !== null && strlen($best) < strlen($bytes)) {
            return [
                'bytes' => $best,
                'mime' => 'image/webp',
                'extension' => 'webp',
                'optimized' => true,
            ];
        }

        return $this->original($bytes, $mime);
    }

    private function original(string $bytes, string $mime): array
    {
        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
                default => 'bin',
            },
            'optimized' => false,
        ];
    }
}
