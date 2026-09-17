<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppEvent;
use App\Models\WhatsAppEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');
        $expected = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Verification failed', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        $this->assertSignature($request);
        $payload = $request->json()->all();

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach (($value['messages'] ?? []) as $message) {
                    $waId = (string) ($message['from'] ?? '');
                    $messageId = (string) ($message['id'] ?? '');
                    $record = WhatsAppEvent::query()->firstOrCreate(
                        ['event_key' => $messageId !== '' ? 'message:'.$messageId : hash('sha256', json_encode($message))],
                        [
                            'wa_id' => $waId,
                            'message_id' => $messageId ?: null,
                            'event_type' => 'message.'.($message['type'] ?? 'unknown'),
                            'payload' => [
                                'message' => $message,
                                'contact' => $contacts->get($waId),
                                'metadata' => $value['metadata'] ?? null,
                            ],
                        ]
                    );
                    if ($record->wasRecentlyCreated) {
                        ProcessWhatsAppEvent::dispatch($record->id);
                    }
                }

                foreach (($value['statuses'] ?? []) as $status) {
                    $key = 'status:'.($status['id'] ?? '').':'.($status['status'] ?? '').':'.($status['timestamp'] ?? '');
                    $record = WhatsAppEvent::query()->firstOrCreate(
                        ['event_key' => $key],
                        [
                            'wa_id' => (string) ($status['recipient_id'] ?? ''),
                            'message_id' => $status['id'] ?? null,
                            'event_type' => 'status.'.($status['status'] ?? 'unknown'),
                            'payload' => $status,
                        ]
                    );
                    if ($record->wasRecentlyCreated) {
                        ProcessWhatsAppEvent::dispatch($record->id);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function assertSignature(Request $request): void
    {
        $secret = (string) config('services.whatsapp.app_secret');
        if ($secret === '') {
            abort_if(app()->isProduction(), 503, 'WhatsApp app secret is not configured.');
            return;
        }

        $provided = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($provided !== '' && hash_equals($expected, $provided), 401, 'Invalid webhook signature.');
    }
}
