<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique()->comment('Auto-generated: INV-2024-0001');
            $table->string('type', 20)->default('invoice')->comment('invoice | credit_note');
            $table->uuid('parent_invoice_id')->nullable()
                  ->comment('Jika ini credit note, referensi ke invoice yang dikoreksi');
            $table->foreignUuid('quotation_id')->nullable()->constrained('quotations')->nullOnDelete()
                  ->comment('Quotation asal jika invoice dibuat dari quotation yang confirmed');
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();

            // Snapshots — data klien saat invoice diterbitkan (immutable)
            $table->string('client_name_snapshot');
            $table->text('client_address_snapshot')->nullable();
            $table->string('client_npwp_snapshot', 30)->nullable();

            $table->date('invoice_date')->index();
            $table->date('due_date')->index();
            $table->unsignedSmallInteger('payment_terms')->nullable()
                  ->comment('Tempo pembayaran dalam hari, misal 30 = Net 30');
            $table->string('currency', 10)->default('IDR');

            // Kalkulasi
            $table->decimal('subtotal', 18, 2)->comment('Total sebelum diskon dan pajak');
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_base', 18, 2)->comment('Dasar Pengenaan Pajak (DPP) = subtotal - diskon');
            $table->decimal('ppn_amount', 18, 2)->default(0)->comment('PPN 11% dari DPP');
            $table->decimal('pph_amount', 18, 2)->default(0)->comment('PPh 23 jika berlaku (2% dari DPP)');
            $table->decimal('total_amount', 18, 2)->comment('Total tagihan bersih ke klien');
            $table->decimal('paid_amount', 18, 2)->default(0)->comment('Akumulasi pembayaran yang diterima');
            $table->decimal('remaining_amount', 18, 2)
                  ->comment('Sisa yang belum dibayar = total - paid (auto-calculated)');

            $table->string('status', 20)->default('draft')->index()
                  ->comment('draft | open | partial_paid | paid | overdue | cancelled | void');

            // Recurring invoice
            $table->boolean('is_recurring')->default(false);
            $table->string('recur_interval', 20)->nullable()->comment('daily | weekly | monthly | yearly');
            $table->date('recur_next_date')->nullable();
            $table->date('recur_end_date')->nullable();

            $table->text('notes')->nullable()->comment('Catatan yang tampil di dokumen invoice');
            $table->text('internal_notes')->nullable()->comment('Catatan internal, tidak tampil di dokumen');
            $table->text('terms_conditions')->nullable();
            $table->string('attachment_url', 500)->nullable();

            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('client_id');
            $table->index('quotation_id');
        });

        // ── Invoice Items ──────────────────────────────────────
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->text('description');
            $table->foreignUuid('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                  ->comment('Akun pendapatan terkait — untuk generate jurnal otomatis');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable()->default(11);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
