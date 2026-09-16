<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    public function sendText(string $to, string $body): array
    {
        return $this->send($to, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendButtons(string $to, string $body, array $buttons): array
    {
        $buttons = array_slice($buttons, 0, 3);
        return $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => array_map(fn (array $button) => [
                        'type' => 'reply',
                        'reply' => [
                            'id' => (string) $button['id'],
                            'title' => mb_substr((string) $button['title'], 0, 20),
                        ],
                    ], $buttons),
                ],
            ],
        ]);
    }

    public function sendTemplate(string $to, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        return $this->send($to, [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);
    }

    public function markAsRead(string $messageId): array
    {
        return $this->client()->post($this->messagesUrl(), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ])->throw()->json();
    }

    public function downloadMedia(string $mediaId): array
    {
        $version = (string) config('services.whatsapp.graph_version', 'v26.0');
        $meta = $this->client()->get("https://graph.facebook.com/{$version}/{$mediaId}")->throw()->json();
        $url = (string) ($meta['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('WhatsApp media URL missing.');
        }

        $response = Http::withToken((string) config('services.whatsapp.access_token'))
            ->timeout(30)
            ->retry(2, 400)
            ->get($url)
            ->throw();

        return [
            'bytes' => $response->body(),
            'mime_type' => (string) ($meta['mime_type'] ?? $response->header('Content-Type') ?? 'application/octet-stream'),
            'sha256' => (string) ($meta['sha256'] ?? hash('sha256', $response->body())),
            'file_size' => (int) ($meta['file_size'] ?? strlen($response->body())),
        ];
    }

    private function send(string $to, array $message): array
    {
        return $this->client()->post($this->messagesUrl(), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            ...$message,
        ])->throw()->json();
    }

    private function client(): PendingRequest
    {
        $token = (string) config('services.whatsapp.access_token');
        if ($token === '') {
            throw new RuntimeException('WHATSAPP_ACCESS_TOKEN is not configured.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300);
    }

    private function messagesUrl(): string
    {
        $version = (string) config('services.whatsapp.graph_version', 'v26.0');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        if ($phoneNumberId === '') {
            throw new RuntimeException('WHATSAPP_PHONE_NUMBER_ID is not configured.');
        }

        return "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    }
}
