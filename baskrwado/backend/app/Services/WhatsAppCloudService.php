<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    public function sendText(string $to, string $body): array
    {
        return $this->client()->post($this->messagesUrl(), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ])->throw()->json();
    }

    public function markAsRead(string $messageId): array
    {
        return $this->client()->post($this->messagesUrl(), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
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
            ->timeout(15)
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
