<?php

namespace App\Jobs;

use App\Models\CaseDocument;
use App\Services\CaseWorkflowService;
use App\Services\OpenAiCaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeCaseDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(public int $documentId) {}

    public function handle(OpenAiCaseService $ai, CaseWorkflowService $workflow): void
    {
        $document = CaseDocument::query()->with('caseRecord')->find($this->documentId);
        if (!$document || !$document->caseRecord) {
            return;
        }

        $supported = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!in_array(strtolower((string) $document->mime_type), $supported, true)) {
            $document->forceFill(['status' => 'manual_review'])->save();
            $workflow->recordEvent(
                $document->caseRecord,
                'document_manual_review',
                'Unsupported document type queued for manual review: '.$document->original_name,
                'system',
                null,
                ['document_id' => $document->id, 'mime_type' => $document->mime_type],
            );
            return;
        }

        $result = $ai->analyzeDocument($document);
        if (!$result) {
            $document->forceFill(['status' => 'manual_review'])->save();
            return;
        }

        $document->forceFill([
            'status' => 'analyzed',
            'ocr_text' => $result['summary'] ?? null,
            'extracted_data' => $result,
        ])->save();

        $workflow->recordEvent(
            $document->caseRecord,
            'document_analyzed',
            'Document analyzed: '.$document->original_name,
            'system',
            null,
            ['document_id' => $document->id],
        );

        GenerateCaseAiReport::dispatch($document->case_record_id);
    }
}
