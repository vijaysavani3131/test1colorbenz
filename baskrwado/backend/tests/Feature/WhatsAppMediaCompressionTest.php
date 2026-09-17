<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCaseDocument;
use App\Jobs\ProcessWhatsAppEvent;
use App\Models\CaseRecord;
use App\Models\ConversationSession;
use App\Models\WhatsAppEvent;
use App\Services\CaseWorkflowService;
use App\Services\ImageCompressionService;
use App\Services\OpenAiCaseService;
use App\Services\WhatsAppCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppMediaCompressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_image_is_optimised_before_private_storage(): void
    {
        Storage::fake('private');
        Bus::fake([AnalyzeCaseDocument::class]);

        $case = CaseRecord::create([
            'service_slug' => 'money_recovery',
            'source' => 'whatsapp',
            'locale' => 'en',
            'name' => 'WhatsApp Customer',
            'phone' => '9876543210',
            'status' => 'intake',
            'readiness_score' => 0,
        ]);

        ConversationSession::create([
            'case_record_id' => $case->id,
            'channel' => 'whatsapp',
            'external_id' => '919876543210',
            'locale' => 'en',
            'state' => 'collecting',
            'last_message_at' => now(),
        ]);

        $bytes = $this->largeJpeg();
        $this->assertGreaterThan(ImageCompressionService::TARGET_BYTES, strlen($bytes));

        $event = WhatsAppEvent::create([
            'event_key' => 'test-wa-media-1',
            'wa_id' => '919876543210',
            'message_id' => 'wamid.test-image-1',
            'event_type' => 'message.image',
            'payload' => [
                'message' => [
                    'from' => '919876543210',
                    'type' => 'image',
                    'image' => ['id' => 'media-test-1'],
                ],
                'contact' => ['profile' => ['name' => 'WhatsApp Customer']],
            ],
        ]);

        $whatsapp = $this->createMock(WhatsAppCloudService::class);
        $whatsapp->expects($this->once())
            ->method('markAsRead')
            ->with('wamid.test-image-1')
            ->willReturn([]);
        $whatsapp->expects($this->once())
            ->method('downloadMedia')
            ->with('media-test-1')
            ->willReturn([
                'bytes' => $bytes,
                'mime_type' => 'image/jpeg',
                'file_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
            ]);

        $outgoingId = 0;
        $whatsapp->method('sendText')->willReturnCallback(function () use (&$outgoingId): array {
            $outgoingId++;
            return ['messages' => [['id' => 'wamid.out-'.$outgoingId]]];
        });

        (new ProcessWhatsAppEvent($event->id))->handle(
            $whatsapp,
            new CaseWorkflowService(),
            new OpenAiCaseService(),
            new ImageCompressionService(),
        );

        $document = $case->documents()->firstOrFail();
        $this->assertSame('whatsapp', $document->source);
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertLessThan(strlen($bytes), $document->size_bytes);
        Storage::disk('private')->assertExists($document->storage_path);
        $stored = Storage::disk('private')->get($document->storage_path);
        $this->assertSame(strlen($stored), $document->size_bytes);
        $this->assertSame(hash('sha256', $stored), $document->sha256);
        Bus::assertDispatched(AnalyzeCaseDocument::class);
    }

    private function largeJpeg(): string
    {
        $width = 2400;
        $height = 1800;
        $image = imagecreatetruecolor($width, $height);
        mt_srand(20260917);

        for ($y = 0; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x += 8) {
                $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                imagefilledrectangle($image, $x, $y, min($x + 7, $width - 1), min($y + 7, $height - 1), $colour);
            }
        }

        ob_start();
        imagejpeg($image, null, 100);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return (string) $bytes;
    }
}
