<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tasks ─────────────────────────────────────────────────────────────
        // Buat tabel tanpa self-referential FK terlebih dahulu
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('stage_id')->index();
            // parent_task_id ditambah FK via raw SQL setelah tabel selesai
            $table->uuid('parent_task_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->integer('progress_percent')->default(0);
            $table->boolean('is_milestone')->default(false);
            $table->boolean('is_done')->default(false);
            $table->json('tags')->nullable();
            $table->integer('sequence')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');

            $table->foreign('stage_id')
                ->references('id')->on('task_stages')
                ->onDelete('restrict');
        });

        // Self-referential FK untuk subtask
        DB::statement('
            ALTER TABLE tasks
            ADD CONSTRAINT tasks_parent_task_id_foreign
            FOREIGN KEY (parent_task_id)
            REFERENCES tasks (id)
            ON DELETE SET NULL
            DEFERRABLE INITIALLY DEFERRED
        ');

        // ── Task Assignees ────────────────────────────────────────────────────
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('user_id')->index();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
        });

        // ── Task Dependencies ─────────────────────────────────────────────────
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('depends_on_task_id')->index();
            $table->enum('dependency_type', ['FS', 'SS', 'FF', 'SF'])->default('FS');
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');

            $table->foreign('depends_on_task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
        });

        // ── Task Comments ─────────────────────────────────────────────────────
        Schema::create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->text('content');
            $table->string('attachment_url')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
        });

        // ── Task Checklists ───────────────────────────────────────────────────
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->uuid('done_by')->nullable();
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
        });

        // ── Task Attachments ──────────────────────────────────────────────────
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->string('filename');
            $table->string('file_url');
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
        });

        // ── Task Time Logs ────────────────────────────────────────────────────
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->date('log_date');
            $table->decimal('hours', 6, 2);
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->timestamps();

            $table->foreign('task_id')
                ->references('id')->on('tasks')
                ->onDelete('cascade');
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

        DB::statement('
            ALTER TABLE tasks
            DROP CONSTRAINT IF EXISTS tasks_parent_task_id_foreign
        ');
        Schema::dropIfExists('tasks');
    }
};
