<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── In-App Notifications ───────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Penerima notifikasi');
            $table->string('type', 100)->comment('Nama class notifikasi: LeaveRequestSubmitted | InvoiceOverdue');
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->json('data')->nullable()->comment('Data tambahan: URL, resource_type, resource_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index(['user_id', 'read_at']);
        });

        // ── Document Number Sequences ──────────────────────────
        // Untuk auto-generate nomor dokumen per tenant per prefix per tahun
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('prefix', 20)->comment('INV | QUO | LDS | CLI | PRJ | PAY | JNL | BILL');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0)->comment('Nomor urut terakhir yang digunakan');
            $table->timestamps();

            $table->unique(['tenant_id', 'prefix', 'year']);
            $table->index('tenant_id');
        });

        // ── Settings (konfigurasi fleksibel per tenant) ────────
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 100)->comment('Contoh: payroll.pph21_method | invoice.prefix | hrd.late_deduction_formula');
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string')
                  ->comment('string | integer | decimal | boolean | json');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index('tenant_id');
        });

        // ── Job Queue (Laravel Queue tabel — opsional jika pakai database driver) ──
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('job_batches', function (Blueprint $table) {
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

        // ── Cache ──────────────────────────────────────────────
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // ── Sessions ───────────────────────────────────────────
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('notifications');
    }
};
