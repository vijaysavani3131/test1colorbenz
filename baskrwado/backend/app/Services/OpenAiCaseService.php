<?php

namespace App\Services;

use App\Models\CaseDocument;
use App\Models\CaseRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OpenAiCaseService
{
    public function available(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    public function analyzeCase(CaseRecord $case): ?array
    {
        if (!$this->available()) {
            return null;
        }

        $case->loadMissing(['answers', 'documents']);
        $payload = [
            'service_slug' => $case->service_slug,
            'locale' => $case->locale,
            'summary' => $case->summary,
            'answers' => $case->answers->pluck('value', 'key')->all(),
            'documents' => $case->documents->map(fn (CaseDocument $doc) => [
                'category' => $doc->category,
                'name' => $doc->original_name,
                'status' => $doc->status,
                'extracted_data' => $doc->extracted_data,
            ])->values()->all(),
        ];

        return $this->requestStructured(
            'You are the intake analyst for BasKarwaDo, an India-first assistance service. Summarize facts only. Do not invent legal rights, deadlines, eligibility, regulator rules or guaranteed outcomes. Flag legal/financial/high-risk matters for human review. Give the next operational step and only the missing questions that materially improve the case. Never ask for passwords, PINs, card CVV, UPI PIN, netbanking credentials or OTPs.',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $this->caseReportSchema(),
            'baskrwado_case_report',
        );
    }

    public function classifyInbound(string $text, ?string $localeHint = null): array
    {
        $fallback = $this->fallbackClassification($text, $localeHint);
        if (!$this->available()) {
            return $fallback;
        }

        $result = $this->requestStructured(
            'Classify a customer WhatsApp message for BasKarwaDo. Supported services: money_recovery, after_sales, identity_repair, move_life, marriage_sync, new_baby, after_loss, unknown. Detect English, Hindi, Gujarati, Hinglish or Gujlish and normalize locale to en, hi or gu. Do not answer the underlying legal/financial question; only classify intent.',
            $text,
            [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'service_slug' => ['type' => 'string', 'enum' => ['money_recovery','after_sales','identity_repair','move_life','marriage_sync','new_baby','after_loss','unknown']],
                    'locale' => ['type' => 'string', 'enum' => ['en','hi','gu']],
                    'intent' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
                'required' => ['service_slug','locale','intent','confidence'],
            ],
            'baskrwado_message_classification',
        );

        return is_array($result) ? array_merge($fallback, $result) : $fallback;
    }

    public function analyzeDocument(CaseDocument $document): ?array
    {
        if (!$this->available() || $document->size_bytes > 8 * 1024 * 1024) {
            return null;
        }

        try {
            $bytes = Storage::disk($document->storage_disk)->get($document->storage_path);
        } catch (Throwable) {
            return null;
        }

        $content = [[
            'type' => 'input_text',
            'text' => 'Extract useful case facts from this customer-provided document. Do not infer facts that are not visible. Mask or avoid repeating full account numbers, government ID numbers, card numbers or other secrets. Return concise structured data.',
        ]];

        if (str_starts_with($document->mime_type, 'image/')) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$document->mime_type.';base64,'.base64_encode($bytes),
                'detail' => 'auto',
            ];
        } else {
            $content[] = [
                'type' => 'input_file',
                'filename' => $document->original_name,
                'file_data' => base64_encode($bytes),
            ];
        }

        return $this->requestStructured(
            'You extract factual metadata from documents for a human-reviewed case-management workflow. Never provide legal advice or decide claim validity.',
            [[
                'role' => 'user',
                'content' => $content,
            ]],
            $this->documentSchema(),
            'baskrwado_document_extract',
            true,
        );
    }

    private function requestStructured(string $instructions, mixed $input, array $schema, string $name, bool $inputIsStructured = false): ?array
    {
        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()
                ->timeout(50)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'store' => false,
                    'instructions' => $instructions,
                    'input' => $inputIsStructured ? $input : (string) $input,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $name,
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                report(new \RuntimeException('OpenAI request failed: '.$response->status().' '.Str::limit($response->body(), 500)));
                return null;
            }

            $json = $response->json();
            $text = collect(Arr::get($json, 'output', []))
                ->flatMap(fn ($item) => Arr::get($item, 'content', []))
                ->firstWhere('type', 'output_text')['text'] ?? null;

            if (!is_string($text)) {
                return null;
            }

            $decoded = json_decode($text, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    private function caseReportSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'customer_need' => ['type' => 'string'],
                'risk_level' => ['type' => 'string', 'enum' => ['green','amber','red']],
                'recommended_next_step' => ['type' => 'string'],
                'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                'extracted_fields' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        ],
                        'required' => ['key','value','confidence'],
                    ],
                ],
            ],
            'required' => ['summary','customer_need','risk_level','recommended_next_step','questions','warnings','extracted_fields'],
        ];
    }

    private function documentSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'document_type' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'fields' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        ],
                        'required' => ['key','value','confidence'],
                    ],
                ],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['document_type','summary','fields','warnings'],
        ];
    }

    private function fallbackClassification(string $text, ?string $localeHint): array
    {
        $lower = Str::lower($text);
        $service = match (true) {
            Str::contains($lower, ['refund','paisa','money','payment','paise','return amount','રિફંડ','પૈસા']) => 'money_recovery',
            Str::contains($lower, ['warranty','repair','service center','replacement','product','વારંટી','રિપેર']) => 'after_sales',
            Str::contains($lower, ['aadhaar','aadhar','pan','name mismatch','kyc','dob','આધાર','પાન']) => 'identity_repair',
            Str::contains($lower, ['shift','address change','moved','move','શિફ્ટ','એડ્રેસ']) => 'move_life',
            Str::contains($lower, ['marriage','shaadi','shadi','wedding','લગ્ન']) => 'marriage_sync',
            Str::contains($lower, ['baby','birth certificate','newborn','બેબી','જન્મ']) => 'new_baby',
            Str::contains($lower, ['death','deceased','died','passed away','expire ho','મૃત્યુ','અવસાન']) => 'after_loss',
            default => 'unknown',
        };

        $locale = in_array($localeHint, ['en','hi','gu'], true) ? $localeHint : 'en';
        if (preg_match('/[\x{0A80}-\x{0AFF}]/u', $text)) {
            $locale = 'gu';
        } elseif (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            $locale = 'hi';
        }

        return ['service_slug' => $service, 'locale' => $locale, 'intent' => Str::limit($text, 120), 'confidence' => $service === 'unknown' ? 0.2 : 0.65];
    }
}
