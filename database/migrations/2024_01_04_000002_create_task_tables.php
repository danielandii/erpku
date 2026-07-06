<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tasks ──────────────────────────────────────────────
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('task_stages')->restrictOnDelete()
                  ->comment('Kolom Kanban tempat task berada saat ini');
            $table->uuid('parent_task_id')->nullable()->comment('Self-reference untuk sub-task');
            $table->string('title', 500);
            $table->text('description')->nullable()->comment('Mendukung Markdown');
            $table->string('priority', 20)->default('medium')
                  ->comment('low | medium | high | critical');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->decimal('estimated_hours', 6, 2)->nullable()->comment('Estimasi jam pengerjaan');
            $table->decimal('actual_hours', 6, 2)->default(0)
                  ->comment('Jam aktual dari time_logs (auto-calculated)');
            $table->unsignedSmallInteger('progress_percent')->default(0);
            $table->boolean('is_milestone')->default(false)->comment('Ditampilkan sebagai diamond di Gantt chart');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->json('tags')->nullable()->comment('Label/tag untuk filter Kanban');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(0)
                  ->comment('Urutan dalam stage untuk drag & drop, nilai lebih kecil = lebih atas');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('project_id');
            $table->index('stage_id');
            $table->index(['stage_id', 'sequence']);
            $table->index('is_done');
        });

        // ── Task Assignees ─────────────────────────────────────
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['task_id', 'user_id']);
            $table->index('task_id');
            $table->index('user_id');
        });

        // ── Task Dependencies (Gantt) ──────────────────────────
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete()
                  ->comment('Task yang memiliki dependensi (task yang harus menunggu)');
            $table->foreignUuid('depends_on_task_id')->constrained('tasks')->cascadeOnDelete()
                  ->comment('Task yang harus selesai lebih dahulu');
            $table->string('dependency_type', 5)->default('FS')
                  ->comment('FS=Finish-Start | SS=Start-Start | FF=Finish-Finish | SF=Start-Finish');

            $table->unique(['task_id', 'depends_on_task_id'], 'unique_task_dependency');
            $table->index('task_id');
            $table->index('depends_on_task_id');
        });

        // ── Task Comments ──────────────────────────────────────
        Schema::create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('content')->comment('Isi komentar, mendukung Markdown dan @mention');
            $table->uuid('parent_comment_id')->nullable()->comment('Untuk reply/balasan komentar');
            $table->json('mentions')->nullable()->comment('Array user_id yang di-mention di komentar');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_comment_id')->references('id')->on('task_comments')->nullOnDelete();
            $table->index('task_id');
            $table->index('user_id');
        });

        // ── Task Checklists (Sub-tasks sederhana) ─────────────
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 500);
            $table->boolean('is_done')->default(false);
            $table->foreignUuid('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index('task_id');
        });

        // ── Task Attachments ───────────────────────────────────
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('filename', 255)->comment('Nama file asli');
            $table->string('file_url', 500)->comment('URL di object storage');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('Ukuran file dalam bytes');
            $table->timestamps();

            $table->index('task_id');
        });

        // ── Task Time Logs ─────────────────────────────────────
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('hours', 5, 2)->comment('Jam yang dikerjakan, misal 2.5 = 2 jam 30 menit');
            $table->text('description')->nullable()->comment('Deskripsi pekerjaan yang dilakukan');
            $table->boolean('is_billable')->default(true);
            $table->timestamps();

            $table->index('task_id');
            $table->index('user_id');
            $table->index('log_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
