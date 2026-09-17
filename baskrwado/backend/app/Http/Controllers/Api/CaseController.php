<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCaseAiReport;
use App\Models\CaseRecord;
use App\Services\AdminNotificationService;
use App\Services\CaseWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    private const CONSENT_VERSION = '2026-09-v2';

    private const CONSENT_TEXT = 'I confirm that the information and documents I provide to BasKarwaDo are genuine, accurate to the best of my knowledge, lawfully obtained, and that I am authorised to share and use them for this case. I understand BasKarwaDo provides assistance and process support and does not certify document authenticity. I remain responsible for the truthfulness, legality and originality of my submissions. I will not use BasKarwaDo to mislead, impersonate, forge, fabricate evidence or attempt fraud against any company, authority, institution or person. BasKarwaDo may pause, reject or close a case if documents or instructions appear suspicious, unlawful or inconsistent, and may preserve records or cooperate with lawful requests where required. I will not upload passwords, PINs, CVV, UPI PIN, netbanking credentials or OTPs.';

    public function store(
        Request $request,
        CaseWorkflowService $workflow,
        AdminNotificationService $notifications,
    ): JsonResponse {
        $slugs = collect($workflow->catalog())->pluck('slug')->all();
        $validated = $request->validate([
            'service_slug' => ['required', 'string', Rule::in($slugs)],
            'locale' => ['nullable', 'string', Rule::in(['en', 'hi', 'gu'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'consent_accepted' => ['required', 'accepted'],
        ]);

        unset($validated['consent_accepted']);

        $case = CaseRecord::create([
            ...$validated,
            'source' => 'web',
            'locale' => $validated['locale'] ?? 'en',
            'phone' => $this->normalizePhone($validated['phone']),
            'status' => 'intake',
            'readiness_score' => 0,
        ]);

        $case->consent()->create([
            'consent_version' => self::CONSENT_VERSION,
            'consent_text_hash' => hash('sha256', self::CONSENT_TEXT),
            'source' => 'web',
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'accepted_at' => now(),
        ]);

        $case = $workflow->refreshReport($case);
        $workflow->recordEvent($case, 'case_created', 'Customer created the case online and accepted the customer declaration.', 'customer', null, [
            'consent_version' => self::CONSENT_VERSION,
        ]);

        $notifications->notifyOwnersAndAdmins(
            'new_case',
            'New case '.$case->public_id,
            $case->name.' started a '.($case->service_slug).' case.',
            ['public_id' => $case->public_id, 'service_slug' => $case->service_slug],
        );

        return response()->json([
            'message' => 'Case created.',
            'data' => $workflow->customerPayload($case),
        ], 201);
    }

    public function answers(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'answers' => ['required', 'array', 'max:40'],
            'answers.*' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_unless(hash_equals($case->phone, $this->normalizePhone($validated['phone'])), 403, 'The case ID and mobile number do not match.');

        foreach ($validated['answers'] as $key => $value) {
            $case->answers()->updateOrCreate(
                ['key' => (string) $key],
                ['value' => trim((string) $value)],
            );
        }

        $case = $workflow->refreshReport($case);
        $workflow->recordEvent($case, 'intake_updated', 'Customer updated case intake.', 'customer');
        GenerateCaseAiReport::dispatch($case->id);

        return response()->json([
            'message' => 'Case details saved.',
            'data' => $workflow->customerPayload($case),
        ]);
    }

    public function show(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $case = CaseRecord::query()
            ->with(['notes' => fn ($q) => $q->where('customer_visible', true)->latest()])
            ->where('public_id', strtoupper($publicId))
            ->firstOrFail();

        abort_unless(
            hash_equals($case->phone, $this->normalizePhone($validated['phone'])),
            403,
            'The case ID and mobile number do not match.'
        );

        return response()->json([
            'data' => $workflow->customerPayload($case) + [
                'updates' => $case->notes->map(fn ($note) => [
                    'message' => $note->note,
                    'created_at' => $note->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
