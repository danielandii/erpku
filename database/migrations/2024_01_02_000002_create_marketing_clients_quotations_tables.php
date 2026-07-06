<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Clients / Members ──────────────────────────────────
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('client_number', 30)->unique()->comment('Auto-generated: CLI-2024-0001');
            $table->string('type', 20)->default('company')->comment('individual | company');
            $table->string('name')->index();
            $table->string('alias', 100)->nullable()->comment('Nama panggilan atau singkatan');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->default('Indonesia');
            $table->string('npwp', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('segment', 50)->nullable()->comment('SME | Enterprise | Government | Individual');
            $table->json('tags')->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Account manager yang mengelola klien ini');
            $table->foreignUuid('source_lead_id')->nullable()->constrained('leads')->nullOnDelete()
                  ->comment('Lead asal jika klien berasal dari konversi lead');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('assigned_to');
        });

        // Set deferred FK: leads.converted_to_client_id → clients.id
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('converted_to_client_id')->references('id')->on('clients')->nullOnDelete();
        });

        // ── Contact Persons per Klien ──────────────────────────
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('position', 100)->nullable()->comment('Jabatan contact person di perusahaan klien');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false)->comment('Kontak utama yang dihubungi pertama kali');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('client_id');
        });

        // ── Quotation Templates ────────────────────────────────
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('items')->comment('Array item template dengan deskripsi, harga, unit');
            $table->text('terms_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });

        // ── Quotations ─────────────────────────────────────────
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('quotation_number', 30)->unique()->comment('Auto-generated: QUO-2024-0001');
            $table->unsignedSmallInteger('version')->default(1)->comment('Nomor revisi');
            $table->uuid('parent_id')->nullable()->comment('ID quotation yang direvisi (self-reference)');
            $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_name_snapshot')->comment('Snapshot nama klien saat quotation dibuat');
            $table->text('client_address_snapshot')->nullable();
            $table->date('date');
            $table->date('valid_until')->nullable()->comment('Tanggal kadaluarsa penawaran');
            $table->foreignUuid('pic_user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('PIC dari sisi perusahaan (salesperson)');
            $table->string('currency', 10)->default('IDR');
            $table->decimal('subtotal', 18, 2)->comment('Total sebelum diskon header dan pajak');
            $table->decimal('discount_percent', 5, 2)->nullable()->comment('Diskon header dalam persen');
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0)->comment('Total PPN');
            $table->decimal('total_amount', 18, 2)->comment('Total akhir yang ditawarkan');
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable()->comment('Catatan internal, tidak tampil di dokumen');
            $table->string('status', 20)->default('draft')
                  ->comment('draft | sent | confirmed | invoiced | expired | cancelled');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('po_number', 100)->nullable()->comment('Nomor Purchase Order dari klien');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('quotations')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('client_id');
            $table->index('status');
        });

        // ── Quotation Items ────────────────────────────────────
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->comment('Urutan tampil item');
            $table->text('description');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 30)->nullable()->comment('unit | pcs | jam | hari | bulan | paket');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable()->default(11)
                  ->comment('PPN per item, default 11%');
            $table->decimal('subtotal', 15, 2)->comment('quantity x unit_price');
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->comment('Subtotal setelah diskon dan pajak item');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_templates');
        Schema::dropIfExists('client_contacts');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['converted_to_client_id']);
        });
        Schema::dropIfExists('clients');
    }
};
