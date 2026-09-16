<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCaseController extends Controller
{
    public function index(CaseWorkflowService $workflow): JsonResponse
    {
        $cases = CaseRecord::query()->latest()->limit(250)->get()
            ->map(fn (CaseRecord $case) => $workflow->customerPayload($case) + [
                'name' => $case->name,
                'phone' => $case->phone,
                'source' => $case->source,
                'locale' => $case->locale,
            ]);

        return response()->json(['data' => $cases]);
    }

    public function show(string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $case = CaseRecord::query()->with('answers')->where('public_id', strtoupper($publicId))->firstOrFail();

        return response()->json([
            'data' => $workflow->customerPayload($case) + [
                'name' => $case->name,
                'phone' => $case->phone,
                'email' => $case->email,
                'source' => $case->source,
                'locale' => $case->locale,
                'metadata' => $case->metadata,
                'answers' => $case->answers->mapWithKeys(fn ($answer) => [$answer->key => $answer->value]),
            ],
        ]);
    }

    public function updateStatus(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['intake','needs_info','ready_for_review','in_progress','waiting_external','resolved','closed'])],
        ]);

        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        $case->update(['status' => $validated['status']]);

        return response()->json(['data' => $workflow->customerPayload($case->fresh())]);
    }
}
