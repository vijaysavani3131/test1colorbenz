<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCaseDocument;
use App\Models\CaseDocument;
use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseDocumentController extends Controller
{
    public function store(Request $request, string $publicId, CaseWorkflowService $workflow, ImageCompressionService $compressor): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        abort_unless(hash_equals($case->phone, $this->normalizePhone($validated['phone'])), 403, 'The case ID and mobile number do not match.');

        $file = $validated['file'];
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $bytes = file_get_contents($file->getRealPath());
        abort_unless(is_string($bytes), 500, 'Unable to read uploaded document.');

        $processed = str_starts_with($mime, 'image/') ? $compressor->compress($bytes, $mime) : [
            'bytes' => $bytes,
            'mime' => $mime,
            'size_bytes' => strlen($bytes),
            'compressed' => false,
        ];

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $path = 'cases/'.$case->public_id.'/'.$filename;
        abort_unless(Storage::disk('private')->put($path, $processed['bytes']), 500, 'Unable to store document.');

        $document = CaseDocument::create([
            'case_record_id' => $case->id,
            'category' => $validated['category'] ?? 'evidence',
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $processed['mime'],
            'size_bytes' => $processed['size_bytes'],
            'storage_disk' => 'private',
            'storage_path' => $path,
            'sha256' => hash('sha256', $processed['bytes']),
            'source' => 'web',
            'status' => 'uploaded',
        ]);

        $workflow->recordEvent(
            $case,
            'document_uploaded',
            'Customer uploaded '.$document->original_name.($processed['compressed'] ? ' (image optimised for storage)' : ''),
            'customer',
            null,
            ['document_id' => $document->id, 'compressed' => $processed['compressed']]
        );
        AnalyzeCaseDocument::dispatch($document->id);

        return response()->json([
            'message' => 'Document uploaded securely.',
            'document' => [
                'id' => $document->id,
                'name' => $document->original_name,
                'category' => $document->category,
                'status' => $document->status,
                'size_bytes' => $document->size_bytes,
                'compressed' => $processed['compressed'],
            ],
        ], 201);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
