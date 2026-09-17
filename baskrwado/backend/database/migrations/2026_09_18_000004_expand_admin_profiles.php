<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->string('department', 100)->nullable()->after('job_title');
            $table->string('language', 10)->default('en')->after('timezone');
            $table->text('bio')->nullable()->after('language');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->string('avatar_mime', 80)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropColumn(['department', 'language', 'bio', 'avatar_path', 'avatar_mime']);
        });
    }
};
