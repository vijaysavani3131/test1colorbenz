<?php

namespace App\Jobs;

use App\Models\CaseDocument;
use App\Models\CaseRecord;
use App\Models\ConversationMessage;
use App\Models\ConversationSession;
use App\Models\WhatsAppEvent;
use App\Services\CaseWorkflowService;
use App\Services\OpenAiCaseService;
use App\Services\WhatsAppCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessWhatsAppEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(public int $eventId) {}

    public function handle(WhatsAppCloudService $whatsapp, CaseWorkflowService $workflow, OpenAiCaseService $ai): void
    {
        $event = WhatsAppEvent::query()->find($this->eventId);
        if (!$event || $event->processed_at) {
            return;
        }

        if (str_starts_with($event->event_type, 'status.')) {
            ConversationMessage::query()
                ->where('external_message_id', $event->message_id)
                ->update(['status' => Str::after($event->event_type, 'status.')]);
            $event->forceFill(['processed_at' => now()])->save();
            return;
        }

        $payload = $event->payload ?? [];
        $message = $payload['message'] ?? [];
        $contact = $payload['contact'] ?? [];
        $waId = (string) ($event->wa_id ?: ($message['from'] ?? ''));
        if ($waId === '') {
            $event->forceFill(['processed_at' => now()])->save();
            return;
        }

        $session = ConversationSession::query()->firstOrCreate(
            ['channel' => 'whatsapp', 'external_id' => $waId],
            ['locale' => 'en', 'state' => 'new', 'last_message_at' => now()],
        );
        $session->forceFill(['last_message_at' => now()])->saveQuietly();

        $text = trim($this->extractText($message));
        ConversationMessage::query()->firstOrCreate(
            ['external_message_id' => $event->message_id],
            [
                'conversation_session_id' => $session->id,
                'direction' => 'in',
                'message_type' => (string) ($message['type'] ?? 'unknown'),
                'text' => $text ?: null,
                'payload' => $message,
                'status' => 'received',
            ],
        );

        try {
            if ($event->message_id) {
                $whatsapp->markAsRead($event->message_id);
            }
        } catch (Throwable $e) {
            report($e);
        }

        if ($locale = $this->localeCommand($text)) {
            $session->forceFill(['locale' => $locale])->save();
            $this->reply($session, $whatsapp, $this->languageChangedText($locale));
            $text = '';
        }

        $case = $session->case_record_id ? CaseRecord::query()->find($session->case_record_id) : null;

        if (!$case) {
            $serviceSlug = $this->serviceFromMenu($text);
            $classification = $ai->classifyInbound($text, $session->locale);
            $serviceSlug = $serviceSlug ?: ($classification['service_slug'] ?? 'unknown');
            $locale = $classification['locale'] ?? $session->locale;
            $session->forceFill(['locale' => $locale])->save();

            if (!$workflow->find($serviceSlug)) {
                $session->forceFill(['state' => 'awaiting_service'])->save();
                $this->reply($session, $whatsapp, $this->serviceMenu($locale));
                $event->forceFill(['processed_at' => now()])->save();
                return;
            }

            $case = CaseRecord::create([
                'service_slug' => $serviceSlug,
                'source' => 'whatsapp',
                'locale' => $locale,
                'name' => (string) data_get($contact, 'profile.name', 'WhatsApp Customer'),
                'phone' => $this->normalizePhone($waId),
                'status' => 'intake',
                'readiness_score' => 0,
            ]);
            $workflow->refreshReport($case);
            $workflow->recordEvent($case, 'case_created', 'Case started on WhatsApp.', 'customer');
            $session->forceFill([
                'case_record_id' => $case->id,
                'state' => 'collecting',
                'locale' => $case->locale,
            ])->save();
            $this->reply($session, $whatsapp, $this->caseStartedText($case));
        }

        if ($this->isMediaMessage($message)) {
            $this->storeMedia($case, $message, $whatsapp, $workflow);
            $this->reply($session, $whatsapp, $this->documentReceivedText($session->locale));
        }

        if ($text !== '') {
            $lower = Str::lower($text);
            if (in_array($lower, ['status', 'case status', 'स्थिति', 'સ્ટેટસ'], true)) {
                $this->reply($session, $whatsapp, $this->statusText($case));
                $event->forceFill(['processed_at' => now()])->save();
                return;
            }

            if (in_array($lower, ['human', 'agent', 'person', 'executive', 'मानव', 'એજન્ટ'], true)) {
                $case->forceFill(['status' => 'ready_for_review', 'priority' => 'high', 'last_activity_at' => now()])->save();
                $session->forceFill(['state' => 'human_handoff'])->save();
                $workflow->recordEvent($case, 'human_handoff_requested', 'Customer requested a human agent.', 'customer');
                $this->reply($session, $whatsapp, $this->humanHandoffText($session->locale, $case->public_id));
                $event->forceFill(['processed_at' => now()])->save();
                return;
            }

            if ($session->current_question_key) {
                $case->answers()->updateOrCreate(
                    ['key' => $session->current_question_key],
                    ['value' => Str::limit($text, 5000, '')],
                );
                $session->forceFill(['current_question_key' => null])->save();
                $case = $workflow->refreshReport($case);
            }
        }

        if ($session->state !== 'human_handoff') {
            $question = $workflow->nextQuestion($case);
            if ($question) {
                $session->forceFill(['state' => 'collecting', 'current_question_key' => $question['key']])->save();
                $this->reply($session, $whatsapp, $this->questionText($question, $session->locale));
            } else {
                $session->forceFill(['state' => 'ready', 'current_question_key' => null])->save();
                GenerateCaseAiReport::dispatch($case->id);
                $this->reply($session, $whatsapp, $this->intakeCompleteText($case, $session->locale));
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
    }

    private function reply(ConversationSession $session, WhatsAppCloudService $whatsapp, string $text): void
    {
        $response = $whatsapp->sendText($session->external_id, $text);
        ConversationMessage::create([
            'conversation_session_id' => $session->id,
            'direction' => 'out',
            'external_message_id' => data_get($response, 'messages.0.id'),
            'message_type' => 'text',
            'text' => $text,
            'payload' => $response,
            'status' => 'sent',
        ]);
    }

    private function storeMedia(CaseRecord $case, array $message, WhatsAppCloudService $whatsapp, CaseWorkflowService $workflow): void
    {
        $type = (string) ($message['type'] ?? '');
        $media = $message[$type] ?? [];
        $mediaId = (string) ($media['id'] ?? '');
        if ($mediaId === '') {
            return;
        }

        $download = $whatsapp->downloadMedia($mediaId);
        if (($download['file_size'] ?? 0) > 8 * 1024 * 1024) {
            return;
        }

        $mime = (string) ($download['mime_type'] ?? 'application/octet-stream');
        $extension = match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            default => 'bin',
        };
        $original = (string) ($media['filename'] ?? ('whatsapp-'.$mediaId.'.'.$extension));
        $path = 'cases/'.$case->public_id.'/'.Str::uuid().'.'.$extension;
        Storage::disk('private')->put($path, $download['bytes']);

        $document = CaseDocument::create([
            'case_record_id' => $case->id,
            'category' => 'evidence',
            'original_name' => Str::limit($original, 255, ''),
            'mime_type' => $mime,
            'size_bytes' => (int) ($download['file_size'] ?? strlen($download['bytes'])),
            'storage_disk' => 'private',
            'storage_path' => $path,
            'sha256' => (string) ($download['sha256'] ?? hash('sha256', $download['bytes'])),
            'source' => 'whatsapp',
            'status' => 'uploaded',
        ]);

        $workflow->recordEvent($case, 'document_uploaded', 'WhatsApp document received: '.$document->original_name, 'customer', null, ['document_id' => $document->id]);
        AnalyzeCaseDocument::dispatch($document->id);
    }

    private function extractText(array $message): string
    {
        return match ($message['type'] ?? '') {
            'text' => (string) data_get($message, 'text.body', ''),
            'button' => (string) data_get($message, 'button.text', ''),
            'interactive' => (string) (data_get($message, 'interactive.button_reply.id') ?: data_get($message, 'interactive.list_reply.id') ?: data_get($message, 'interactive.button_reply.title') ?: data_get($message, 'interactive.list_reply.title') ?: ''),
            'image' => (string) data_get($message, 'image.caption', ''),
            'document' => (string) data_get($message, 'document.caption', ''),
            default => '',
        };
    }

    private function isMediaMessage(array $message): bool
    {
        return in_array($message['type'] ?? '', ['image', 'document'], true);
    }

    private function serviceFromMenu(string $text): ?string
    {
        $value = Str::lower(trim($text));
        return match ($value) {
            '1', 'money_recovery', 'paisa wapas' => 'money_recovery',
            '2', 'after_sales', 'warranty' => 'after_sales',
            '3', 'identity_repair', 'document' => 'identity_repair',
            '4', 'move_life', 'address' => 'move_life',
            '5', 'marriage_sync', 'marriage' => 'marriage_sync',
            '6', 'new_baby', 'baby' => 'new_baby',
            '7', 'after_loss', 'family' => 'after_loss',
            default => null,
        };
    }

    private function localeCommand(string $text): ?string
    {
        return match (Str::lower(trim($text))) {
            'en', 'english' => 'en',
            'hi', 'hindi', 'हिंदी' => 'hi',
            'gu', 'gujarati', 'ગુજરાતી' => 'gu',
            default => null,
        };
    }

    private function questionText(array $question, string $locale): string
    {
        return (string) data_get($question, 'label.'.$locale, data_get($question, 'label.en'));
    }

    private function serviceMenu(string $locale): string
    {
        return match ($locale) {
            'hi' => "नमस्ते 👋 क्या काम अटका है?\n1. पैसा वापस / Refund\n2. Product / Warranty\n3. PAN-Aadhaar / Document mismatch\n4. Address / House shift\n5. Marriage updates\n6. New baby documents\n7. Family / After-loss admin\n\nबस 1–7 में से नंबर भेजें. Language बदलने के लिए EN / HI / GU लिखें.",
            'gu' => "નમસ્તે 👋 શું કામ અટક્યું છે?\n1. પૈસા / Refund\n2. Product / Warranty\n3. PAN-Aadhaar / Document mismatch\n4. Address / House shift\n5. Marriage updates\n6. New baby documents\n7. Family / After-loss admin\n\n1–7 માંથી નંબર મોકલો. Language બદલવા EN / HI / GU લખો.",
            default => "Hi 👋 What is stuck?\n1. Money / Refund\n2. Product / Warranty\n3. PAN-Aadhaar / Document mismatch\n4. Address / House shift\n5. Marriage updates\n6. New baby documents\n7. Family / After-loss admin\n\nReply 1–7. Type EN / HI / GU anytime to change language.",
        };
    }

    private function caseStartedText(CaseRecord $case): string
    {
        return match ($case->locale) {
            'hi' => "केस शुरू हो गया ✅\nCase ID: {$case->public_id}\nमैं कुछ जरूरी सवाल पूछूँगा. Password, PIN, CVV, UPI PIN या OTP कभी न भेजें.",
            'gu' => "Case શરૂ થઈ ગયો ✅\nCase ID: {$case->public_id}\nહવે થોડા જરૂરી પ્રશ્નો પૂછું. Password, PIN, CVV, UPI PIN કે OTP ક્યારેય મોકલશો નહીં.",
            default => "Case started ✅\nCase ID: {$case->public_id}\nI’ll ask only the required questions. Never send passwords, PINs, CVV, UPI PIN or OTP.",
        };
    }

    private function documentReceivedText(string $locale): string
    {
        return match ($locale) {
            'hi' => 'Document सुरक्षित रूप से मिल गया ✅',
            'gu' => 'Document સુરક્ષિત રીતે મળી ગયું ✅',
            default => 'Document received securely ✅',
        };
    }

    private function intakeCompleteText(CaseRecord $case, string $locale): string
    {
        return match ($locale) {
            'hi' => "Intake complete ✅\nCase: {$case->public_id}\nReadiness: {$case->readiness_score}%\nInvoice / screenshot / supporting PDF हो तो यहीं भेज सकते हैं. Human team review करेगी.",
            'gu' => "Intake complete ✅\nCase: {$case->public_id}\nReadiness: {$case->readiness_score}%\nInvoice / screenshot / supporting PDF હોય તો અહીં મોકલી શકો છો. Human team review કરશે.",
            default => "Intake complete ✅\nCase: {$case->public_id}\nReadiness: {$case->readiness_score}%\nYou can send invoice, screenshot or supporting PDF here. A human team member will review the case.",
        };
    }

    private function statusText(CaseRecord $case): string
    {
        return "Case {$case->public_id}\nStatus: {$case->status_label}\nReadiness: {$case->readiness_score}%\nPayment: {$case->payment_status}";
    }

    private function humanHandoffText(string $locale, string $caseId): string
    {
        return match ($locale) {
            'hi' => "ठीक है. Case {$caseId} human review queue में डाल दिया है. ✅",
            'gu' => "બરાબર. Case {$caseId} human review queue માં મૂકી દીધો છે. ✅",
            default => "Done. Case {$caseId} is now in the human review queue. ✅",
        };
    }

    private function languageChangedText(string $locale): string
    {
        return match ($locale) {
            'hi' => 'भाषा हिंदी कर दी ✅',
            'gu' => 'ભાષા ગુજરાતી કરી ✅',
            default => 'Language changed to English ✅',
        };
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
