<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Bank Accounts ─────────────────────────────────────────────────────
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('bank_name', 100);
            $table->string('account_number', 50)->unique();
            $table->string('account_name');
            $table->enum('account_type', ['giro', 'savings', 'petty_cash'])->default('giro');
            $table->uuid('coa_id')->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->decimal('opening_balance', 20, 2)->default(0);
            $table->decimal('current_balance', 20, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('coa_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');
        });

        // ── Invoices ──────────────────────────────────────────────────────────
        // Buat tabel dulu tanpa self-referential FK
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('invoice_number', 30)->unique();
            $table->enum('type', ['invoice', 'credit_note'])->default('invoice');
            // parent_invoice_id ditambah FK via raw SQL setelah tabel dibuat
            $table->uuid('parent_invoice_id')->nullable()->index();
            $table->uuid('quotation_id')->nullable()->index();
            $table->uuid('client_id')->nullable()->index();
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_npwp', 30)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->integer('payment_terms')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_base', 20, 2)->default(0);
            $table->decimal('ppn_amount', 20, 2)->default(0);
            $table->decimal('pph_amount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2)->default(0);
            $table->enum('status', [
                'draft', 'open', 'partial_paid', 'paid',
                'overdue', 'cancelled', 'void',
            ])->default('draft');
            $table->boolean('is_recurring')->default(false);
            $table->string('recur_interval', 20)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('attachment_url')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Self-referential FK untuk invoices (credit note → original invoice)
        DB::statement('
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_parent_invoice_id_foreign
            FOREIGN KEY (parent_invoice_id)
            REFERENCES invoices (id)
            ON DELETE SET NULL
            DEFERRABLE INITIALLY DEFERRED
        ');

        // ── Invoice Items ─────────────────────────────────────────────────────
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id')->index();
            $table->integer('sequence')->default(0);
            $table->string('description');
            $table->string('coa_code', 20)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 20, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(11);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');

        DB::statement('
            ALTER TABLE invoices
            DROP CONSTRAINT IF EXISTS invoices_parent_invoice_id_foreign
        ');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bank_accounts');
    }
};
