<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Struktur Gaji Karyawan ─────────────────────────────
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->comment('Gaji pokok');
            $table->date('effective_date')->comment('Tanggal berlaku struktur gaji ini');
            $table->date('end_date')->nullable()->comment('Tanggal berakhir (NULL = masih berlaku)');
            /**
             * Format JSON komponen:
             * [
             *   { "name": "Tunjangan Jabatan", "type": "allowance", "amount": 500000, "is_taxable": true, "is_fixed": true },
             *   { "name": "Tunjangan Transport", "type": "allowance", "amount": 300000, "is_taxable": false, "is_fixed": true },
             *   { "name": "Potongan Pinjaman", "type": "deduction", "amount": 200000, "is_taxable": false, "is_fixed": true }
             * ]
             */
            $table->json('components')->nullable()->comment('Array komponen tunjangan dan potongan tetap');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('employee_id');
            $table->index('effective_date');
        });

        // ── Periode Penggajian ─────────────────────────────────
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedSmallInteger('period_month')->comment('1 = Januari, 12 = Desember');
            $table->string('status', 20)->default('draft')
                  ->comment('draft | processing | review | finalized');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('finalized_at')->nullable()->comment('Setelah finalisasi, data tidak dapat diubah');
            $table->foreignUuid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_gross', 18, 2)->nullable()->comment('Total gaji bruto semua karyawan');
            $table->decimal('total_deductions', 18, 2)->nullable();
            $table->decimal('total_net', 18, 2)->nullable()->comment('Total gaji bersih yang ditransfer');
            $table->unsignedSmallInteger('employee_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period_year', 'period_month'], 'unique_payroll_period');
            $table->index('tenant_id');
            $table->index('status');
        });

        // ── Item Gaji per Karyawan per Periode ────────────────
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $table->decimal('base_salary', 15, 2)->comment('Gaji pokok bulan ini (snapshot dari salary_structure)');
            $table->decimal('total_allowances', 15, 2)->default(0)->comment('Total semua tunjangan');
            $table->decimal('total_bonuses', 15, 2)->default(0)->comment('Total bonus one-time bulan ini');
            $table->decimal('deduction_absent', 15, 2)->default(0)->comment('Potongan hari absen tanpa keterangan');
            $table->decimal('deduction_late', 15, 2)->default(0)->comment('Potongan akumulasi keterlambatan');
            $table->decimal('deduction_bpjs_kes', 15, 2)->default(0)->comment('Iuran BPJS Kesehatan porsi karyawan (1%)');
            $table->decimal('deduction_bpjs_tk', 15, 2)->default(0)->comment('Iuran BPJS TK porsi karyawan (2%)');
            $table->decimal('deduction_pph21', 15, 2)->default(0)->comment('Potongan PPh 21');
            $table->decimal('deduction_loan', 15, 2)->default(0)->comment('Cicilan pinjaman karyawan');
            $table->decimal('deduction_others', 15, 2)->default(0)->comment('Potongan lain-lain');
            $table->decimal('gross_salary', 15, 2)->comment('Gaji bruto = pokok + tunjangan + bonus');
            $table->decimal('net_salary', 15, 2)->comment('Gaji bersih (take home pay) = bruto - semua potongan');
            /**
             * Snapshot detail rincian untuk slip gaji:
             * {
             *   "allowances": [...],
             *   "bonuses": [...],
             *   "deductions": [...],
             *   "pph21_detail": { "method": "gross_up", "pkp": 0, "tax": 0 }
             * }
             */
            $table->json('components_detail')->nullable()->comment('Snapshot rincian lengkap untuk cetak slip gaji');
            $table->unsignedSmallInteger('working_days')->nullable()->comment('Hari kerja efektif bulan ini');
            $table->unsignedSmallInteger('present_days')->nullable();
            $table->unsignedSmallInteger('absent_days')->nullable();
            $table->unsignedSmallInteger('late_count')->nullable()->comment('Jumlah kejadian terlambat');
            $table->unsignedSmallInteger('leave_days')->nullable()->comment('Hari cuti yang diambil');
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id'], 'unique_payroll_item');
            $table->index('tenant_id');
            $table->index('payroll_period_id');
            $table->index('employee_id');
        });

        // ── Bonus Tambahan ─────────────────────────────────────
        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->string('bonus_type', 50)->comment('performance | thr | project | annual | other');
            $table->string('description')->comment('Keterangan bonus');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_taxable')->default(true);
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('employee_id');
            $table->index('payroll_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bonuses');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('salary_structures');
    }
};
