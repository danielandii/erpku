<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    /**
     * Format permission: {module}.{resource}.{action}
     *
     * Setiap modul mendefinisikan resource dan action yang diizinkan.
     * Permission ini bersifat global (tidak per-tenant).
     */
    public function run(): void
    {
        $permissions = [

            // ── MODULE: USER ────────────────────────────────────
            ['module' => 'user', 'resource' => 'users',       'action' => 'create',  'description' => 'Buat user baru'],
            ['module' => 'user', 'resource' => 'users',       'action' => 'read',    'description' => 'Lihat daftar & detail user'],
            ['module' => 'user', 'resource' => 'users',       'action' => 'update',  'description' => 'Edit data user'],
            ['module' => 'user', 'resource' => 'users',       'action' => 'delete',  'description' => 'Nonaktifkan / hapus user'],
            ['module' => 'user', 'resource' => 'roles',       'action' => 'create',  'description' => 'Buat role baru'],
            ['module' => 'user', 'resource' => 'roles',       'action' => 'read',    'description' => 'Lihat daftar role'],
            ['module' => 'user', 'resource' => 'roles',       'action' => 'update',  'description' => 'Edit role & permission'],
            ['module' => 'user', 'resource' => 'roles',       'action' => 'delete',  'description' => 'Hapus role kustom'],
            ['module' => 'user', 'resource' => 'audit_logs',  'action' => 'read',    'description' => 'Lihat audit trail sistem'],

            // ── MODULE: HRD ─────────────────────────────────────
            ['module' => 'hrd', 'resource' => 'employees',     'action' => 'create',  'description' => 'Tambah karyawan baru'],
            ['module' => 'hrd', 'resource' => 'employees',     'action' => 'read',    'description' => 'Lihat data karyawan'],
            ['module' => 'hrd', 'resource' => 'employees',     'action' => 'update',  'description' => 'Edit data karyawan'],
            ['module' => 'hrd', 'resource' => 'employees',     'action' => 'delete',  'description' => 'Nonaktifkan karyawan'],
            ['module' => 'hrd', 'resource' => 'attendance',    'action' => 'create',  'description' => 'Clock-in / clock-out'],
            ['module' => 'hrd', 'resource' => 'attendance',    'action' => 'read',    'description' => 'Lihat rekap presensi'],
            ['module' => 'hrd', 'resource' => 'attendance',    'action' => 'update',  'description' => 'Edit presensi (koreksi manual)'],
            ['module' => 'hrd', 'resource' => 'attendance',    'action' => 'approve', 'description' => 'Approve ijin kehadiran'],
            ['module' => 'hrd', 'resource' => 'attendance',    'action' => 'export',  'description' => 'Export laporan kehadiran'],
            ['module' => 'hrd', 'resource' => 'leave',         'action' => 'create',  'description' => 'Ajukan cuti'],
            ['module' => 'hrd', 'resource' => 'leave',         'action' => 'read',    'description' => 'Lihat pengajuan cuti'],
            ['module' => 'hrd', 'resource' => 'leave',         'action' => 'approve', 'description' => 'Approve / tolak cuti'],
            ['module' => 'hrd', 'resource' => 'leave',         'action' => 'manage',  'description' => 'Kelola jenis cuti & alokasi'],
            ['module' => 'hrd', 'resource' => 'payroll',       'action' => 'read',    'description' => 'Lihat data penggajian'],
            ['module' => 'hrd', 'resource' => 'payroll',       'action' => 'process', 'description' => 'Proses batch payroll'],
            ['module' => 'hrd', 'resource' => 'payroll',       'action' => 'finalize','description' => 'Finalisasi payroll periode'],
            ['module' => 'hrd', 'resource' => 'payroll',       'action' => 'export',  'description' => 'Export data transfer bank'],
            ['module' => 'hrd', 'resource' => 'salary',        'action' => 'create',  'description' => 'Input struktur gaji karyawan'],
            ['module' => 'hrd', 'resource' => 'salary',        'action' => 'read',    'description' => 'Lihat struktur gaji'],
            ['module' => 'hrd', 'resource' => 'salary',        'action' => 'update',  'description' => 'Edit struktur gaji'],

            // ── MODULE: MARKETING ───────────────────────────────
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'create',  'description' => 'Buat lead baru'],
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'read',    'description' => 'Lihat daftar lead'],
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'update',  'description' => 'Edit lead & ubah stage'],
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'delete',  'description' => 'Hapus lead'],
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'assign',  'description' => 'Assign lead ke salesperson'],
            ['module' => 'marketing', 'resource' => 'leads',      'action' => 'convert', 'description' => 'Konversi lead ke klien'],
            ['module' => 'marketing', 'resource' => 'quotations', 'action' => 'create',  'description' => 'Buat quotation'],
            ['module' => 'marketing', 'resource' => 'quotations', 'action' => 'read',    'description' => 'Lihat quotation'],
            ['module' => 'marketing', 'resource' => 'quotations', 'action' => 'update',  'description' => 'Edit quotation draft'],
            ['module' => 'marketing', 'resource' => 'quotations', 'action' => 'send',    'description' => 'Kirim quotation ke klien'],
            ['module' => 'marketing', 'resource' => 'quotations', 'action' => 'confirm', 'description' => 'Konfirmasi quotation'],
            ['module' => 'marketing', 'resource' => 'clients',    'action' => 'create',  'description' => 'Tambah klien baru'],
            ['module' => 'marketing', 'resource' => 'clients',    'action' => 'read',    'description' => 'Lihat daftar klien'],
            ['module' => 'marketing', 'resource' => 'clients',    'action' => 'update',  'description' => 'Edit data klien'],
            ['module' => 'marketing', 'resource' => 'clients',    'action' => 'delete',  'description' => 'Nonaktifkan klien'],

            // ── MODULE: FINANCE ─────────────────────────────────
            ['module' => 'finance', 'resource' => 'coa',          'action' => 'create',  'description' => 'Tambah akun COA baru'],
            ['module' => 'finance', 'resource' => 'coa',          'action' => 'read',    'description' => 'Lihat Chart of Accounts'],
            ['module' => 'finance', 'resource' => 'coa',          'action' => 'update',  'description' => 'Edit akun COA'],
            ['module' => 'finance', 'resource' => 'invoices',     'action' => 'create',  'description' => 'Buat invoice baru'],
            ['module' => 'finance', 'resource' => 'invoices',     'action' => 'read',    'description' => 'Lihat daftar invoice'],
            ['module' => 'finance', 'resource' => 'invoices',     'action' => 'update',  'description' => 'Edit invoice draft'],
            ['module' => 'finance', 'resource' => 'invoices',     'action' => 'send',    'description' => 'Kirim invoice ke klien'],
            ['module' => 'finance', 'resource' => 'invoices',     'action' => 'cancel',  'description' => 'Batalkan / void invoice'],
            ['module' => 'finance', 'resource' => 'payments',     'action' => 'create',  'description' => 'Catat pembayaran masuk/keluar'],
            ['module' => 'finance', 'resource' => 'payments',     'action' => 'read',    'description' => 'Lihat riwayat pembayaran'],
            ['module' => 'finance', 'resource' => 'bills',        'action' => 'create',  'description' => 'Input tagihan vendor'],
            ['module' => 'finance', 'resource' => 'bills',        'action' => 'read',    'description' => 'Lihat daftar tagihan vendor'],
            ['module' => 'finance', 'resource' => 'bills',        'action' => 'approve', 'description' => 'Approve pembayaran vendor'],
            ['module' => 'finance', 'resource' => 'journals',     'action' => 'create',  'description' => 'Input jurnal manual'],
            ['module' => 'finance', 'resource' => 'journals',     'action' => 'read',    'description' => 'Lihat jurnal umum'],
            ['module' => 'finance', 'resource' => 'journals',     'action' => 'post',    'description' => 'Post jurnal ke GL'],
            ['module' => 'finance', 'resource' => 'reports',      'action' => 'read',    'description' => 'Lihat laporan keuangan (P&L, Neraca, Cash Flow)'],
            ['module' => 'finance', 'resource' => 'reports',      'action' => 'export',  'description' => 'Export laporan ke Excel/PDF'],

            // ── MODULE: PROJECT ─────────────────────────────────
            ['module' => 'project', 'resource' => 'projects',   'action' => 'create',  'description' => 'Buat proyek baru'],
            ['module' => 'project', 'resource' => 'projects',   'action' => 'read',    'description' => 'Lihat daftar proyek'],
            ['module' => 'project', 'resource' => 'projects',   'action' => 'update',  'description' => 'Edit data proyek'],
            ['module' => 'project', 'resource' => 'projects',   'action' => 'delete',  'description' => 'Hapus proyek'],
            ['module' => 'project', 'resource' => 'tasks',      'action' => 'create',  'description' => 'Buat task baru'],
            ['module' => 'project', 'resource' => 'tasks',      'action' => 'read',    'description' => 'Lihat task'],
            ['module' => 'project', 'resource' => 'tasks',      'action' => 'update',  'description' => 'Edit task & pindahkan stage'],
            ['module' => 'project', 'resource' => 'tasks',      'action' => 'delete',  'description' => 'Hapus task'],
            ['module' => 'project', 'resource' => 'tasks',      'action' => 'assign',  'description' => 'Assign task ke anggota tim'],
            ['module' => 'project', 'resource' => 'time_logs',  'action' => 'create',  'description' => 'Log waktu kerja'],
            ['module' => 'project', 'resource' => 'time_logs',  'action' => 'read',    'description' => 'Lihat time log'],
            ['module' => 'project', 'resource' => 'kanban',     'action' => 'manage',  'description' => 'Kelola kolom Kanban (tambah/edit/hapus stage)'],
        ];

        $now = now();
        $rows = array_map(function ($p) use ($now) {
            return [
                'id'          => Str::uuid(),
                'module'      => $p['module'],
                'resource'    => $p['resource'],
                'action'      => $p['action'],
                'name'        => "{$p['module']}.{$p['resource']}.{$p['action']}",
                'description' => $p['description'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }, $permissions);

        // Chunk insert untuk performa
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('permissions')->insertOrIgnore($chunk);
        }

        $this->command->info('✓ ' . count($rows) . ' permissions seeded.');
    }
}
