<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_records', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 20)->unique();
            $table->string('service_slug', 60)->index();
            $table->string('source', 20)->default('web')->index();
            $table->string('locale', 10)->default('en');
            $table->string('name', 120);
            $table->string('phone', 20)->index();
            $table->string('email', 180)->nullable();
            $table->string('status', 40)->default('intake')->index();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('case_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['case_record_id', 'key']);
        });

        Schema::create('whatsapp_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 255)->unique();
            $table->string('wa_id', 40)->nullable()->index();
            $table->string('message_id', 255)->nullable()->index();
            $table->string('event_type', 80)->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_events');
        Schema::dropIfExists('case_answers');
        Schema::dropIfExists('case_records');
    }
};
