<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/finance/reports/profit-loss",
     *   tags={"Finance"},
     *   summary="Laporan Laba Rugi (Income Statement)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="year",  in="query", required=true, @OA\Schema(type="integer", example=2024)),
     *   @OA\Parameter(name="month", in="query", description="Kosongkan untuk laporan tahunan", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="compare_previous", in="query", description="Bandingkan dengan periode sebelumnya", @OA\Schema(type="boolean")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="period",    type="string"),
     *         @OA\Property(property="revenue",   type="object"),
     *         @OA\Property(property="cogs",      type="object"),
     *         @OA\Property(property="gross_profit", type="number"),
     *         @OA\Property(property="expenses",  type="object"),
     *         @OA\Property(property="operating_income", type="number"),
     *         @OA\Property(property="other",     type="object"),
     *         @OA\Property(property="net_income", type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate([
            'year'  => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $year     = (int) $request->year;
        $month    = $request->month ? (int) $request->month : null;

        $data    = $this->buildProfitLoss($tenantId, $year, $month);
        $compare = null;

        if ($request->compare_previous) {
            if ($month) {
                $prevMonth = $month === 1 ? 12 : $month - 1;
                $prevYear  = $month === 1 ? $year - 1 : $year;
                $compare   = $this->buildProfitLoss($tenantId, $prevYear, $prevMonth);
            } else {
                $compare = $this->buildProfitLoss($tenantId, $year - 1, null);
            }
        }

        return $this->ok(array_filter([
            'current'  => $data,
            'previous' => $compare,
        ]));
    }

    /**
     * @OA\Get(
     *   path="/finance/reports/balance-sheet",
     *   tags={"Finance"},
     *   summary="Neraca Saldo (Balance Sheet)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="as_of_date", in="query", required=true, description="Tanggal posisi neraca", @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="assets",      type="object"),
     *         @OA\Property(property="liabilities", type="object"),
     *         @OA\Property(property="equity",      type="object"),
     *         @OA\Property(property="is_balanced", type="boolean")
     *       )
     *     )
     *   )
     * )
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate(['as_of_date' => ['required', 'date']]);

        $tenantId = $request->user()->tenant_id;
        $asOf     = Carbon::parse($request->as_of_date);

        $balances = $this->getAccountBalances($tenantId, $asOf);

        $assets      = $this->groupByType($balances, ['asset']);
        $liabilities = $this->groupByType($balances, ['liability']);
        $equity      = $this->groupByType($balances, ['equity']);

        // Tambahkan laba tahun berjalan ke ekuitas
        $currentYearIncome = $this->getNetIncome($tenantId, $asOf->year, null, $asOf);
        $equity['retained_earnings_ytd'] = $currentYearIncome;
        $equity['total'] = ($equity['total'] ?? 0) + $currentYearIncome;

        $totalAssets = $assets['total'] ?? 0;
        $totalLiabEq = ($liabilities['total'] ?? 0) + ($equity['total'] ?? 0);

        return $this->ok([
            'as_of_date'      => $asOf->toDateString(),
            'assets'          => $assets,
            'liabilities'     => $liabilities,
            'equity'          => $equity,
            'total_assets'    => $totalAssets,
            'total_liab_eq'   => $totalLiabEq,
            'is_balanced'     => abs($totalAssets - $totalLiabEq) < 1,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/finance/reports/trial-balance",
     *   tags={"Finance"},
     *   summary="Trial Balance",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="year",  in="query", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="month", in="query", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $request->validate([
            'year'  => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $tenantId  = $request->user()->tenant_id;
        $year      = (int) $request->year;
        $month     = (int) $request->month;
        $endOfMonth = Carbon::create($year, $month)->endOfMonth();

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_detail', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $rows         = [];
        $totalDebit   = 0;
        $totalCredit  = 0;

        foreach ($accounts as $account) {
            $balance = $account->getBalance($endOfMonth);

            if ($balance == 0) continue;

            $debit  = $account->normal_balance === 'debit'  ? abs($balance) : 0;
            $credit = $account->normal_balance === 'credit' ? abs($balance) : 0;

            $rows[]      = [
                'code'     => $account->code,
                'name'     => $account->name,
                'type'     => $account->account_type,
                'debit'    => $debit,
                'credit'   => $credit,
            ];

            $totalDebit  += $debit;
            $totalCredit += $credit;
        }

        return $this->ok([
            'period'       => Carbon::create($year, $month)->format('F Y'),
            'accounts'     => $rows,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced'  => abs($totalDebit - $totalCredit) < 1,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/finance/reports/ar-aging",
     *   tags={"Finance"},
     *   summary="AR Aging Report (Analisis Umur Piutang)",
     *   description="Mengelompokkan piutang berdasarkan umur keterlambatan pembayaran.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="as_of_date", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="summary", type="object",
     *           @OA\Property(property="current",   type="number", description="Belum jatuh tempo"),
     *           @OA\Property(property="days_1_30", type="number", description="1-30 hari lewat"),
     *           @OA\Property(property="days_31_60",type="number"),
     *           @OA\Property(property="days_61_90",type="number"),
     *           @OA\Property(property="over_90",   type="number"),
     *           @OA\Property(property="total",     type="number")
     *         ),
     *         @OA\Property(property="clients", type="array", @OA\Items(type="object"))
     *       )
     *     )
     *   )
     * )
     */
    public function arAging(Request $request): JsonResponse
    {
        $asOf = $request->as_of_date ? Carbon::parse($request->as_of_date) : now();

        $invoices = Invoice::with('client')
            ->whereNotIn('status', ['paid', 'cancelled', 'void', 'draft'])
            ->where('remaining_amount', '>', 0)
            ->get();

        $summary = [
            'current'    => 0,
            'days_1_30'  => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'over_90'    => 0,
            'total'      => 0,
        ];

        $byClient = [];

        foreach ($invoices as $inv) {
            $daysLate = (int) Carbon::parse($inv->due_date)->diffInDays($asOf, false);
            $amount   = (float) $inv->remaining_amount;
            $key      = $inv->client_name_snapshot;

            if (! isset($byClient[$key])) {
                $byClient[$key] = ['client' => $key, 'current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'over_90' => 0, 'total' => 0];
            }

            if ($daysLate <= 0) {
                $bucket = 'current';
            } elseif ($daysLate <= 30) {
                $bucket = 'days_1_30';
            } elseif ($daysLate <= 60) {
                $bucket = 'days_31_60';
            } elseif ($daysLate <= 90) {
                $bucket = 'days_61_90';
            } else {
                $bucket = 'over_90';
            }

            $summary[$bucket]      += $amount;
            $summary['total']      += $amount;
            $byClient[$key][$bucket] += $amount;
            $byClient[$key]['total'] += $amount;
        }

        return $this->ok([
            'as_of_date' => $asOf->toDateString(),
            'summary'    => $summary,
            'clients'    => array_values($byClient),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/finance/reports/ap-aging",
     *   tags={"Finance"},
     *   summary="AP Aging Report (Analisis Umur Hutang Vendor)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="as_of_date", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function apAging(Request $request): JsonResponse
    {
        $asOf = $request->as_of_date ? Carbon::parse($request->as_of_date) : now();

        $bills = Bill::whereNotIn('status', ['paid', 'cancelled'])->get();

        $summary  = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'over_90' => 0, 'total' => 0];
        $byVendor = [];

        foreach ($bills as $bill) {
            $daysLate = (int) Carbon::parse($bill->due_date)->diffInDays($asOf, false);
            $amount   = (float) ($bill->amount - $bill->paid_amount);
            $key      = $bill->vendor_name;

            if (! isset($byVendor[$key])) {
                $byVendor[$key] = ['vendor' => $key, 'current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'over_90' => 0, 'total' => 0];
            }

            $bucket = match (true) {
                $daysLate <= 0  => 'current',
                $daysLate <= 30 => 'days_1_30',
                $daysLate <= 60 => 'days_31_60',
                $daysLate <= 90 => 'days_61_90',
                default         => 'over_90',
            };

            $summary[$bucket]        += $amount;
            $summary['total']        += $amount;
            $byVendor[$key][$bucket] += $amount;
            $byVendor[$key]['total'] += $amount;
        }

        return $this->ok([
            'as_of_date' => $asOf->toDateString(),
            'summary'    => $summary,
            'vendors'    => array_values($byVendor),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/finance/reports/general-ledger",
     *   tags={"Finance"},
     *   summary="General Ledger per akun",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="coa_id",    in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",   in="query", required=true, @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="account",         type="object"),
     *         @OA\Property(property="opening_balance", type="number"),
     *         @OA\Property(property="transactions",    type="array", @OA\Items(type="object")),
     *         @OA\Property(property="closing_balance", type="number"),
     *         @OA\Property(property="total_debit",     type="number"),
     *         @OA\Property(property="total_credit",    type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function generalLedger(Request $request): JsonResponse
    {
        $request->validate([
            'coa_id'    => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $coa      = ChartOfAccount::find($request->coa_id);
        $dateFrom = Carbon::parse($request->date_from);
        $dateTo   = Carbon::parse($request->date_to);

        // Saldo awal (sebelum date_from)
        $openingBalance = $coa->getBalance($dateFrom->copy()->subDay());

        // Transaksi dalam periode
        $lines = JournalLine::with('journalEntry')
            ->where('coa_id', $request->coa_id)
            ->whereHas('journalEntry', fn($q) =>
                $q->where('is_posted', true)
                  ->whereBetween('entry_date', [$dateFrom, $dateTo])
            )
            ->orderBy(fn($q) =>
                $q->select('entry_date')->from('journal_entries')->whereColumn('journal_entries.id', 'journal_lines.journal_entry_id')
            )
            ->get();

        $runningBalance = $openingBalance;
        $totalDebit     = 0;
        $totalCredit    = 0;

        $transactions = $lines->map(function ($line) use (&$runningBalance, &$totalDebit, &$totalCredit, $coa) {
            $totalDebit  += $line->debit_amount;
            $totalCredit += $line->credit_amount;

            $movement = $coa->normal_balance === 'debit'
                ? $line->debit_amount - $line->credit_amount
                : $line->credit_amount - $line->debit_amount;

            $runningBalance += $movement;

            return [
                'date'            => $line->journalEntry->entry_date,
                'journal_number'  => $line->journalEntry->entry_number,
                'description'     => $line->description ?? $line->journalEntry->description,
                'debit'           => (float) $line->debit_amount,
                'credit'          => (float) $line->credit_amount,
                'running_balance' => $runningBalance,
            ];
        });

        return $this->ok([
            'account'         => ['code' => $coa->code, 'name' => $coa->name, 'type' => $coa->account_type],
            'period'          => $dateFrom->format('d M Y') . ' — ' . $dateTo->format('d M Y'),
            'opening_balance' => $openingBalance,
            'transactions'    => $transactions,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'closing_balance' => $runningBalance,
        ]);
    }

    // Export wrappers
    public function exportProfitLoss(Request $request): JsonResponse
    {
        // Dispatch export job
        return $this->ok(['job_id' => \Illuminate\Support\Str::uuid(), 'status' => 'queued']);
    }

    public function exportBalanceSheet(Request $request): JsonResponse
    {
        return $this->ok(['job_id' => \Illuminate\Support\Str::uuid(), 'status' => 'queued']);
    }

    // ── Private Helpers ────────────────────────────────────

    private function buildProfitLoss(string $tenantId, int $year, ?int $month): array
    {
        $monthLabel = $month ? Carbon::create($year, $month)->format('F Y') : "Tahun {$year}";

        $revenue  = $this->sumAccountType($tenantId, 'revenue',       $year, $month);
        $cogs     = $this->sumAccountType($tenantId, 'cogs',          $year, $month);
        $expense  = $this->sumAccountType($tenantId, 'expense',       $year, $month);
        $otherRev = $this->sumAccountType($tenantId, 'other_revenue', $year, $month);
        $otherExp = $this->sumAccountType($tenantId, 'other_expense', $year, $month);

        $grossProfit     = $revenue['total'] - $cogs['total'];
        $operatingIncome = $grossProfit - $expense['total'];
        $netIncome       = $operatingIncome + $otherRev['total'] - $otherExp['total'];

        return [
            'period'           => $monthLabel,
            'revenue'          => $revenue,
            'cogs'             => $cogs,
            'gross_profit'     => $grossProfit,
            'gross_margin_pct' => $revenue['total'] > 0 ? round($grossProfit / $revenue['total'] * 100, 1) : 0,
            'expenses'         => $expense,
            'operating_income' => $operatingIncome,
            'other_revenue'    => $otherRev,
            'other_expenses'   => $otherExp,
            'net_income'       => $netIncome,
            'net_margin_pct'   => $revenue['total'] > 0 ? round($netIncome / $revenue['total'] * 100, 1) : 0,
        ];
    }

    private function sumAccountType(string $tenantId, string $type, int $year, ?int $month): array
    {
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('account_type', $type)
            ->where('is_detail', true)
            ->get();

        $items = [];
        $total = 0;

        foreach ($accounts as $acc) {
            $query = JournalLine::where('coa_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($year, $month) {
                    $q->where('is_posted', true)->where('period_year', $year);
                    if ($month) $q->where('period_month', $month);
                });

            $debit  = (float) $query->sum('debit_amount');
            $credit = (float) (clone $query)->sum('credit_amount');
            $balance = $acc->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

            if (abs($balance) > 0) {
                $items[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $balance];
                $total  += $balance;
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    private function getAccountBalances(string $tenantId, Carbon $asOf): \Illuminate\Support\Collection
    {
        return ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_detail', true)
            ->get()
            ->map(fn($acc) => [
                'id'      => $acc->id,
                'code'    => $acc->code,
                'name'    => $acc->name,
                'type'    => $acc->account_type,
                'normal'  => $acc->normal_balance,
                'balance' => $acc->getBalance($asOf),
            ])
            ->filter(fn($a) => abs($a['balance']) > 0);
    }

    private function groupByType(\Illuminate\Support\Collection $balances, array $types): array
    {
        $filtered = $balances->filter(fn($a) => in_array($a['type'], $types));
        return [
            'items' => $filtered->values()->toArray(),
            'total' => $filtered->sum('balance'),
        ];
    }

    private function getNetIncome(string $tenantId, int $year, ?int $month, Carbon $asOf): float
    {
        $data = $this->buildProfitLoss($tenantId, $year, $month);
        return $data['net_income'];
    }
}
