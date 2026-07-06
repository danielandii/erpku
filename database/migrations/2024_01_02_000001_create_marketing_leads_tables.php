<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Lead Stages (Pipeline) ─────────────────────────────
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100)->comment('New | Qualified | Proposal | Negotiation | Won | Lost');
            $table->unsignedSmallInteger('sequence')->comment('Urutan tampil di Kanban pipeline');
            $table->string('color', 7)->nullable()->comment('Hex color untuk UI, misal #3B82F6');
            $table->boolean('is_won')->default(false)->comment('Stage ini mewakili lead berhasil ditutup');
            $table->boolean('is_lost')->default(false)->comment('Stage ini mewakili lead gagal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'sequence']);
        });

        // ── Leads ──────────────────────────────────────────────
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('lead_number', 30)->unique()->comment('Auto-generated: LDS-2024-0001');
            $table->string('contact_name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->foreignUuid('stage_id')->constrained('lead_stages')->restrictOnDelete();
            $table->string('source', 50)->nullable()
                  ->comment('website | referral | cold_call | social_media | event | exhibition | other');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_revenue', 15, 2)->nullable()->comment('Estimasi nilai deal');
            $table->unsignedSmallInteger('probability')->nullable()->comment('Probabilitas menang 0-100');
            $table->date('expected_close_date')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_followup_at')->nullable()->index();
            $table->unsignedSmallInteger('score')->default(0)->comment('Lead score 0-100 untuk prioritisasi');
            $table->string('status', 20)->default('active')
                  ->comment('active | on_hold | won | lost');
            $table->text('lost_reason')->nullable()->comment('Wajib diisi saat status = lost');
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->uuid('converted_to_client_id')->nullable()->comment('FK ke clients setelah konversi');
            $table->text('notes')->nullable();
            $table->json('tags')->nullable()->comment('Array custom tags: ["urgent","enterprise"]');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('stage_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('email');
        });

        // ── Lead Activities (Last Call / Follow-up Log) ────────
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('activity_type', 30)
                  ->comment('call | email | meeting | note | whatsapp | site_visit | demo');
            $table->text('summary')->comment('Ringkasan hasil interaksi / catatan');
            $table->timestamp('activity_date');
            $table->text('next_action')->nullable()->comment('Rencana tindak lanjut');
            $table->timestamp('next_action_date')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_stages');
    }
};
