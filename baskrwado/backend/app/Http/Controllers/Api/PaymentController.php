<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\CaseWorkflowService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function createOrder(Request $request, string $publicId, RazorpayService $razorpay): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $case = CaseRecord::query()->where('public_id', strtoupper($publicId))->firstOrFail();
        abort_unless(hash_equals($case->phone, $this->normalizePhone($validated['phone'])), 403, 'The case ID and mobile number do not match.');
        abort_if($case->payment_status === 'paid', 422, 'This case is already paid.');

        $order = $razorpay->createOrder($case);
        Payment::query()->updateOrCreate(
            ['provider_order_id' => $order['id']],
            [
                'case_record_id' => $case->id,
                'provider' => 'razorpay',
                'amount_paise' => (int) ($order['amount'] ?? $case->fee_paise),
                'currency' => (string) ($order['currency'] ?? 'INR'),
                'status' => 'created',
                'metadata' => $order,
            ],
        );

        return response()->json([
            'key_id' => config('services.razorpay.key_id'),
            'order' => [
                'id' => $order['id'],
                'amount' => (int) $order['amount'],
                'currency' => $order['currency'] ?? 'INR',
            ],
            'customer' => ['name' => $case->name, 'email' => $case->email, 'phone' => $case->phone],
        ]);
    }

    public function verify(Request $request, RazorpayService $razorpay, CaseWorkflowService $workflow): JsonResponse
    {
        $validated = $request->validate([
            'public_id' => ['required', 'string', 'max:30'],
            'razorpay_order_id' => ['required', 'string', 'max:120'],
            'razorpay_payment_id' => ['required', 'string', 'max:120'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $payment = Payment::query()->with('caseRecord')->where('provider_order_id', $validated['razorpay_order_id'])->firstOrFail();
        abort_unless($payment->caseRecord && $payment->caseRecord->public_id === strtoupper($validated['public_id']), 403, 'Payment does not belong to this case.');
        abort_unless($razorpay->verifyCheckoutSignature($validated['razorpay_order_id'], $validated['razorpay_payment_id'], $validated['razorpay_signature']), 422, 'Payment signature verification failed.');

        $this->markPaid($payment, $validated['razorpay_payment_id'], $workflow);
        return response()->json(['message' => 'Payment verified.', 'payment_status' => 'paid']);
    }

    public function webhook(Request $request, RazorpayService $razorpay, CaseWorkflowService $workflow): JsonResponse
    {
        $raw = $request->getContent();
        abort_unless($razorpay->verifyWebhookSignature($raw, (string) $request->header('X-Razorpay-Signature')), 401, 'Invalid webhook signature.');

        $payload = json_decode($raw, true) ?: [];
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $raw));
        $event = WebhookEvent::query()->firstOrCreate(
            ['provider' => 'razorpay', 'event_id' => $eventId],
            ['event_type' => (string) ($payload['event'] ?? 'unknown'), 'payload' => $payload],
        );

        if (!$event->wasRecentlyCreated || $event->processed_at) {
            return response()->json(['received' => true]);
        }

        $orderId = (string) (data_get($payload, 'payload.order.entity.id') ?: data_get($payload, 'payload.payment.entity.order_id') ?: '');
        $paymentId = (string) (data_get($payload, 'payload.payment.entity.id') ?: '');
        if ($orderId !== '' && in_array($payload['event'] ?? '', ['order.paid', 'payment.captured'], true)) {
            $payment = Payment::query()->with('caseRecord')->where('provider_order_id', $orderId)->first();
            if ($payment) {
                $this->markPaid($payment, $paymentId, $workflow);
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
        return response()->json(['received' => true]);
    }

    private function markPaid(Payment $payment, string $paymentId, CaseWorkflowService $workflow): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $payment->forceFill([
            'provider_payment_id' => $paymentId ?: $payment->provider_payment_id,
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $case = $payment->caseRecord;
        if ($case) {
            $case->forceFill(['payment_status' => 'paid', 'last_activity_at' => now()])->save();
            $workflow->recordEvent($case, 'payment_received', 'Payment received and verified.', 'system', null, ['payment_id' => $payment->id]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
