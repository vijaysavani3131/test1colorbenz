<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function store(Request $request, CaseWorkflowService $workflow): JsonResponse
    {
        $slugs = collect($workflow->catalog())->pluck('slug')->all();
        $validated = $request->validate([
            'service_slug' => ['required', 'string', Rule::in($slugs)],
            'source' => ['nullable', 'string', Rule::in(['web', 'whatsapp', 'admin'])],
            'locale' => ['nullable', 'string', Rule::in(['en', 'hi', 'gu'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
        ]);

        $case = CaseRecord::create([
            ...$validated,
            'source' => $validated['source'] ?? 'web',
            'locale' => $validated['locale'] ?? 'en',
            'phone' => $this->normalizePhone($validated['phone']),
            'status' => 'intake',
            'readiness_score' => 0,
        ]);

        return response()->json([
            'message' => 'Case created.',
            'data' => $workflow->customerPayload($case),
        ], 201);
    }

    public function answers(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $case = CaseRecord::query()->where('public_id', $publicId)->firstOrFail();
        $validated = $request->validate([
            'answers' => ['required', 'array', 'max:40'],
            'answers.*' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach ($validated['answers'] as $key => $value) {
            $case->answers()->updateOrCreate(
                ['key' => (string) $key],
                ['value' => trim((string) $value)],
            );
        }

        $case = $workflow->refreshReport($case);

        return response()->json([
            'message' => 'Case details saved.',
            'data' => $workflow->customerPayload($case),
        ]);
    }

    public function show(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();

        abort_unless(
            hash_equals($case->phone, $this->normalizePhone($validated['phone'])),
            403,
            'The case ID and mobile number do not match.'
        );

        return response()->json(['data' => $workflow->customerPayload($case)]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
