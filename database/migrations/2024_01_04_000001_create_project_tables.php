<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Projects ───────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('project_number', 30)->unique()->comment('Auto-generated: PRJ-2024-0001');
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('quotation_id')->nullable()->constrained('quotations')->nullOnDelete()
                  ->comment('Quotation yang menjadi dasar proyek ini');
            $table->foreignUuid('manager_id')->constrained('users')->restrictOnDelete()
                  ->comment('Project Manager yang bertanggung jawab');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->comment('Target tanggal selesai');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('budget', 18, 2)->nullable()->comment('Anggaran proyek');
            $table->decimal('actual_cost', 18, 2)->default(0)->comment('Biaya aktual yang sudah dikeluarkan');
            $table->string('status', 20)->default('not_started')->index()
                  ->comment('not_started | in_progress | on_hold | completed | cancelled');
            $table->string('priority', 20)->default('medium')
                  ->comment('low | medium | high | critical');
            $table->unsignedSmallInteger('progress_percent')->default(0)
                  ->comment('Persentase kemajuan 0-100, auto-calculated dari tasks');
            $table->string('color', 7)->nullable()->comment('Hex color untuk identifikasi visual di UI');
            $table->json('tags')->nullable();
            $table->boolean('is_billable')->default(true)->comment('Apakah jam kerja proyek ini bisa di-invoice ke klien');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('client_id');
            $table->index('manager_id');
        });

        // ── Project Members ────────────────────────────────────
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50)->default('member')
                  ->comment('manager | lead | member | observer');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index('project_id');
            $table->index('user_id');
        });

        // ── Task Stages (Kolom Kanban per Proyek) ─────────────
        Schema::create('task_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 100)->comment('Backlog | Todo | In Progress | Review | Done');
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('sequence')->comment('Urutan kolom, untuk drag & drop sorting');
            $table->boolean('is_done_stage')->default(false)
                  ->comment('Task di stage ini dianggap selesai (untuk % completion calculation)');
            $table->unsignedSmallInteger('wip_limit')->nullable()
                  ->comment('Work In Progress limit, 0 atau NULL = tidak terbatas');
            $table->timestamps();

            $table->index('project_id');
            $table->index(['project_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stages');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
