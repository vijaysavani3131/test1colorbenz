<?php

namespace App\Jobs;

use App\Models\CaseRecord;
use App\Services\CaseWorkflowService;
use App\Services\OpenAiCaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCaseAiReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 70;

    public function __construct(public int $caseId) {}

    public function handle(OpenAiCaseService $ai, CaseWorkflowService $workflow): void
    {
        $case = CaseRecord::query()->find($this->caseId);
        if (!$case) {
            return;
        }

        $case = $workflow->refreshReport($case);
        $report = $ai->analyzeCase($case);
        if (!$report) {
            return;
        }

        $case->forceFill(['ai_report' => $report, 'last_activity_at' => now()])->save();
        $workflow->recordEvent($case, 'ai_report_generated', 'AI intake report refreshed.');
    }
}
