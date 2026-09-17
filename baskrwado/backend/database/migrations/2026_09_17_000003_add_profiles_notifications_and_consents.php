<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('phone');
            $table->string('timezone', 60)->default('Asia/Kolkata')->after('role');
            $table->json('notification_preferences')->nullable()->after('timezone');
        });

        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('title', 180);
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('case_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->unique()->constrained('case_records')->cascadeOnDelete();
            $table->string('consent_version', 30)->default('2026-09-v1');
            $table->char('consent_text_hash', 64);
            $table->string('source', 30)->default('web');
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_consents');
        Schema::dropIfExists('admin_notifications');

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'job_title', 'timezone', 'notification_preferences']);
        });
    }
};
