<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Bills / Hutang Vendor (Accounts Payable) ───────────
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bill_number', 30)->unique()->comment('Auto-generated: BILL-2024-0001');
            $table->string('vendor_name')->comment('Nama vendor / supplier');
            $table->string('vendor_invoice_number', 100)->nullable()->comment('Nomor invoice dari vendor');
            $table->date('bill_date');
            $table->date('due_date');
            $table->text('description');
            $table->foreignUuid('coa_id')->constrained('chart_of_accounts')->restrictOnDelete()
                  ->comment('Akun beban/aset terkait (misal: Beban Sewa, Beban Utilitas)');
            $table->decimal('amount', 18, 2)->comment('Total tagihan dari vendor');
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('unpaid')
                  ->comment('unpaid | partial | paid | overdue | cancelled');
            $table->string('attachment_url', 500)->nullable()->comment('Scan / foto tagihan vendor');
            $table->text('notes')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Pengeluaran besar memerlukan approval');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('due_date');
        });

        // ── Payments (Pembayaran Masuk & Keluar) ──────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('payment_number', 30)->unique()->comment('Auto-generated: PAY-2024-0001');
            $table->string('type', 20)->comment('inbound (dari klien) | outbound (ke vendor/pihak lain)');
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()
                  ->comment('Referensi invoice untuk pembayaran inbound');
            $table->foreignUuid('bill_id')->nullable()->constrained('bills')->nullOnDelete()
                  ->comment('Referensi bill untuk pembayaran outbound ke vendor');
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('vendor_name')->nullable()->comment('Nama penerima jika bukan client');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 30)
                  ->comment('bank_transfer | cash | check | giro | qris | virtual_account | credit_card');
            $table->string('reference_number', 100)->nullable()->comment('Nomor referensi / kode transfer bank');
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete()
                  ->comment('Akun bank/kas yang menerima atau mengirim dana');
            $table->text('notes')->nullable();
            $table->string('attachment_url', 500)->nullable()->comment('Bukti transfer / kwitansi');
            $table->uuid('journal_entry_id')->nullable()->comment('FK ke journal_entries, di-set setelah jurnal dibuat');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('invoice_id');
            $table->index('bill_id');
            $table->index('payment_date');
            $table->index('type');
        });

        // ── Journal Entries (Jurnal Umum) ──────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entry_number', 30)->unique()->comment('Auto-generated: JNL-2024-0001');
            $table->string('type', 30)
                  ->comment('manual | invoice | credit_note | payment | bill | payroll | adjustment | closing');
            $table->string('reference_type', 50)->nullable()
                  ->comment('Polymorphic: Invoice | Payment | Bill | PayrollPeriod');
            $table->uuid('reference_id')->nullable()->comment('ID dari record referensi (polymorphic)');
            $table->text('description');
            $table->date('entry_date')->index();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedSmallInteger('period_month');
            $table->decimal('total_debit', 18, 2);
            $table->decimal('total_credit', 18, 2);
            $table->boolean('is_posted')->default(false)
                  ->comment('Setelah posted, jurnal masuk ke General Ledger');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_reversed')->default(false);
            $table->uuid('reversed_by_entry_id')->nullable()
                  ->comment('ID jurnal reverse (jika entry ini sudah di-reverse)');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('reversed_by_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('reference_type');
            $table->index('reference_id');
            $table->index(['period_year', 'period_month']);
        });

        // ── Journal Lines (Baris Debit/Kredit) ────────────────
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('coa_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 18, 2)->default(0);
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('coa_id');
        });

        // FK deferred: payments.journal_entry_id → journal_entries.id
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
        });
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bills');
    }
};
