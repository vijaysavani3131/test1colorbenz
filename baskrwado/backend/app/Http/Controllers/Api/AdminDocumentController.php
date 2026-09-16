<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseDocument;
use App\Services\CaseWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function download(int $documentId): StreamedResponse
    {
        $document = CaseDocument::query()->findOrFail($documentId);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404, 'Document file not found.');

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function verify(Request $request, int $documentId, CaseWorkflowService $workflow)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected,manual_review'],
        ]);

        $document = CaseDocument::query()->with('caseRecord')->findOrFail($documentId);
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
}
