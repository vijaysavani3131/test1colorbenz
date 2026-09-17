<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\CaseDocument;
use App\Services\CaseWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function download(Request $request, int $documentId): StreamedResponse
    {
        $document = $this->documentForUser($request, $documentId);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404, 'Document file not found.');

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function view(Request $request, int $documentId): Response
    {
        $document = $this->documentForUser($request, $documentId);
        $disk = Storage::disk($document->storage_disk);
        abort_unless($disk->exists($document->storage_path), 404, 'Document file not found.');

        $safeName = str_replace(['"', "\r", "\n"], '', $document->original_name);
        return response($disk->get($document->storage_path), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, int $documentId, CaseWorkflowService $workflow)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected,manual_review'],
        ]);

        $document = $this->documentForUser($request, $documentId);
        $document->load('caseRecord');
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

    private function documentForUser(Request $request, int $documentId): CaseDocument
    {
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');
        $query = CaseDocument::query()->with('caseRecord');
        if (!$admin->canManageStaff()) {
            $query->whereHas('caseRecord', fn ($q) => $q->where('assigned_admin_user_id', $admin->id));
        }
        return $query->findOrFail($documentId);
    }
}
