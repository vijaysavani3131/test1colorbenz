<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 255)->unique();
            $table->string('wa_id', 100)->nullable()->index();
            $table->string('message_id', 255)->nullable()->index();
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_events');
    }
};
