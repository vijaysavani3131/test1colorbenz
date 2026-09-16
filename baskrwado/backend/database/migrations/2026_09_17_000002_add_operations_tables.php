<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('password');
            $table->string('role', 30)->default('agent')->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('name', 80)->default('web');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('case_records', function (Blueprint $table): void {
            $table->foreignId('assigned_admin_user_id')->nullable()->after('email')->constrained('admin_users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('status')->index();
            $table->string('payment_status', 20)->default('unpaid')->after('priority')->index();
            $table->unsignedInteger('fee_paise')->default(0)->after('payment_status');
            $table->json('ai_report')->nullable()->after('summary');
            $table->timestamp('last_activity_at')->nullable()->after('metadata')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('case_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->string('category', 60)->default('evidence')->index();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk', 40)->default('private');
            $table->string('storage_path', 500);
            $table->char('sha256', 64)->index();
            $table->string('source', 30)->default('web')->index();
            $table->string('status', 30)->default('uploaded')->index();
            $table->longText('ocr_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->foreignId('verified_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('actor_type', 30)->default('system')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('message', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('case_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->text('note');
            $table->boolean('customer_visible')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('conversation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->nullable()->constrained('case_records')->nullOnDelete();
            $table->string('channel', 30)->default('whatsapp')->index();
            $table->string('external_id', 100)->index();
            $table->string('locale', 10)->default('en');
            $table->string('state', 60)->default('new')->index();
            $table->string('current_question_key', 100)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['channel', 'external_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_session_id')->constrained('conversation_sessions')->cascadeOnDelete();
            $table->string('direction', 10)->index();
            $table->string('external_message_id', 255)->nullable()->unique();
            $table->string('message_type', 30)->default('text')->index();
            $table->longText('text')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('received')->index();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->string('provider', 30)->default('razorpay')->index();
            $table->string('provider_order_id', 120)->nullable()->unique();
            $table->string('provider_payment_id', 120)->nullable()->unique();
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 5)->default('INR');
            $table->string('status', 30)->default('created')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30)->index();
            $table->string('event_id', 255)->index();
            $table->string('event_type', 100)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_sessions');
        Schema::dropIfExists('case_notes');
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('case_documents');

        Schema::table('case_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_admin_user_id');
            $table->dropColumn(['priority', 'payment_status', 'fee_paise', 'ai_report', 'last_activity_at', 'resolved_at', 'closed_at']);
        });

        Schema::dropIfExists('admin_api_tokens');
        Schema::dropIfExists('admin_users');
    }
};
