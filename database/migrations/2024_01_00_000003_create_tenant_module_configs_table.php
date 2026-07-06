<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_name', 50)->comment('user | hrd | marketing | finance | project');
            $table->boolean('is_enabled')->default(false);
            $table->json('config_json')->nullable()->comment('Konfigurasi kustom per modul dalam format JSON');
            $table->timestamp('enabled_at')->nullable();
            $table->foreignUuid('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_name']);
            $table->index('tenant_id');
            $table->index('module_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_configs');
    }
};
