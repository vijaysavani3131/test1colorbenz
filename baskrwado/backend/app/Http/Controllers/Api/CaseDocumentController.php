<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCaseDocument;
use App\Models\CaseDocument;
use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use App\Services\DocumentImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseDocumentController extends Controller
{
    public function store(
        Request $request,
        string $publicId,
        CaseWorkflowService $workflow,
        DocumentImageOptimizer $optimizer,
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        abort_unless(hash_equals($case->phone, $this->normalizePhone($validated['phone'])), 403, 'The case ID and mobile number do not match.');

        $file = $validated['file'];
        $originalName = Str::limit($file->getClientOriginalName(), 255, '');
        $originalMime = $file->getMimeType() ?: 'application/octet-stream';
        $bytes = (string) file_get_contents($file->getRealPath());
        abort_if($bytes === '', 422, 'Uploaded document is empty.');

        if (str_starts_with($originalMime, 'image/')) {
            $result = $optimizer->optimize($bytes, $originalMime);
            $bytes = $result['bytes'];
            $mime = $result['mime'];
            $extension = $result['extension'];
            $optimized = (bool) $result['optimized'];
        } else {
            $mime = $originalMime;
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $optimized = false;
        }

        $path = 'cases/'.$case->public_id.'/'.Str::uuid().'.'.$extension;
        abort_unless(Storage::disk('private')->put($path, $bytes), 500, 'Unable to store document.');

        $document = CaseDocument::create([
            'case_record_id' => $case->id,
            'category' => $validated['category'] ?? 'evidence',
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'storage_disk' => 'private',
            'storage_path' => $path,
            'sha256' => hash('sha256', $bytes),
            'source' => 'web',
            'status' => 'uploaded',
        ]);

        $workflow->recordEvent(
            $case,
            'document_uploaded',
            'Customer uploaded '.$document->original_name.($optimized ? ' (image optimized for storage).' : ''),
            'customer',
            null,
            ['document_id' => $document->id, 'optimized' => $optimized],
        );
        AnalyzeCaseDocument::dispatch($document->id);

        return response()->json([
            'message' => $optimized ? 'Document uploaded securely and image optimized.' : 'Document uploaded securely.',
            'document' => [
                'id' => $document->id,
                'name' => $document->original_name,
                'category' => $document->category,
                'status' => $document->status,
                'size_bytes' => $document->size_bytes,
                'optimized' => $optimized,
            ],
        ], 201);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
