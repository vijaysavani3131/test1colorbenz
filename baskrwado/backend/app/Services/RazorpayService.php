<?php

namespace App\Services;

use App\Models\CaseRecord;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    public function configured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function createOrder(CaseRecord $case): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return Http::withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => max(100, $case->fee_paise),
                'currency' => 'INR',
                'receipt' => $case->public_id,
                'notes' => ['case_id' => $case->public_id, 'service' => $case->service_slug],
            ])
            ->throw()
            ->json();
    }

    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('services.razorpay.key_secret'));
        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        if ($secret === '') {
            return !app()->isProduction();
        }
        return $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
