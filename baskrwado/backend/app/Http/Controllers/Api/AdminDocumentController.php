<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\CaseDocument;
use App\Services\CaseWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function download(Request $request, int $documentId): StreamedResponse
    {
        $document = CaseDocument::query()->with('caseRecord')->findOrFail($documentId);
        $this->authorizeDocument($request, $document);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404, 'Document file not found.');

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function view(Request $request, int $documentId): StreamedResponse
    {
        $document = CaseDocument::query()->with('caseRecord')->findOrFail($documentId);
        $this->authorizeDocument($request, $document);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404, 'Document file not found.');

        return Storage::disk($document->storage_disk)->response(
            $document->storage_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function verify(Request $request, int $documentId, CaseWorkflowService $workflow)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected,manual_review'],
        ]);

        $document = CaseDocument::query()->with('caseRecord')->findOrFail($documentId);
        $this->authorizeDocument($request, $document);
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');
        $document->forceFill([
            'status' => $validated['status'],
            'verified_by_admin_user_id' => $validated['status'] === 'verified' ? $admin->id : null,
            'verified_at' => $validated['status'] === 'verified' ? now() : null,
        ])->save();

        if ($document->caseRecord) {
            $workflow->recordEvent($document->caseRecord, 'document_'.$validated['status'], $document->original_name.' marked '.$validated['status'].'.', 'admin', $admin->id, ['document_id' => $document->id]);
        }

        return response()->json(['message' => 'Document status updated.']);
    }

    private function authorizeDocument(Request $request, CaseDocument $document): void
    {
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');
        $case = $document->caseRecord;
        abort_unless($case, 404, 'Case not found.');
        if (!$admin->canManageStaff()) {
            abort_unless((int) $case->assigned_admin_user_id === (int) $admin->id, 403, 'This document belongs to a case not assigned to you.');
        }
    }
}
