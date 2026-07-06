<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Projects ──────────────────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('project_number', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', [
                'not_started', 'in_progress', 'on_hold', 'completed', 'cancelled',
            ])->default('not_started');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('progress_percent')->default(0);
            $table->string('color', 10)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('budget', 20, 2)->nullable();
            $table->decimal('actual_cost', 20, 2)->default(0);
            $table->uuid('client_id')->nullable()->index();
            $table->uuid('manager_id')->nullable()->index();
            $table->uuid('quotation_id')->nullable()->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->onDelete('set null');
        });

        // ── Project Members ───────────────────────────────────────────────────
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('user_id')->index();
            $table->enum('role', ['manager', 'lead', 'member', 'observer'])->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');
        });

        // ── Task Stages ───────────────────────────────────────────────────────
        Schema::create('task_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->string('name', 100);
            $table->string('color', 10)->nullable();
            $table->integer('sequence')->default(0);
            $table->boolean('is_done_stage')->default(false);
            $table->integer('wip_limit')->nullable();
            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stages');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
