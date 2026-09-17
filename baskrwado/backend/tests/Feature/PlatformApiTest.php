<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_intake_requires_customer_declaration_and_generates_readiness(): void
    {
        $this->postJson('/api/cases', [
            'service_slug' => 'money_recovery',
            'locale' => 'en',
            'name' => 'Rahul Patel',
            'phone' => '+91 98765 43210',
        ])->assertUnprocessable()->assertJsonValidationErrors('consent_accepted');

        $created = $this->postJson('/api/cases', [
            'service_slug' => 'money_recovery',
            'locale' => 'en',
            'name' => 'Rahul Patel',
            'phone' => '+91 98765 43210',
            'email' => 'rahul@example.com',
            'consent_accepted' => true,
        ])->assertCreated();

        $publicId = $created->json('data.public_id');
        $this->assertNotEmpty($publicId);
        $this->assertDatabaseCount('case_consents', 1);
        $this->assertDatabaseHas('case_consents', ['source' => 'web', 'consent_version' => '2026-09-v2']);

        $answers = [
            'merchant' => 'Example Store',
            'amount' => '4999',
            'issue_date' => '2026-09-01',
            'reference' => 'ORDER-123',
            'summary' => 'Refund pending after return.',
        ];

        $this->postJson("/api/cases/{$publicId}/answers", [
            'phone' => '9999999999',
            'answers' => $answers,
        ])->assertForbidden();

        $this->postJson("/api/cases/{$publicId}/answers", [
            'phone' => '9876543210',
            'answers' => $answers,
        ])->assertOk()->assertJsonPath('data.readiness_score', 100);

        $this->getJson("/api/cases/{$publicId}?phone=9876543210")
            ->assertOk()
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.status', 'ready_for_review');
    }

    public function test_private_document_upload_requires_matching_case_phone(): void
    {
        Storage::fake('private');

        $created = $this->postJson('/api/cases', [
            'service_slug' => 'after_sales',
            'locale' => 'en',
            'name' => 'Asha Shah',
            'phone' => '9876500000',
            'consent_accepted' => true,
        ])->assertCreated();

        $publicId = $created->json('data.public_id');
        $file = UploadedFile::fake()->create('invoice.pdf', 250, 'application/pdf');

        $this->post("/api/cases/{$publicId}/documents", [
            'phone' => '1111111111',
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertForbidden();

        $file = UploadedFile::fake()->create('invoice.pdf', 250, 'application/pdf');
        $this->post("/api/cases/{$publicId}/documents", [
            'phone' => '9876500000',
            'category' => 'invoice',
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('document.category', 'invoice');

        $this->assertDatabaseCount('case_documents', 1);
    }

    public function test_owner_can_manage_staff_profile_and_notification_preferences(): void
    {
        AdminUser::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'a-strong-password',
            'role' => 'owner',
            'active' => true,
        ]);

        $this->getJson('/api/admin/cases')->assertUnauthorized();

        $login = $this->postJson('/api/admin/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'a-strong-password',
        ])->assertOk();
        $token = $login->json('token');

        $this->withToken($token)->postJson('/api/admin/staff', [
            'name' => 'Case Agent',
            'email' => 'agent@example.com',
            'phone' => '9876543210',
            'job_title' => 'Resolution Agent',
            'password' => 'temporary-pass-123',
            'role' => 'agent',
        ])->assertCreated();

        $this->withToken($token)->patchJson('/api/admin/profile', [
            'name' => 'Owner Updated',
            'phone' => '9000000000',
            'job_title' => 'Operations Lead',
            'timezone' => 'Asia/Kolkata',
        ])->assertOk()->assertJsonPath('user.job_title', 'Operations Lead');

        $this->withToken($token)->patchJson('/api/admin/profile/notification-preferences', [
            'new_case' => true,
            'assigned_case' => true,
            'urgent_case' => true,
            'payment_received' => false,
            'customer_update' => true,
        ])->assertOk()->assertJsonPath('preferences.payment_received', false);

        $this->withToken($token)->getJson('/api/admin/notifications')->assertOk();
        $this->withToken($token)->getJson('/api/admin/staff')->assertOk();
    }
}
