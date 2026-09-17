<?php

namespace App\Services;

use App\Models\CaseEvent;
use App\Models\CaseRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CaseWorkflowService
{
    public function catalog(): array
    {
        return [
            $this->service('money_recovery', 'Paisa Wapas', 'Refund, failed service, cancelled booking or money stuck.', '₹', 'mint', 149900, 'amber', [
                $this->q('merchant', 'Company / merchant / airline ka naam?', 'कंपनी / मर्चेंट / एयरलाइन का नाम?', 'કંપની / merchant / airline નું નામ?'),
                $this->q('amount', 'Kitna amount atka hai?', 'कितना पैसा अटका है?', 'કેટલી રકમ અટકેલી છે?'),
                $this->q('issue_date', 'Issue / refund kab se pending hai?', 'रिफंड / समस्या कब से लंबित है?', 'Refund / issue ક્યારેથી pending છે?'),
                $this->q('reference', 'Order / booking / transaction ID?', 'Order / booking / transaction ID?', 'Order / booking / transaction ID?'),
                $this->q('summary', 'Short mein kya hua?', 'संक्षेप में क्या हुआ?', 'ટૂંકમાં શું થયું?'),
            ]),
            $this->service('after_sales', 'After-Sales & Warranty', 'Repair, warranty, replacement and service follow-up.', '↻', 'blue', 99900, 'amber', [
                $this->q('brand', 'Brand / seller ka naam?', 'ब्रांड / सेलर का नाम?', 'Brand / seller નું નામ?'),
                $this->q('product', 'Product aur model?', 'प्रोडक्ट और मॉडल?', 'Product અને model?'),
                $this->q('purchase_date', 'Purchase date?', 'खरीद की तारीख?', 'Purchase date?'),
                $this->q('warranty', 'Warranty active lag rahi hai?', 'वारंटी एक्टिव है?', 'Warranty active લાગે છે?'),
                $this->q('summary', 'Exact problem kya hai?', 'सटीक समस्या क्या है?', 'Exact problem શું છે?'),
            ]),
            $this->service('identity_repair', 'Identity Repair', 'PAN, Aadhaar, bank or document name / DOB mismatch.', 'ID', 'violet', 149900, 'amber', [
                $this->q('documents', 'Kaunse documents mismatch hain?', 'कौनसे डॉक्यूमेंट mismatch हैं?', 'કયા documents mismatch છે?'),
                $this->q('canonical_name', 'Correct legal name / detail kya hai?', 'सही कानूनी नाम / डिटेल क्या है?', 'Correct legal name / detail શું છે?'),
                $this->q('blocked_action', 'Mismatch se kya kaam atka hai?', 'Mismatch की वजह से क्या काम अटका है?', 'Mismatch થી શું કામ અટક્યું છે?'),
                $this->q('summary', 'Mismatch briefly describe karein.', 'Mismatch संक्षेप में बताइए.', 'Mismatch ટૂંકમાં સમજાવો.'),
            ]),
            $this->service('move_life', 'Move My Life', 'Address updates after shifting home or city.', '⌂', 'amber', 199900, 'green', [
                $this->q('from_city', 'Kahan se shift hue?', 'कहाँ से शिफ्ट हुए?', 'ક્યાંથી shift થયા?'),
                $this->q('to_city', 'Kahan shift hue?', 'कहाँ शिफ्ट हुए?', 'ક્યાં shift થયા?'),
                $this->q('move_date', 'Move date?', 'शिफ्ट की तारीख?', 'Move date?'),
                $this->q('records', 'Kin records ka address update chahiye?', 'किन रिकॉर्ड्स में address update चाहिए?', 'કયા records માં address update જોઈએ?'),
            ]),
            $this->service('marriage_sync', 'Marriage Sync', 'Name, address, nominee and record updates after marriage.', '∞', 'rose', 249900, 'green', [
                $this->q('marriage_date', 'Marriage date?', 'शादी की तारीख?', 'Marriage date?'),
                $this->q('name_change', 'Name change karna hai?', 'नाम बदलना है?', 'Name change કરવું છે?'),
                $this->q('address_change', 'Address change bhi hai?', 'Address भी बदलना है?', 'Address change પણ છે?'),
                $this->q('records', 'Kaunse records update karne hain?', 'कौनसे रिकॉर्ड update करने हैं?', 'કયા records update કરવા છે?'),
            ]),
            $this->service('new_baby', 'New Baby Setup', 'Newborn document and benefit checklist.', '+', 'cyan', 199900, 'green', [
                $this->q('birth_date', 'Baby birth date?', 'Baby की birth date?', 'Baby birth date?'),
                $this->q('city', 'Birth city?', 'Birth city?', 'Birth city?'),
                $this->q('hospital', 'Hospital / place of birth?', 'Hospital / place of birth?', 'Hospital / place of birth?'),
                $this->q('needs', 'Abhi kis document / benefit ki priority hai?', 'अभी किस document / benefit की priority है?', 'હમણાં કયા document / benefit ની priority છે?'),
            ]),
            $this->service('after_loss', 'After-Loss Admin', 'Organise bank, insurance, account and family administration.', '◌', 'slate', 999900, 'red', [
                $this->q('relation', 'Deceased se relation?', 'दिवंगत व्यक्ति से संबंध?', 'દિવંગત વ્યક્તિ સાથે relation?'),
                $this->q('date', 'Date of death?', 'Date of death?', 'Date of death?'),
                $this->q('assets', 'Known banks / insurance / PF / investments?', 'Known banks / insurance / PF / investments?', 'Known banks / insurance / PF / investments?'),
                $this->q('nominee', 'Nominee details available hain?', 'Nominee details available हैं?', 'Nominee details available છે?'),
                $this->q('summary', 'Sabse urgent admin concern kya hai?', 'सबसे urgent admin concern क्या है?', 'સૌથી urgent admin concern શું છે?'),
            ], true),
        ];
    }

    public function find(string $slug): ?array
    {
        return collect($this->catalog())->firstWhere('slug', $slug);
    }

    public function requiredKeys(string $slug): array
    {
        return collect($this->find($slug)['questions'] ?? [])->pluck('key')->all();
    }

    public function nextQuestion(CaseRecord $case): ?array
    {
        $answers = $case->answers()->pluck('value', 'key')->all();
        foreach ($this->find($case->service_slug)['questions'] ?? [] as $question) {
            if (!filled(Arr::get($answers, $question['key']))) {
                return $question;
            }
        }
        return null;
    }

    public function refreshReport(CaseRecord $case): CaseRecord
    {
        $definition = $this->find($case->service_slug);
        if (!$definition) {
            return $case;
        }

        $answers = $case->answers()->pluck('value', 'key')->all();
        $required = collect($definition['questions'])->pluck('key')->all();
        $complete = collect($required)->filter(fn (string $key) => filled(Arr::get($answers, $key)))->count();
        $score = count($required) ? (int) round(($complete / count($required)) * 100) : 0;
        $missing = collect($required)->reject(fn (string $key) => filled(Arr::get($answers, $key)))->values()->all();

        $case->readiness_score = $score;
        if (!in_array($case->status, ['in_progress', 'waiting_customer', 'waiting_external', 'resolved', 'closed'], true)) {
            $case->status = $score >= 80 ? 'ready_for_review' : 'needs_info';
        }
        $case->summary = $this->summary($case, $answers);
        $case->fee_paise = $case->fee_paise ?: (int) $definition['starting_fee_paise'];
        $case->metadata = array_merge($case->metadata ?? [], [
            'missing_fields' => $missing,
            'human_review_required' => (bool) ($definition['human_review_first'] ?? false),
            'risk_tier' => $definition['risk_tier'],
            'report_generated_at' => now()->toIso8601String(),
        ]);
        $case->last_activity_at = now();
        $case->save();

        return $case->fresh(['answers', 'documents']);
    }

    public function recordEvent(CaseRecord $case, string $type, string $message, string $actorType = 'system', ?int $actorId = null, array $data = []): void
    {
        CaseEvent::create([
            'case_record_id' => $case->id,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'message' => $message,
            'data' => $data ?: null,
        ]);
        $case->touchActivity();
    }

    public function customerPayload(CaseRecord $case): array
    {
        $case->loadMissing(['answers', 'documents']);
        $service = $this->find($case->service_slug);

        return [
            'public_id' => $case->public_id,
            'service_slug' => $case->service_slug,
            'service' => $service ? Arr::except($service, ['questions']) : null,
            'status' => $case->status,
            'status_label' => $case->status_label,
            'readiness_score' => $case->readiness_score,
            'summary' => $case->summary,
            'payment_status' => $case->payment_status,
            'fee_paise' => $case->fee_paise,
            'missing_fields' => $case->metadata['missing_fields'] ?? [],
            'documents_count' => $case->documents->count(),
            'customer_report' => Arr::only($case->ai_report ?? [], ['summary', 'recommended_next_step', 'questions', 'warnings']),
            'updated_at' => optional($case->updated_at)->toIso8601String(),
            'updated_at_human' => optional($case->updated_at)->diffForHumans(),
            'created_at_human' => optional($case->created_at)->diffForHumans(),
        ];
    }

    private function service(string $slug, string $title, string $short, string $icon, string $tone, int $startingFeePaise, string $riskTier, array $questions, bool $humanReviewFirst = false): array
    {
        return compact('slug', 'title', 'short', 'icon', 'tone', 'questions') + [
            'starting_fee_paise' => $startingFeePaise,
            'risk_tier' => $riskTier,
            'human_review_first' => $humanReviewFirst,
        ];
    }

    private function q(string $key, string $en, string $hi, string $gu): array
    {
        return ['key' => $key, 'label' => ['en' => $en, 'hi' => $hi, 'gu' => $gu]];
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
