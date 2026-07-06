<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present')
                  ->comment('present | late | absent | permission | sick | leave | holiday');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->decimal('check_in_lat', 10, 8)->nullable()->comment('Latitude GPS saat clock-in');
            $table->decimal('check_in_lng', 11, 8)->nullable()->comment('Longitude GPS saat clock-in');
            $table->decimal('check_out_lat', 10, 8)->nullable();
            $table->decimal('check_out_lng', 11, 8)->nullable();
            $table->string('check_in_photo', 500)->nullable()->comment('URL foto selfie saat clock-in');
            $table->string('check_out_photo', 500)->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable()->comment('Menit keterlambatan dari jam masuk');
            $table->unsignedSmallInteger('work_duration_minutes')->nullable()->comment('Total durasi kerja dalam menit');
            $table->text('notes')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Diisi saat ijin manual disetujui atasan');
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date'], 'unique_employee_attendance_date');
            $table->index('tenant_id');
            $table->index('employee_id');
            $table->index('attendance_date');
            $table->index('status');
        });

        // ── Attendance Permissions (Pengajuan Ijin) ────────────
        Schema::create('attendance_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->date('permission_date');
            $table->string('permission_type', 30)->comment('sick | personal | family | official');
            $table->text('reason');
            $table->string('document_url', 500)->nullable()->comment('URL lampiran surat dokter / keterangan');
            $table->string('status', 20)->default('pending')->comment('pending | approved | rejected');
            $table->text('rejection_notes')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('employee_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_permissions');
        Schema::dropIfExists('attendances');
    }
};
