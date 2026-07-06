<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Buat invoice baru dan generate jurnal otomatis.
     * Dr. Piutang Usaha  (1201)
     * Cr. Pendapatan      (4xxx) per item
     * Cr. PPN Keluaran   (2203)
     */
    public function createInvoice(array $data, User $creator): Invoice
    {
        return DB::transaction(function () use ($data, $creator) {
            $client = Client::find($data['client_id']);

            // ── Hitung total dari items ────────────────────────
            $subtotal  = 0;
            $taxAmount = 0;
            $discAmount= 0;
            $itemsData = [];

            foreach ($data['items'] as $i => $item) {
                $qty      = (float) $item['quantity'];
                $price    = (float) $item['unit_price'];
                $discPct  = (float) ($item['discount_percent'] ?? 0);
                $taxPct   = (float) ($item['tax_percent'] ?? 11);

                $itemSubtotal  = $qty * $price;
                $itemDisc      = round($itemSubtotal * $discPct / 100, 2);
                $itemTaxBase   = $itemSubtotal - $itemDisc;
                $itemTax       = round($itemTaxBase * $taxPct / 100, 2);
                $itemTotal     = $itemTaxBase + $itemTax;

                $subtotal  += $itemSubtotal;
                $discAmount+= $itemDisc;
                $taxAmount += $itemTax;

                $itemsData[] = [
                    'sequence'         => $i + 1,
                    'description'      => $item['description'],
                    'coa_id'           => $item['coa_id'] ?? null,
                    'quantity'         => $qty,
                    'unit'             => $item['unit'] ?? null,
                    'unit_price'       => $price,
                    'discount_percent' => $discPct,
                    'tax_percent'      => $taxPct,
                    'subtotal'         => $itemSubtotal,
                    'discount_amount'  => $itemDisc,
                    'tax_amount'       => $itemTax,
                    'total'            => $itemTotal,
                ];
            }

            $taxBase    = $subtotal - $discAmount;
            $totalAmount= $taxBase + $taxAmount;

            $invoice = Invoice::create([
                'tenant_id'               => $creator->tenant_id,
                'type'                    => 'invoice',
                'quotation_id'            => $data['quotation_id'] ?? null,
                'client_id'               => $client->id,
                'client_name_snapshot'    => $client->name,
                'client_address_snapshot' => $client->address,
                'client_npwp_snapshot'    => $client->npwp,
                'invoice_date'            => $data['invoice_date'],
                'due_date'                => $data['due_date'],
                'currency'                => $data['currency'] ?? 'IDR',
                'subtotal'                => $subtotal,
                'discount_amount'         => $discAmount,
                'tax_base'                => $taxBase,
                'ppn_amount'              => $taxAmount,
                'pph_amount'              => 0,
                'total_amount'            => $totalAmount,
                'paid_amount'             => 0,
                'remaining_amount'        => $totalAmount,
                'status'                  => Invoice::STATUS_DRAFT,
                'notes'                   => $data['notes'] ?? null,
                'internal_notes'          => $data['internal_notes'] ?? null,
                'terms_conditions'        => $data['terms_conditions'] ?? null,
                'created_by'              => $creator->id,
            ]);

            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // ── Buat Jurnal Otomatis ───────────────────────────
            $this->createInvoiceJournal($invoice, $itemsData, $creator);

            return $invoice;
        });
    }

    private function createInvoiceJournal(Invoice $invoice, array $items, User $creator): void
    {
        $tenantId = $invoice->tenant_id;

        $coaAr     = $this->getCoaByCode($tenantId, '1201'); // Piutang Usaha
        $coaPpnOut = $this->getCoaByCode($tenantId, '2203'); // PPN Keluaran

        if (! $coaAr) return;

        $journal = JournalEntry::create([
            'tenant_id'      => $tenantId,
            'type'           => 'invoice',
            'reference_type' => 'Invoice',
            'reference_id'   => $invoice->id,
            'description'    => "Invoice {$invoice->invoice_number} — {$invoice->client_name_snapshot}",
            'entry_date'     => $invoice->invoice_date,
            'period_year'    => \Carbon\Carbon::parse($invoice->invoice_date)->year,
            'period_month'   => \Carbon\Carbon::parse($invoice->invoice_date)->month,
            'total_debit'    => $invoice->total_amount,
            'total_credit'   => $invoice->total_amount,
            'created_by'     => $creator->id,
        ]);

        // Dr. Piutang Usaha = total_amount
        $journal->lines()->create([
            'coa_id'        => $coaAr->id,
            'description'   => "Piutang — {$invoice->invoice_number}",
            'debit_amount'  => $invoice->total_amount,
            'credit_amount' => 0,
            'sequence'      => 1,
        ]);

        $seq = 2;
        // Cr. Pendapatan per item (sesuai COA item)
        $revenueByCoaGroup = collect($items)->groupBy('coa_id');
        foreach ($revenueByCoaGroup as $coaId => $groupItems) {
            $totalRevenue = $groupItems->sum('subtotal') - $groupItems->sum('discount_amount');
            $revCoa  = $coaId ? ChartOfAccount::find($coaId) : $this->getCoaByCode($tenantId, '4101');

            if ($revCoa) {
                $journal->lines()->create([
                    'coa_id'        => $revCoa->id,
                    'description'   => "Pendapatan — {$invoice->invoice_number}",
                    'debit_amount'  => 0,
                    'credit_amount' => $totalRevenue,
                    'sequence'      => $seq++,
                ]);
            }
        }

        // Cr. PPN Keluaran
        if ($invoice->ppn_amount > 0 && $coaPpnOut) {
            $journal->lines()->create([
                'coa_id'        => $coaPpnOut->id,
                'description'   => "PPN 11% — {$invoice->invoice_number}",
                'debit_amount'  => 0,
                'credit_amount' => $invoice->ppn_amount,
                'sequence'      => $seq,
            ]);
        }

        $journal->post($creator);
    }

    /**
     * Buat Credit Note untuk invoice.
     */
    public function createCreditNote(Invoice $invoice, array $data, User $creator): Invoice
    {
        $amount = $data['amount'] ?? $invoice->total_amount;

        $creditNote = Invoice::create([
            'tenant_id'            => $invoice->tenant_id,
            'type'                 => 'credit_note',
            'parent_invoice_id'    => $invoice->id,
            'client_id'            => $invoice->client_id,
            'client_name_snapshot' => $invoice->client_name_snapshot,
            'invoice_date'         => now()->toDateString(),
            'due_date'             => now()->toDateString(),
            'currency'             => $invoice->currency,
            'subtotal'             => $amount,
            'discount_amount'      => 0,
            'tax_base'             => $amount,
            'ppn_amount'           => 0,
            'pph_amount'           => 0,
            'total_amount'         => $amount,
            'paid_amount'          => $amount,
            'remaining_amount'     => 0,
            'status'               => Invoice::STATUS_PAID,
            'notes'                => $data['reason'] ?? 'Credit Note',
            'created_by'           => $creator->id,
        ]);

        return $creditNote;
    }

    private function getCoaByCode(string $tenantId, string $code): ?ChartOfAccount
    {
        return ChartOfAccount::where('tenant_id', $tenantId)->where('code', $code)->first();
    }
}
