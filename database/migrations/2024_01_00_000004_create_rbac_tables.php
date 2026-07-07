<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Roles ─────────────────────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()
                  ->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });

        // ── Permissions ───────────────────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module', 50);
            $table->string('resource', 100);
            $table->string('action', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index('module');
        });

        // ── Role ↔ Permission (pivot) ─────────────────────────────────────────
        // granted_by → nullable agar bisa di-seed sebelum users dibuat
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')
                  ->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')
                  ->constrained('permissions')->cascadeOnDelete();
            // NULLABLE — tidak wajib ada user yang meng-grant (untuk seeder/system)
            $table->uuid('granted_by')->nullable()->index();
            $table->timestamp('granted_at')->useCurrent();

            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
            $table->index('permission_id');
        });

        // ── User ↔ Role (pivot) ───────────────────────────────────────────────
        // assigned_by → nullable untuk kasus seeder/system assignment
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                  ->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')
                  ->constrained('roles')->cascadeOnDelete();
            // NULLABLE — tidak wajib ada user yang meng-assign (untuk seeder/system)
            $table->uuid('assigned_by')->nullable()->index();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
