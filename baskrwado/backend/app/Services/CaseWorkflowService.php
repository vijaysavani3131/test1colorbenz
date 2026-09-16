<?php

namespace App\Services;

use App\Models\CaseRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CaseWorkflowService
{
    public function catalog(): array
    {
        return [
            $this->service('money_recovery', 'Paisa Wapas', 'Refund, failed service, cancelled booking or money stuck.', '₹', 'mint', ['merchant','amount','issue_date','reference','summary']),
            $this->service('after_sales', 'After-Sales & Warranty', 'Repair, warranty, replacement and service follow-up.', '↻', 'blue', ['brand','product','purchase_date','warranty','summary']),
            $this->service('identity_repair', 'Identity Repair', 'PAN, Aadhaar, bank or document name / DOB mismatch.', 'ID', 'violet', ['documents','canonical_name','blocked_action','summary']),
            $this->service('move_life', 'Move My Life', 'Address updates after shifting home or city.', '⌂', 'amber', ['from_city','to_city','move_date','records']),
            $this->service('marriage_sync', 'Marriage Sync', 'Name, address, nominee and record updates after marriage.', '∞', 'rose', ['marriage_date','name_change','address_change','records']),
            $this->service('new_baby', 'New Baby Setup', 'Newborn document and benefit checklist.', '+', 'cyan', ['birth_date','city','hospital','needs']),
            $this->service('after_loss', 'After-Loss Admin', 'Organise bank, insurance, account and family administration.', '◌', 'slate', ['relation','date','assets','nominee','summary'], true),
        ];
    }

    public function find(string $slug): ?array
    {
        return collect($this->catalog())->firstWhere('slug', $slug);
    }

    public function refreshReport(CaseRecord $case): CaseRecord
    {
        $definition = $this->find($case->service_slug);
        if (!$definition) {
            return $case;
        }

        $answers = $case->answers()->pluck('value', 'key')->all();
        $required = $definition['required_fields'];
        $complete = collect($required)->filter(fn (string $key) => filled(Arr::get($answers, $key)))->count();
        $score = count($required) ? (int) round(($complete / count($required)) * 100) : 0;
        $missing = collect($required)->reject(fn (string $key) => filled(Arr::get($answers, $key)))->values()->all();

        $case->readiness_score = $score;
        $case->status = $score >= 80 ? 'ready_for_review' : 'needs_info';
        $case->summary = $this->summary($case, $answers);
        $case->metadata = array_merge($case->metadata ?? [], [
            'missing_fields' => $missing,
            'human_review_required' => (bool) ($definition['human_review_first'] ?? false),
            'report_generated_at' => now()->toIso8601String(),
        ]);
        $case->save();

        return $case->fresh('answers');
    }

    public function customerPayload(CaseRecord $case): array
    {
        $case->loadMissing('answers');
        $service = $this->find($case->service_slug);

        return [
            'public_id' => $case->public_id,
            'service_slug' => $case->service_slug,
            'service' => $service ? Arr::except($service, ['required_fields']) : null,
            'status' => $case->status,
            'status_label' => $case->status_label,
            'readiness_score' => $case->readiness_score,
            'summary' => $case->summary,
            'updated_at' => optional($case->updated_at)->toIso8601String(),
            'updated_at_human' => optional($case->updated_at)->diffForHumans(),
            'created_at_human' => optional($case->created_at)->diffForHumans(),
        ];
    }

    private function service(string $slug, string $title, string $short, string $icon, string $tone, array $required, bool $humanReviewFirst = false): array
    {
        return compact('slug', 'title', 'short', 'icon', 'tone') + [
            'required_fields' => $required,
            'human_review_first' => $humanReviewFirst,
        ];
    }

    private function summary(CaseRecord $case, array $answers): string
    {
        $service = $this->find($case->service_slug);
        $label = $service['title'] ?? Str::headline($case->service_slug);
        $parts = collect($answers)
            ->filter(fn ($value) => filled($value))
            ->take(4)
            ->map(fn ($value, $key) => Str::headline($key).': '.Str::limit((string) $value, 90))
            ->values()
            ->all();

        return $label.' case. '.implode(' · ', $parts);
    }
}
