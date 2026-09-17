<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseNote;
use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCaseController extends Controller
{
    public function index(Request $request, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'service' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', Rule::in(['low','normal','high','urgent'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = CaseRecord::query()->with('assignee')->latest('last_activity_at')->latest();
        $query->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($validated['service'] ?? null, fn ($q, $v) => $q->where('service_slug', $v));
        $query->when($validated['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v));
        $query->when($validated['search'] ?? null, function ($q, string $search): void {
            $q->where(function ($inner) use ($search): void {
                $inner->where('public_id', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        });

        $paginator = $query->paginate(40);
        $rows = collect($paginator->items())->map(fn (CaseRecord $case) => $this->row($case, $workflow));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'metrics' => [
                'open' => CaseRecord::query()->whereNotIn('status', ['resolved','closed'])->count(),
                'ready_for_review' => CaseRecord::query()->where('status', 'ready_for_review')->count(),
                'waiting_customer' => CaseRecord::query()->where('status', 'waiting_customer')->count(),
                'urgent' => CaseRecord::query()->where('priority', 'urgent')->whereNotIn('status', ['resolved','closed'])->count(),
            ],
        ]);
    }

    public function show(string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $case = CaseRecord::query()
            ->with([
                'answers', 'documents', 'events', 'notes.adminUser', 'payments', 'assignee',
                'conversations' => fn ($q) => $q->with(['messages' => fn ($m) => $m->latest()->limit(50)]),
            ])
            ->where('public_id', strtoupper($publicId))
            ->firstOrFail();

        return response()->json(['data' => $this->detail($case, $workflow)]);
    }

    public function update(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['intake','needs_info','ready_for_review','in_progress','waiting_customer','waiting_external','resolved','closed'])],
            'priority' => ['nullable', Rule::in(['low','normal','high','urgent'])],
            'assigned_admin_user_id' => ['nullable', 'integer', 'exists:admin_users,id'],
            'fee_paise' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        $before = $case->only(['status','priority','assigned_admin_user_id','fee_paise']);
        $case->fill($validated);

        if (($validated['status'] ?? null) === 'resolved') {
            $case->resolved_at = now();
        }
        if (($validated['status'] ?? null) === 'closed') {
            $case->closed_at = now();
        }
        $case->last_activity_at = now();
        $case->save();

        $admin = $request->attributes->get('admin_user');
        $workflow->recordEvent($case, 'case_updated', 'Case workflow updated by '.$admin->name, 'admin', $admin->id, [
            'before' => $before,
            'after' => $case->only(['status','priority','assigned_admin_user_id','fee_paise']),
        ]);

        return $this->show($publicId, $workflow);
    }

    public function addNote(Request $request, string $publicId, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:10000'],
            'customer_visible' => ['nullable', 'boolean'],
        ]);

        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        $admin = $request->attributes->get('admin_user');
        $note = CaseNote::create([
            'case_record_id' => $case->id,
            'admin_user_id' => $admin->id,
            'note' => trim($validated['note']),
            'customer_visible' => (bool) ($validated['customer_visible'] ?? false),
        ]);

        $workflow->recordEvent($case, 'note_added', $note->customer_visible ? 'Customer-visible update added.' : 'Internal note added.', 'admin', $admin->id);

        return response()->json(['message' => 'Note added.'], 201);
    }

    private function row(CaseRecord $case, CaseWorkflowService $workflow): array
    {
        return $workflow->customerPayload($case) + [
            'name' => $case->name,
            'phone' => $case->phone,
            'email' => $case->email,
            'source' => $case->source,
            'locale' => $case->locale,
            'priority' => $case->priority,
            'assignee' => $case->assignee ? ['id' => $case->assignee->id, 'name' => $case->assignee->name] : null,
            'last_activity_at' => $case->last_activity_at?->toIso8601String(),
        ];
    }

    private function detail(CaseRecord $case, CaseWorkflowService $workflow): array
    {
        return $this->row($case, $workflow) + [
            'metadata' => $case->metadata,
            'ai_report' => $case->ai_report,
            'answers' => $case->answers->mapWithKeys(fn ($answer) => [$answer->key => $answer->value]),
            'documents' => $case->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'category' => $doc->category,
                'name' => $doc->original_name,
                'mime_type' => $doc->mime_type,
                'size_bytes' => $doc->size_bytes,
                'status' => $doc->status,
                'extracted_data' => $doc->extracted_data,
                'verified_at' => $doc->verified_at?->toIso8601String(),
            ]),
            'events' => $case->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'actor_type' => $event->actor_type,
                'message' => $event->message,
                'data' => $event->data,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
            'notes' => $case->notes->map(fn ($note) => [
                'id' => $note->id,
                'note' => $note->note,
                'customer_visible' => $note->customer_visible,
                'author' => $note->adminUser?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ]),
            'payments' => $case->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'amount_paise' => $payment->amount_paise,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ]),
        ];
    }
}
