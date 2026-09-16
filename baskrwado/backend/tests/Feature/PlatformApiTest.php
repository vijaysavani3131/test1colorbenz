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

    public function test_case_intake_is_phone_bound_and_generates_readiness(): void
    {
        $created = $this->postJson('/api/cases', [
            'service_slug' => 'money_recovery',
            'source' => 'web',
            'locale' => 'en',
            'name' => 'Rahul Patel',
            'phone' => '+91 98765 43210',
            'email' => 'rahul@example.com',
        ])->assertCreated();

        $publicId = $created->json('data.public_id');
        $this->assertNotEmpty($publicId);

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
        ])->assertCreated();

        $publicId = $created->json('data.public_id');
        $file = UploadedFile::fake()->image('invoice.jpg', 600, 800)->size(250);

        $this->post("/api/cases/{$publicId}/documents", [
            'phone' => '1111111111',
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertForbidden();

        $file = UploadedFile::fake()->image('invoice.jpg', 600, 800)->size(250);
        $this->post("/api/cases/{$publicId}/documents", [
            'phone' => '9876500000',
            'category' => 'invoice',
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('document.category', 'invoice');

        $this->assertDatabaseCount('case_documents', 1);
    }

    public function test_admin_login_uses_bearer_token_and_protects_case_queue(): void
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
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'owner');

        $this->withToken($token)
            ->getJson('/api/admin/cases')
            ->assertOk();
    }
}
