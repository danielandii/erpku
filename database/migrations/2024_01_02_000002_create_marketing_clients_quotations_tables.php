<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Clients ───────────────────────────────────────────────────────────
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('client_number', 30)->unique();
            $table->enum('type', ['individual', 'company'])->default('company');
            $table->string('name');
            $table->string('alias', 100)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('segment', 50)->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('total_revenue', 20, 2)->default(0);
            $table->uuid('assigned_to')->nullable();
            $table->uuid('source_lead_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Client Contacts ───────────────────────────────────────────────────
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->index();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->onDelete('cascade');
        });

        // ── Quotations ────────────────────────────────────────────────────────
        // PENTING: buat tabel TANPA self-referential FK dulu,
        // lalu tambahkan FK parent_id via raw SQL setelah tabel & primary key sudah ada.
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('quotation_number', 30)->unique();
            $table->integer('version')->default(1);

            // parent_id kolom biasa dulu — FK ditambahkan lewat raw SQL di bawah
            $table->uuid('parent_id')->nullable()->index();

            $table->uuid('lead_id')->nullable()->index();
            $table->uuid('client_id')->nullable()->index();
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_npwp', 30)->nullable();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->enum('status', [
                'draft', 'sent', 'confirmed', 'invoiced', 'expired', 'cancelled',
            ])->default('draft');
            $table->string('po_number', 60)->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('pic_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tambahkan self-referential FK SETELAH tabel sudah ada
        // sehingga primary key UUID sudah eksis dan unique.
        // PostgreSQL memerlukan referenced column memiliki unique/primary constraint.
        DB::statement('
            ALTER TABLE quotations
            ADD CONSTRAINT quotations_parent_id_foreign
            FOREIGN KEY (parent_id)
            REFERENCES quotations (id)
            ON DELETE SET NULL
            DEFERRABLE INITIALLY DEFERRED
        ');

        // ── Quotation Items ───────────────────────────────────────────────────
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quotation_id')->index();
            $table->integer('sequence')->default(0);
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 20, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(11);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('quotation_id')
                ->references('id')->on('quotations')
                ->onDelete('cascade');
        });

        // ── Quotation Templates ───────────────────────────────────────────────
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('items')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Hapus FK manual dulu sebelum drop tabel
        DB::statement('
            ALTER TABLE quotations
            DROP CONSTRAINT IF EXISTS quotations_parent_id_foreign
        ');

        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotation_templates');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};