<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Jenis Cuti ─────────────────────────────────────────
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100)->comment('Cuti Tahunan | Cuti Sakit | Cuti Melahirkan | Cuti Duka');
            $table->string('code', 20)->comment('ANNUAL | SICK | MATERNITY | BEREAVEMENT');
            $table->unsignedSmallInteger('default_days')->comment('Jatah default per tahun');
            $table->boolean('is_paid')->default(true)->comment('Apakah cuti ini berbayar (tidak potong gaji)');
            $table->boolean('carry_over')->default(false)->comment('Sisa cuti bisa dibawa ke tahun berikutnya');
            $table->unsignedSmallInteger('max_carry_over_days')->nullable();
            $table->boolean('requires_document')->default(false)->comment('Wajib lampirkan dokumen (misal surat dokter)');
            $table->string('gender_specific', 10)->nullable()->comment('Khusus gender: male | female | NULL = semua');
            $table->unsignedSmallInteger('max_consecutive_days')->nullable()->comment('Maks hari berturut-turut dalam satu pengajuan');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
        });

        // ── Alokasi Cuti per Karyawan per Tahun ───────────────
        Schema::create('leave_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_days', 5, 1)->comment('Total hari yang dialokasikan');
            $table->decimal('used_days', 5, 1)->default(0)->comment('Total hari yang sudah terpakai');
            $table->decimal('pending_days', 5, 1)->default(0)->comment('Hari dalam proses pengajuan / belum disetujui');
            $table->decimal('carry_over_days', 5, 1)->default(0)->comment('Sisa carry-over dari tahun sebelumnya');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'unique_leave_allocation');
            $table->index('tenant_id');
            $table->index('employee_id');
        });

        // ── Pengajuan Cuti ─────────────────────────────────────
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1)->comment('Total hari kerja (tidak termasuk weekend & hari libur)');
            $table->text('reason');
            $table->string('document_url', 500)->nullable();
            $table->string('status', 20)->default('pending')
                  ->comment('draft | pending | approved | rejected | cancelled');
            $table->text('rejection_notes')->nullable();

            // Multi-level approval (Level 1 = Manager, Level 2 = HR Manager)
            $table->foreignUuid('approved_by_l1')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at_l1')->nullable();
            $table->foreignUuid('approved_by_l2')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at_l2')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('employee_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_allocations');
        Schema::dropIfExists('leave_types');
    }
};
