<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Journal Entries ───────────────────────────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('entry_number', 30)->unique();
            $table->string('type', 50)->default('manual');
            $table->string('description');
            $table->date('entry_date');
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('total_debit', 20, 2)->default(0);
            $table->decimal('total_credit', 20, 2)->default(0);
            $table->boolean('is_balanced')->default(false);
            $table->boolean('is_posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->uuid('reversed_by_entry_id')->nullable();
            $table->text('reversal_reason')->nullable();
            // source polymorphic
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable()->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'period_year', 'period_month']);
        });

        // ── Journal Lines ─────────────────────────────────────────────────────
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id')->index();
            $table->uuid('coa_id')->index();
            $table->string('coa_code', 20)->nullable();
            $table->string('coa_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 20, 2)->default(0);
            $table->decimal('credit_amount', 20, 2)->default(0);
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->foreign('journal_entry_id')
                ->references('id')->on('journal_entries')
                ->onDelete('cascade');

            $table->foreign('coa_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('restrict');
        });

        // ── Bills (Hutang / AP) ───────────────────────────────────────────────
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('bill_number', 30)->unique();
            $table->string('vendor_name');
            $table->string('vendor_invoice_number', 60)->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->text('description');
            $table->uuid('coa_id')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2)->default(0);
            $table->enum('status', [
                'unpaid', 'partial', 'paid', 'overdue', 'cancelled',
            ])->default('unpaid');
            $table->string('attachment_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coa_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');

            $table->foreign('journal_entry_id')
                ->references('id')->on('journal_entries')
                ->onDelete('set null');
        });

        // ── Payments ──────────────────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('payment_number', 30)->unique();
            $table->enum('type', ['inbound', 'outbound'])->default('inbound');
            $table->uuid('invoice_id')->nullable()->index();
            $table->uuid('bill_id')->nullable()->index();
            $table->uuid('client_id')->nullable()->index();
            $table->string('vendor_name')->nullable();
            $table->uuid('bank_account_id')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 20, 2);
            $table->string('payment_method', 50)->default('bank_transfer');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_url')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->onDelete('set null');

            $table->foreign('bill_id')
                ->references('id')->on('bills')
                ->onDelete('set null');

            $table->foreign('bank_account_id')
                ->references('id')->on('bank_accounts')
                ->onDelete('set null');

            $table->foreign('journal_entry_id')
                ->references('id')->on('journal_entries')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
