<?php

namespace Tests\Unit;

use App\Services\ImageCompressionService;
use PHPUnit\Framework\TestCase;

class ImageCompressionServiceTest extends TestCase
{
    public function test_large_jpeg_is_reduced_without_changing_format(): void
    {
        $this->assertTrue(function_exists('imagecreatetruecolor'), 'GD must be enabled for image compression tests.');

        $image = imagecreatetruecolor(1600, 1200);
        for ($y = 0; $y < 1200; $y += 8) {
            for ($x = 0; $x < 1600; $x += 8) {
                $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                imagefilledrectangle($image, $x, $y, min($x + 7, 1599), min($y + 7, 1199), $colour);
            }
        }

        ob_start();
        imagejpeg($image, null, 98);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($bytes);
        $this->assertGreaterThan(ImageCompressionService::TARGET_BYTES, strlen($bytes));

        $result = (new ImageCompressionService())->compress($bytes, 'image/jpeg');

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame(strlen($bytes), $result['original_size_bytes']);
        $this->assertTrue($result['compressed']);
        $this->assertLessThan(strlen($bytes), $result['size_bytes']);
        $this->assertSame($result['size_bytes'] <= ImageCompressionService::TARGET_BYTES, $result['target_met']);
    }

    public function test_small_image_is_not_reencoded(): void
    {
        $image = imagecreatetruecolor(120, 80);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 119, 79, $white);

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $result = (new ImageCompressionService())->compress($bytes, 'image/jpeg');

        $this->assertFalse($result['compressed']);
        $this->assertTrue($result['target_met']);
        $this->assertSame($bytes, $result['bytes']);
    }

    public function test_non_image_bytes_are_preserved(): void
    {
        $bytes = str_repeat('%PDF-test-data-', 30000);
        $result = (new ImageCompressionService())->compress($bytes, 'application/pdf');

        $this->assertFalse($result['compressed']);
        $this->assertSame($bytes, $result['bytes']);
        $this->assertSame('application/pdf', $result['mime']);
    }
}
