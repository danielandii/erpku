<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountSeeder extends Seeder
{
    private string $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    private array $coaMap    = []; // code => id

    public function run(): void
    {
        $now = now();

        /**
         * Format: [code, name, type, normal_balance, parent_code, level, is_detail]
         *
         * Tipe akun:
         *   asset | liability | equity | revenue | cogs | expense | other_revenue | other_expense
         */
        $accounts = [
            // ══════════════════════════════════════════════
            // 1. ASET
            // ══════════════════════════════════════════════
            ['1',    'ASET',                         'asset',     'debit',  null, 1, false],

            // Aset Lancar
            ['11',   'Aset Lancar',                  'asset',     'debit',  '1',  2, false],
            ['1110', 'Kas dan Bank',                  'asset',     'debit',  '11', 3, false],
            ['1111', 'Kas Kecil',                     'asset',     'debit',  '1110',4,true],
            ['1112', 'Bank BCA — Rekening Giro',      'asset',     'debit',  '1110',4,true],
            ['1113', 'Bank Mandiri — Rekening Giro',  'asset',     'debit',  '1110',4,true],
            ['1114', 'Bank BNI — Rekening Giro',      'asset',     'debit',  '1110',4,true],

            ['1200', 'Piutang Usaha',                 'asset',     'debit',  '11', 3, false],
            ['1201', 'Piutang Usaha — Umum',          'asset',     'debit',  '1200',4,true],
            ['1205', 'Cadangan Kerugian Piutang',     'asset',     'credit', '1200',4,true],

            ['1300', 'Persediaan',                    'asset',     'debit',  '11', 3, false],
            ['1301', 'Persediaan Barang Dagang',      'asset',     'debit',  '1300',4,true],
            ['1302', 'Persediaan Bahan Baku',         'asset',     'debit',  '1300',4,true],

            ['1400', 'Aset Lancar Lainnya',           'asset',     'debit',  '11', 3, false],
            ['1401', 'Biaya Dibayar Dimuka',          'asset',     'debit',  '1400',4,true],
            ['1402', 'Uang Muka Pembelian',           'asset',     'debit',  '1400',4,true],
            ['1403', 'PPN Masukan',                   'asset',     'debit',  '1400',4,true],

            // Aset Tidak Lancar
            ['12',   'Aset Tidak Lancar',             'asset',     'debit',  '1',  2, false],
            ['1510', 'Aset Tetap',                    'asset',     'debit',  '12', 3, false],
            ['1511', 'Tanah',                         'asset',     'debit',  '1510',4,true],
            ['1512', 'Bangunan',                      'asset',     'debit',  '1510',4,true],
            ['1513', 'Kendaraan',                     'asset',     'debit',  '1510',4,true],
            ['1514', 'Peralatan Kantor',              'asset',     'debit',  '1510',4,true],
            ['1515', 'Peralatan IT & Komputer',       'asset',     'debit',  '1510',4,true],
            ['1520', 'Akumulasi Penyusutan',          'asset',     'credit', '12', 3, false],
            ['1521', 'Akum. Penyusutan Bangunan',     'asset',     'credit', '1520',4,true],
            ['1522', 'Akum. Penyusutan Kendaraan',    'asset',     'credit', '1520',4,true],
            ['1523', 'Akum. Penyusutan Peralatan',    'asset',     'credit', '1520',4,true],
            ['1530', 'Aset Tidak Berwujud',           'asset',     'debit',  '12', 3, false],
            ['1531', 'Software & Lisensi',            'asset',     'debit',  '1530',4,true],
            ['1532', 'Hak Paten & Merek',             'asset',     'debit',  '1530',4,true],

            // ══════════════════════════════════════════════
            // 2. LIABILITAS
            // ══════════════════════════════════════════════
            ['2',    'LIABILITAS',                    'liability', 'credit', null, 1, false],

            // Liabilitas Jangka Pendek
            ['21',   'Liabilitas Jangka Pendek',      'liability', 'credit', '2',  2, false],
            ['2100', 'Hutang Usaha',                  'liability', 'credit', '21', 3, false],
            ['2101', 'Hutang Usaha — Vendor',         'liability', 'credit', '2100',4,true],
            ['2200', 'Hutang Pajak',                  'liability', 'credit', '21', 3, false],
            ['2201', 'Hutang PPh 21',                 'liability', 'credit', '2200',4,true],
            ['2202', 'Hutang PPh 23',                 'liability', 'credit', '2200',4,true],
            ['2203', 'PPN Keluaran',                  'liability', 'credit', '2200',4,true],
            ['2204', 'Hutang PPh Badan',              'liability', 'credit', '2200',4,true],
            ['2300', 'Hutang BPJS',                   'liability', 'credit', '21', 3, false],
            ['2301', 'Hutang BPJS Kesehatan',         'liability', 'credit', '2300',4,true],
            ['2302', 'Hutang BPJS Ketenagakerjaan',   'liability', 'credit', '2300',4,true],
            ['2400', 'Hutang Jangka Pendek Lainnya',  'liability', 'credit', '21', 3, false],
            ['2401', 'Uang Muka Pelanggan',           'liability', 'credit', '2400',4,true],
            ['2402', 'Pendapatan Diterima Dimuka',    'liability', 'credit', '2400',4,true],
            ['2403', 'Biaya Akrual (Masih Harus Dibayar)','liability','credit','2400',4,true],
            ['2404', 'Hutang Gaji Karyawan',          'liability', 'credit', '2400',4,true],

            // Liabilitas Jangka Panjang
            ['22',   'Liabilitas Jangka Panjang',     'liability', 'credit', '2',  2, false],
            ['2500', 'Hutang Bank Jangka Panjang',    'liability', 'credit', '22', 3, false],
            ['2501', 'Pinjaman Bank BCA',             'liability', 'credit', '2500',4,true],
            ['2502', 'Pinjaman Bank Mandiri',         'liability', 'credit', '2500',4,true],

            // ══════════════════════════════════════════════
            // 3. EKUITAS
            // ══════════════════════════════════════════════
            ['3',    'EKUITAS',                       'equity',    'credit', null, 1, false],
            ['3100', 'Modal',                         'equity',    'credit', '3',  2, false],
            ['3101', 'Modal Disetor',                 'equity',    'credit', '3100',3,true],
            ['3102', 'Tambahan Modal Disetor',        'equity',    'credit', '3100',3,true],
            ['3200', 'Saldo Laba',                    'equity',    'credit', '3',  2, false],
            ['3201', 'Laba Ditahan',                  'equity',    'credit', '3200',3,true],
            ['3202', 'Laba Rugi Tahun Berjalan',      'equity',    'credit', '3200',3,true],
            ['3203', 'Dividen',                       'equity',    'debit',  '3200',3,true],

            // ══════════════════════════════════════════════
            // 4. PENDAPATAN
            // ══════════════════════════════════════════════
            ['4',    'PENDAPATAN',                    'revenue',   'credit', null, 1, false],
            ['4100', 'Pendapatan Usaha',              'revenue',   'credit', '4',  2, false],
            ['4101', 'Pendapatan Jasa Konsultasi',    'revenue',   'credit', '4100',3,true],
            ['4102', 'Pendapatan Jasa Implementasi',  'revenue',   'credit', '4100',3,true],
            ['4103', 'Pendapatan Jasa Maintenance',   'revenue',   'credit', '4100',3,true],
            ['4104', 'Pendapatan Penjualan Produk',   'revenue',   'credit', '4100',3,true],
            ['4105', 'Pendapatan Lisensi Software',   'revenue',   'credit', '4100',3,true],
            ['4106', 'Pendapatan Retainer / Langganan','revenue',  'credit', '4100',3,true],

            // ══════════════════════════════════════════════
            // 5. BEBAN POKOK (HPP / COGS)
            // ══════════════════════════════════════════════
            ['5',    'HARGA POKOK PENDAPATAN',        'cogs',      'debit',  null, 1, false],
            ['5100', 'HPP Jasa',                      'cogs',      'debit',  '5',  2, false],
            ['5101', 'Beban Langsung Tenaga Ahli',    'cogs',      'debit',  '5100',3,true],
            ['5102', 'Beban Subkontraktor',           'cogs',      'debit',  '5100',3,true],
            ['5103', 'Beban Material Proyek',         'cogs',      'debit',  '5100',3,true],
            ['5200', 'HPP Produk',                    'cogs',      'debit',  '5',  2, false],
            ['5201', 'Harga Pokok Penjualan Produk',  'cogs',      'debit',  '5200',3,true],

            // ══════════════════════════════════════════════
            // 6. BEBAN OPERASIONAL
            // ══════════════════════════════════════════════
            ['6',    'BEBAN OPERASIONAL',             'expense',   'debit',  null, 1, false],

            // Beban SDM
            ['6100', 'Beban Sumber Daya Manusia',     'expense',   'debit',  '6',  2, false],
            ['6101', 'Beban Gaji Pokok',              'expense',   'debit',  '6100',3,true],
            ['6102', 'Beban Tunjangan Karyawan',      'expense',   'debit',  '6100',3,true],
            ['6103', 'Beban BPJS Kesehatan (Pemberi Kerja)','expense','debit','6100',3,true],
            ['6104', 'Beban BPJS TK (Pemberi Kerja)', 'expense',   'debit',  '6100',3,true],
            ['6105', 'Beban THR & Bonus',             'expense',   'debit',  '6100',3,true],
            ['6106', 'Beban Rekrutmen & Training',    'expense',   'debit',  '6100',3,true],

            // Beban Kantor
            ['6200', 'Beban Umum & Administrasi',     'expense',   'debit',  '6',  2, false],
            ['6201', 'Beban Sewa Kantor',             'expense',   'debit',  '6200',3,true],
            ['6202', 'Beban Listrik, Air & Gas',      'expense',   'debit',  '6200',3,true],
            ['6203', 'Beban Internet & Komunikasi',   'expense',   'debit',  '6200',3,true],
            ['6204', 'Beban Alat Tulis Kantor (ATK)', 'expense',   'debit',  '6200',3,true],
            ['6205', 'Beban Kebersihan & Keamanan',   'expense',   'debit',  '6200',3,true],
            ['6206', 'Beban Penyusutan Aset Tetap',   'expense',   'debit',  '6200',3,true],
            ['6207', 'Beban Amortisasi Aset Tak Berwujud','expense','debit', '6200',3,true],
            ['6208', 'Beban Perbaikan & Pemeliharaan','expense',   'debit',  '6200',3,true],
            ['6209', 'Beban Perjalanan Dinas',        'expense',   'debit',  '6200',3,true],
            ['6210', 'Beban Asuransi',                'expense',   'debit',  '6200',3,true],

            // Beban Penjualan & Marketing
            ['6300', 'Beban Penjualan & Marketing',   'expense',   'debit',  '6',  2, false],
            ['6301', 'Beban Iklan & Promosi',         'expense',   'debit',  '6300',3,true],
            ['6302', 'Beban Komisi Penjualan',        'expense',   'debit',  '6300',3,true],
            ['6303', 'Beban Entertainment Klien',     'expense',   'debit',  '6300',3,true],
            ['6304', 'Beban Pameran & Event',         'expense',   'debit',  '6300',3,true],

            // Beban Teknologi
            ['6400', 'Beban Teknologi & IT',          'expense',   'debit',  '6',  2, false],
            ['6401', 'Beban Lisensi Software',        'expense',   'debit',  '6400',3,true],
            ['6402', 'Beban Cloud & Hosting',         'expense',   'debit',  '6400',3,true],
            ['6403', 'Beban Domain & SSL',            'expense',   'debit',  '6400',3,true],

            // ══════════════════════════════════════════════
            // 7. PENDAPATAN LAIN-LAIN
            // ══════════════════════════════════════════════
            ['7',    'PENDAPATAN LAIN-LAIN',          'other_revenue','credit',null,1,false],
            ['7100', 'Pendapatan Bunga Bank',         'other_revenue','credit','7', 2,true],
            ['7200', 'Keuntungan Selisih Kurs',       'other_revenue','credit','7', 2,true],
            ['7300', 'Pendapatan Lain-lain',          'other_revenue','credit','7', 2,true],

            // ══════════════════════════════════════════════
            // 8. BEBAN LAIN-LAIN
            // ══════════════════════════════════════════════
            ['8',    'BEBAN LAIN-LAIN',               'other_expense','debit',null, 1,false],
            ['8100', 'Beban Bunga Pinjaman',          'other_expense','debit','8',  2,true],
            ['8200', 'Kerugian Selisih Kurs',         'other_expense','debit','8',  2,true],
            ['8300', 'Beban Pajak Badan (PPh 25/29)', 'other_expense','debit','8',  2,true],
            ['8400', 'Beban Lain-lain',               'other_expense','debit','8',  2,true],
        ];

        foreach ($accounts as $acc) {
            [$code, $name, $type, $normal, $parentCode, $level, $isDetail] = $acc;

            $id = Str::uuid()->toString();
            $this->coaMap[$code] = $id;

            DB::table('chart_of_accounts')->insertOrIgnore([
                'id'             => $id,
                'tenant_id'      => $this->tenantId,
                'code'           => $code,
                'name'           => $name,
                'account_type'   => $type,
                'normal_balance' => $normal,
                'parent_id'      => $parentCode ? ($this->coaMap[$parentCode] ?? null) : null,
                'level'          => $level,
                'is_detail'      => $isDetail,
                'is_cash_account'=> in_array($code, ['1111', '1112', '1113', '1114']),
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // Seed bank accounts (linked ke COA)
        $this->seedBankAccounts($now);

        // Seed document sequences
        // $this->seedDocumentSequences($now);

        // Seed default settings
        $this->seedSettings($now);

        $this->command->info('✓ ' . count($accounts) . ' COA accounts seeded.');
    }

    private function seedBankAccounts($now): void
    {
        $bankAccounts = [
            [
                'id'              => Str::uuid(),
                'coa_code'        => '1112',
                'bank_name'       => 'BCA',
                'account_number'  => '1234567890',
                'account_name'    => 'PT Maju Jaya Bersama',
                'account_type'    => 'giro',
                'current_balance' => 250000000,
                'is_default'      => true,
            ],
            [
                'id'              => Str::uuid(),
                'coa_code'        => '1113',
                'bank_name'       => 'Mandiri',
                'account_number'  => '9876543210',
                'account_name'    => 'PT Maju Jaya Bersama',
                'account_type'    => 'giro',
                'current_balance' => 80000000,
                'is_default'      => false,
            ],
            [
                'id'              => Str::uuid(),
                'coa_code'        => '1111',
                'bank_name'       => 'Kas Kecil',
                'account_number'  => 'KAS-001',
                'account_name'    => 'Kas Kecil Kantor Jakarta',
                'account_type'    => 'petty_cash',
                'current_balance' => 5000000,
                'is_default'      => false,
            ],
        ];

        foreach ($bankAccounts as $ba) {
            $coaId = DB::table('chart_of_accounts')
                ->where('tenant_id', $this->tenantId)
                ->where('code', $ba['coa_code'])
                ->value('id');

            DB::table('bank_accounts')->insertOrIgnore([
                'id'              => $ba['id'],
                'tenant_id'       => $this->tenantId,
                'coa_id'          => $coaId,
                'bank_name'       => $ba['bank_name'],
                'account_number'  => $ba['account_number'],
                'account_name'    => $ba['account_name'],
                'account_type'    => $ba['account_type'],
                'currency'        => 'IDR',
                'current_balance' => $ba['current_balance'],
                'is_active'       => true,
                'is_default'      => $ba['is_default'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    // private function seedDocumentSequences($now): void
    // {
    //     $prefixes = ['INV', 'QUO', 'LDS', 'CLI', 'PRJ', 'PAY', 'JNL', 'BILL', 'PAYROLL'];
    //     foreach ($prefixes as $prefix) {
    //         DB::table('document_sequences')->insertOrIgnore([
    //             'id'            => Str::uuid(),
    //             'tenant_id'     => $this->tenantId,
    //             'prefix'        => $prefix,
    //             'year'          => 2024,
    //             'last_sequence' => 0,
    //             'created_at'    => $now,
    //             'updated_at'    => $now,
    //         ]);
    //     }
    // }

    private function seedSettings($now): void
    {
        $settings = [
            // Format nomor dokumen
            ['key' => 'invoice.number_format',    'value' => 'INV-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'quotation.number_format',  'value' => 'QUO-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'lead.number_format',       'value' => 'LDS-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'client.number_format',     'value' => 'CLI-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'project.number_format',    'value' => 'PRJ-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'payment.number_format',    'value' => 'PAY-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'journal.number_format',    'value' => 'JNL-{YYYY}-{SEQ4}',    'type' => 'string'],
            ['key' => 'bill.number_format',       'value' => 'BILL-{YYYY}-{SEQ4}',   'type' => 'string'],

            // Finance
            ['key' => 'finance.ppn_rate',                 'value' => '11',            'type' => 'decimal'],
            ['key' => 'finance.pph23_rate',               'value' => '2',             'type' => 'decimal'],
            ['key' => 'finance.default_payment_terms',    'value' => '30',            'type' => 'integer'],
            ['key' => 'finance.coa_ar',                   'value' => '1201',          'type' => 'string'],
            ['key' => 'finance.coa_ap',                   'value' => '2101',          'type' => 'string'],
            ['key' => 'finance.coa_ppn_out',              'value' => '2203',          'type' => 'string'],
            ['key' => 'finance.coa_ppn_in',               'value' => '1403',          'type' => 'string'],
            ['key' => 'finance.coa_salary_expense',       'value' => '6101',          'type' => 'string'],

            // HRD
            ['key' => 'hrd.pph21_method',                'value' => 'gross_up',      'type' => 'string'],
            ['key' => 'hrd.bpjs_kes_employee_rate',      'value' => '1',             'type' => 'decimal'],
            ['key' => 'hrd.bpjs_kes_employer_rate',      'value' => '4',             'type' => 'decimal'],
            ['key' => 'hrd.bpjs_tk_jht_employee_rate',   'value' => '2',             'type' => 'decimal'],
            ['key' => 'hrd.bpjs_tk_jht_employer_rate',   'value' => '3.7',           'type' => 'decimal'],
            ['key' => 'hrd.bpjs_tk_jp_employee_rate',    'value' => '1',             'type' => 'decimal'],
            ['key' => 'hrd.bpjs_tk_jp_employer_rate',    'value' => '2',             'type' => 'decimal'],
            ['key' => 'hrd.late_deduction_per_minute',   'value' => '5000',          'type' => 'integer'],
            ['key' => 'hrd.absent_deduction_formula',    'value' => 'base_salary/working_days', 'type' => 'string'],
            ['key' => 'hrd.payroll_cutoff_day',          'value' => '25',            'type' => 'integer'],

            // Invoice reminder
            ['key' => 'invoice.reminder_days_before',    'value' => '3',             'type' => 'integer'],
            ['key' => 'invoice.reminder_days_after',     'value' => '3',             'type' => 'integer'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->insertOrIgnore([
                'id'         => Str::uuid(),
                'tenant_id'  => $this->tenantId,
                'key'        => $s['key'],
                'value'      => $s['value'],
                'type'       => $s['type'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
