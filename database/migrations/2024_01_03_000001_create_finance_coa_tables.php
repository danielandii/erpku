<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Chart of Accounts (COA) ────────────────────────────
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 20)->comment('Kode akun: 1110, 4010, 6020');
            $table->string('name');
            $table->string('account_type', 30)
                  ->comment('asset | liability | equity | revenue | cogs | expense | other_revenue | other_expense');
            $table->string('normal_balance', 10)->comment('debit | credit — Saldo normal akun ini');
            $table->uuid('parent_id')->nullable()->comment('Self-reference untuk hierarki COA');
            $table->unsignedSmallInteger('level')->default(1)
                  ->comment('1=kelompok | 2=subkelompok | 3=akun detail');
            $table->boolean('is_detail')->default(true)
                  ->comment('Hanya akun detail (level 3) yang bisa diposting');
            $table->boolean('is_cash_account')->default(false)
                  ->comment('Menandai akun ini adalah kas/bank untuk arus kas');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->foreign('parent_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('account_type');
            $table->index('is_detail');
        });

        // ── Bank / Kas Accounts ────────────────────────────────
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('coa_id')->constrained('chart_of_accounts')->restrictOnDelete()
                  ->comment('Akun GL yang merepresentasikan rekening ini (akun kas/bank)');
            $table->string('bank_name', 100)->comment('BCA | Mandiri | BNI | BRI | BSI');
            $table->string('account_number', 50)->unique();
            $table->string('account_name')->comment('Nama pemegang rekening sesuai buku tabungan');
            $table->string('account_type', 20)->comment('giro | savings | petty_cash');
            $table->string('currency', 10)->default('IDR');
            $table->decimal('current_balance', 18, 2)->default(0)
                  ->comment('Saldo saat ini (update otomatis saat ada transaksi)');
            $table->string('branch', 100)->nullable()->comment('Nama cabang bank');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)->comment('Rekening default untuk penerimaan');
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
