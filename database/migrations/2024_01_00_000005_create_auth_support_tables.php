<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Refresh Tokens ─────────────────────────────────────
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique()->comment('SHA-256 hash dari refresh token string');
            $table->text('device_info')->nullable()->comment('User-Agent string browser/device');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('expires_at');
        });

        // ── Login History ──────────────────────────────────────
        Schema::create('login_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 20)->comment('success | failed | blocked');
            $table->string('failure_reason', 100)->nullable()->comment('wrong_password | account_locked | account_inactive');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
        });

        // ── Audit Logs ─────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 50)->comment('Modul yang diakses');
            $table->string('action', 100)->comment('CREATE | UPDATE | DELETE | LOGIN | EXPORT | APPROVE');
            $table->string('resource_type', 100)->comment('Nama model: User | Invoice | Lead | Task');
            $table->uuid('resource_id')->nullable()->comment('ID record yang dimodifikasi');
            $table->json('old_values')->nullable()->comment('Nilai sebelum perubahan');
            $table->json('new_values')->nullable()->comment('Nilai setelah perubahan');
            $table->ipAddress('ip_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('module');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('created_at');
        });

        // ── Password Reset Tokens ──────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('refresh_tokens');
    }
};
