<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Buat tabel tanpa self-referential FK dulu
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('account_type', [
                'asset', 'liability', 'equity',
                'revenue', 'cogs', 'expense',
                'other_revenue', 'other_expense',
            ]);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->integer('level')->default(1);
            $table->boolean('is_detail')->default(true);
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            // parent_id sebagai kolom biasa dulu, FK ditambah setelah tabel selesai
            $table->uuid('parent_id')->nullable()->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        // Tambah self-referential FK setelah tabel & primary key sudah ada
        DB::statement('
            ALTER TABLE chart_of_accounts
            ADD CONSTRAINT chart_of_accounts_parent_id_foreign
            FOREIGN KEY (parent_id)
            REFERENCES chart_of_accounts (id)
            ON DELETE SET NULL
            DEFERRABLE INITIALLY DEFERRED
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE chart_of_accounts
            DROP CONSTRAINT IF EXISTS chart_of_accounts_parent_id_foreign
        ');
        Schema::dropIfExists('chart_of_accounts');
    }
};
