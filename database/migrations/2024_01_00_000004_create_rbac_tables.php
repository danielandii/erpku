<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Roles ─────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()
                  ->comment('NULL = system role berlaku global');
            $table->string('name', 100);
            $table->string('slug', 100)->comment('Identifier: super_admin | admin | manager | staff | viewer');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('System role tidak dapat dihapus');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });

        // ── Permissions ────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module', 50)->comment('Nama modul: user | hrd | marketing | finance | project');
            $table->string('resource', 100)->comment('Resource: attendance | invoice | lead | task');
            $table->string('action', 50)->comment('Action: create | read | update | delete | approve | export');
            $table->string('name', 255)->comment('Format: module.resource.action — misal: hrd.attendance.create');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index('module');
        });

        // ── Role ↔ Permission (pivot) ─────────────────────────
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('granted_at')->useCurrent();

            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
            $table->index('permission_id');
        });

        // ── User ↔ Role (pivot) ───────────────────────────────
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('Opsional: role sementara dengan masa berlaku');

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
