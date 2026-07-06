<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Departments ────────────────────────────────────────
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->uuid('parent_id')->nullable()->comment('Self-referencing untuk hierarki department');
            $table->uuid('manager_id')->nullable()->comment('FK ke employees, di-set setelah employees tersedia');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('parent_id');
        });

        // ── Positions ──────────────────────────────────────────
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 100);
            $table->string('level', 30)->nullable()->comment('Staff | Supervisor | Manager | Director');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // ── Work Schedules ─────────────────────────────────────
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100)->comment('Reguler | Shift Pagi | Shift Malam');
            $table->json('work_days')->comment('Array hari kerja dalam angka: [1,2,3,4,5] = Senin-Jumat');
            $table->time('check_in_time')->comment('Jam masuk standar, contoh: 08:00:00');
            $table->time('check_out_time')->comment('Jam pulang standar, contoh: 17:00:00');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15)->comment('Toleransi keterlambatan dalam menit');
            $table->unsignedSmallInteger('break_duration_minutes')->default(60)->comment('Durasi istirahat dalam menit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // ── Employees ──────────────────────────────────────────
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained('users')->nullOnDelete()
                  ->comment('Link ke akun login (opsional)');
            $table->string('employee_number', 50)->unique()->comment('Nomor Induk Karyawan');
            $table->string('full_name');
            $table->string('nik_ktp', 20)->nullable()->comment('Nomor KTP 16 digit');
            $table->string('npwp', 30)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable()->comment('male | female');
            $table->string('marital_status', 20)->nullable()->comment('single | married | divorced | widowed');
            $table->string('num_dependants', 5)->nullable()->comment('Jumlah tanggungan untuk PPh21: TK0|K0|K1|K2|K3');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email_personal')->nullable();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignUuid('work_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();
            $table->uuid('manager_id')->nullable()->comment('Self-referencing FK ke employees');
            $table->date('join_date');
            $table->date('resign_date')->nullable();
            $table->string('employment_type', 30)->default('permanent')
                  ->comment('permanent | contract | freelance | intern');
            $table->date('contract_end_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bpjs_kes_number', 30)->nullable();
            $table->string('bpjs_tk_number', 30)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('department_id');
            $table->index('position_id');
            $table->index('manager_id');
            $table->index('is_active');
        });

        // ── Set FK yang deferred (manager_id di departments) ──
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        // ── Public Holidays ────────────────────────────────────
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('holiday_date');
            $table->boolean('is_recurring')->default(false)->comment('Berulang setiap tahun (misal hari kemerdekaan)');
            $table->timestamps();

            $table->unique(['tenant_id', 'holiday_date']);
            $table->index(['tenant_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
