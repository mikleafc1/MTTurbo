<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * ثبت سند حسابداری خودکار
 */
class AccountingService
{
    /**
     * ثبت سند از خطوط: [['account_id'=>, 'debit'=>, 'credit'=>, 'description'=>], ...]
     */
    public static function postVoucher(string $date, string $description, array $lines, ?string $source = 'manual', ?string $refType = null, ?int $refId = null, ?int $userId = null): int
    {
        $totalDebit = 0; $totalCredit = 0;
        foreach ($lines as $l) {
            $totalDebit += (float) ($l['debit'] ?? 0);
            $totalCredit += (float) ($l['credit'] ?? 0);
        }
        if (count($lines) < 2 || abs($totalDebit - $totalCredit) > 0.01 || $totalDebit == 0) {
            return 0; // سند تراز نیست
        }

        $voucherNo = InventoryService::nextNo('voucher_prefix', 'voucher_counter');
        $voucherId = Database::insert('vouchers', [
            'voucher_no' => $voucherNo,
            'voucher_date' => $date,
            'description' => $description,
            'status' => 'posted',
            'source' => $source,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by' => $userId,
        ]);

        foreach ($lines as $l) {
            Database::insert('voucher_lines', [
                'voucher_id' => $voucherId,
                'account_id' => (int) $l['account_id'],
                'debit' => round((float) ($l['debit'] ?? 0), 2),
                'credit' => round((float) ($l['credit'] ?? 0), 2),
                'description' => mb_substr((string) ($l['description'] ?? $description), 0, 190),
            ]);
        }
        return $voucherId;
    }

    /** سند فروش: بدهکار دریافتنی / بستانکار فروش + مالیات (+ تخفیف) */
    public static function voucherForInvoice(array $invoice): int
    {
        $ar = (int) setting('account_ar', 5);
        $sales = (int) setting('account_sales', 17);
        $vat = (int) setting('account_vat', 11);
        $disc = (int) setting('account_discount', 24);
        $shippingAcc = (int) Database::val("SELECT id FROM accounts WHERE code = '5102'") ?: 21;

        if ($invoice['invoice_type'] === 'purchase') {
            $ap = (int) setting('account_ap', 10);
            $inv = (int) setting('account_inventory', 6);
            $lines = [
                ['account_id' => $inv, 'debit' => (float) $invoice['subtotal'], 'credit' => 0, 'description' => 'خرید کالا'],
            ];
            if ((float) $invoice['tax_amount'] > 0) {
                $lines[] = ['account_id' => $vat, 'debit' => (float) $invoice['tax_amount'], 'credit' => 0, 'description' => 'مالیات خرید'];
            }
            if ((float) $invoice['shipping'] > 0) {
                $lines[] = ['account_id' => $shippingAcc, 'debit' => (float) $invoice['shipping'], 'credit' => 0, 'description' => 'هزینه حمل'];
            }
            $lines[] = ['account_id' => $ap, 'debit' => 0, 'credit' => (float) $invoice['total'], 'description' => 'بستانکار تأمین‌کننده'];
            return self::postVoucher($invoice['invoice_date'], 'فاکتور خرید ' . $invoice['invoice_no'], $lines, 'auto_purchase', 'invoice', (int) $invoice['id']);
        }

        $net = (float) $invoice['subtotal'] - (float) $invoice['discount'];
        $lines = [
            ['account_id' => $ar, 'debit' => (float) $invoice['total'], 'credit' => 0, 'description' => 'طلب از مشتری'],
        ];
        if ((float) $invoice['discount'] > 0) {
            $lines[] = ['account_id' => $disc, 'debit' => (float) $invoice['discount'], 'credit' => 0, 'description' => 'تخفیف فروش'];
        }
        $lines[] = ['account_id' => $sales, 'debit' => 0, 'credit' => $net, 'description' => 'فروش'];
        if ((float) $invoice['tax_amount'] > 0) {
            $lines[] = ['account_id' => $vat, 'debit' => 0, 'credit' => (float) $invoice['tax_amount'], 'description' => 'مالیات بر ارزش افزوده'];
        }
        return self::postVoucher($invoice['invoice_date'], 'فاکتور فروش ' . $invoice['invoice_no'], $lines, 'auto_sale', 'invoice', (int) $invoice['id']);
    }

    /** بهای تمام‌شده فروش (COGS) */
    public static function voucherForCogs(array $invoice, float $cogs): int
    {
        if ($cogs <= 0) return 0;
        return self::postVoucher(
            $invoice['invoice_date'],
            'بهای تمام‌شده فاکتور ' . $invoice['invoice_no'],
            [
                ['account_id' => (int) setting('account_cogs', 20), 'debit' => $cogs, 'credit' => 0, 'description' => 'بهای تمام‌شده'],
                ['account_id' => (int) setting('account_inventory', 6), 'debit' => 0, 'credit' => $cogs, 'description' => 'کاهش موجودی'],
            ],
            'auto_cogs', 'invoice', (int) $invoice['id']
        );
    }

    /** سند دریافت/پرداخت */
    public static function voucherForTransaction(array $trx, string $partyName = ''): int
    {
        $cashAcc = (int) setting('account_cash', 3);
        $bankAcc = (int) setting('account_bank', 4);
        $ar = (int) setting('account_ar', 5);
        $ap = (int) setting('account_ap', 10);

        $cashbox = Database::one('SELECT * FROM cashboxes WHERE id = ?', [$trx['cashbox_id']]);
        $acc = (($cashbox['type'] ?? 'cash') === 'bank') ? $bankAcc : $cashAcc;

        if ($trx['type'] === 'receipt') {
            $lines = [
                ['account_id' => $acc, 'debit' => (float) $trx['amount'], 'credit' => 0, 'description' => 'دریافت وجه'],
                ['account_id' => $ar, 'debit' => 0, 'credit' => (float) $trx['amount'], 'description' => 'کاهش مطالبات ' . $partyName],
            ];
        } else {
            $lines = [
                ['account_id' => $ap, 'debit' => (float) $trx['amount'], 'credit' => 0, 'description' => 'پرداخت بدهی'],
                ['account_id' => $acc, 'debit' => 0, 'credit' => (float) $trx['amount'], 'description' => 'خروج وجه'],
            ];
        }
        return self::postVoucher($trx['trx_date'], 'سند ' . ($trx['type'] === 'receipt' ? 'دریافت' : 'پرداخت') . ' ' . $trx['trx_no'], $lines, 'auto_treasury', 'transaction', (int) $trx['id']);
    }
}
