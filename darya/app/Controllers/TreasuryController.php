<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AccountingService;
use App\Services\InventoryService;
use App\Services\SyncService;

/** خزانه: صندوق/بانک، دریافت و پرداخت، چک‌ها */
class TreasuryController extends Controller
{
    /** برچسب انواع صندوق */
    public const BOX_TYPES = ['cash' => 'صندوق نقدی', 'bank' => 'حساب بانکی'];

    /** برچسب روش‌های پرداخت */
    public const METHODS = ['cash' => 'نقدی', 'transfer' => 'انتقال بانکی', 'cheque' => 'چک', 'card' => 'کارت‌خوان', 'online' => 'آنلاین'];

    /* ==================== صندوق و بانک ==================== */

    public function cashboxesAction(): void
    {
        $this->requirePerm('treasury.view');
        $rows = Database::all('SELECT * FROM cashboxes ORDER BY id ASC');
        $this->view('treasury/cashboxes', [
            'title'    => 'صندوق و بانک',
            'rows'     => $rows,
            'boxTypes' => self::BOX_TYPES,
        ]);
    }

    public function cashboxFormAction(array $args): void
    {
        $this->requirePerm('treasury.view');
        $id = (int) ($args[0] ?? 0);
        $row = null;
        if ($id) {
            $row = Database::one('SELECT * FROM cashboxes WHERE id = ?', [$id]);
            if (!$row) $this->notFound();
        }
        $this->view('treasury/cashbox-form', [
            'title'    => $row ? 'ویرایش ' . $row['name'] : 'صندوق / حساب بانکی جدید',
            'row'      => $row,
            'boxTypes' => self::BOX_TYPES,
        ]);
    }

    public function cashboxStoreAction(array $args): void
    {
        $this->requirePerm('treasury.manage');
        $id = (int) ($args[0] ?? 0);
        $name = (string) $this->input('name', '');
        $type = (string) $this->input('type', 'cash') === 'bank' ? 'bank' : 'cash';
        if ($name === '') {
            flash_set('error', 'نام صندوق/حساب الزامی است.');
            $this->redirect($id ? 'treasury/cashbox-form/' . $id : 'treasury/cashbox-form');
        }
        $data = [
            'name'           => $name,
            'type'           => $type,
            'bank_name'      => $this->input('bank_name') ?: null,
            'account_number' => $this->input('account_number') ?: null,
            'iban'           => $this->input('iban') ?: null,
            'card_number'    => $this->input('card_number') ?: null,
            'is_active'      => (int) ($this->input('is_active', '') === '1'),
        ];
        if ($id) {
            Database::update('cashboxes', $data, 'id = ?', [$id]);
            flash_set('success', 'اطلاعات بروزرسانی شد.');
        } else {
            Database::insert('cashboxes', $data);
            flash_set('success', 'صندوق/حساب جدید ثبت شد.');
        }
        $this->redirect('treasury/cashboxes');
    }

    public function cashboxDeleteAction(array $args): void
    {
        $this->requirePerm('treasury.manage');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM cashboxes WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        $hasTrx = (bool) Database::val('SELECT COUNT(*) FROM transactions WHERE cashbox_id = ?', [$id]);
        if ($hasTrx) {
            flash_set('error', 'این صندوق تراکنش دارد و قابل حذف نیست.');
        } else {
            Database::delete('cashboxes', 'id = ?', [$id]);
            flash_set('success', 'صندوق/حساب حذف شد.');
        }
        $this->redirect('treasury/cashboxes');
    }

    /* ==================== دریافت و پرداخت ==================== */

    public function transactionsAction(): void
    {
        $this->requirePerm('treasury.view');

        $type = (string) $this->input('type', '');
        $cashboxId = (int) $this->input('cashbox_id', 0);
        $from = jinput_to_gregorian((string) $this->input('date_from', ''));
        $to = jinput_to_gregorian((string) $this->input('date_to', ''));

        $where = '1=1';
        $params = [];
        if (in_array($type, ['receipt', 'payment'], true)) { $where .= ' AND t.type = ?'; $params[] = $type; }
        if ($cashboxId) { $where .= ' AND t.cashbox_id = ?'; $params[] = $cashboxId; }
        if ($from) { $where .= ' AND t.trx_date >= ?'; $params[] = $from; }
        if ($to) { $where .= ' AND t.trx_date <= ?'; $params[] = $to; }

        $join = 'FROM transactions t
                 LEFT JOIN cashboxes c ON c.id = t.cashbox_id
                 LEFT JOIN customers cu ON t.party_type = "customer" AND cu.id = t.party_id
                 LEFT JOIN employees e ON t.party_type = "employee" AND e.id = t.party_id
                 LEFT JOIN promoters p ON t.party_type = "promoter" AND p.id = t.party_id';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val("SELECT COUNT(*) $join WHERE $where", $params);
        $rows = Database::all(
            "SELECT t.*, c.name AS cashbox_name, c.type AS cashbox_type,
                    cu.full_name AS customer_name, e.full_name AS employee_name, p.full_name AS promoter_name
             $join WHERE $where
             ORDER BY t.trx_date DESC, t.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );
        foreach ($rows as &$r) {
            $r['party_name'] = $r['customer_name'] ?? $r['employee_name'] ?? $r['promoter_name'] ?? null;
        }
        unset($r);

        $cashboxes = Database::all('SELECT id, name, type FROM cashboxes ORDER BY name ASC');
        $this->view('treasury/transactions', [
            'title'     => 'دریافت و پرداخت',
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $perPage)),
            'type'      => $type,
            'cashboxId' => $cashboxId,
            'from'      => (string) $this->input('date_from', ''),
            'to'        => (string) $this->input('date_to', ''),
            'cashboxes' => $cashboxes,
            'methods'   => self::METHODS,
        ]);
    }

    /** فرم دریافت — با شناسه فاکتور، مبلغ باقیمانده از پیش پر می‌شود */
    public function receiptAction(array $args): void
    {
        $this->requirePerm('treasury.view');
        $this->txnForm('receipt', (int) ($args[0] ?? 0));
    }

    /** فرم پرداخت */
    public function paymentAction(array $args): void
    {
        $this->requirePerm('treasury.view');
        $this->txnForm('payment', (int) ($args[0] ?? 0));
    }

    public function storeReceiptAction(): void
    {
        $this->requirePerm('treasury.edit');
        $this->saveTransaction('receipt');
    }

    public function storePaymentAction(): void
    {
        $this->requirePerm('treasury.edit');
        $this->saveTransaction('payment');
    }

    /* ==================== چک‌ها ==================== */

    public function chequesAction(): void
    {
        $this->requirePerm('treasury.view');

        $direction = (string) $this->input('direction', '');
        $status = (string) $this->input('status', '');
        $from = jinput_to_gregorian((string) $this->input('due_from', ''));
        $to = jinput_to_gregorian((string) $this->input('due_to', ''));

        $where = '1=1';
        $params = [];
        if (in_array($direction, ['received', 'issued'], true)) { $where .= ' AND ch.direction = ?'; $params[] = $direction; }
        if (in_array($status, ['in_hand', 'deposited', 'cleared', 'bounced', 'canceled'], true)) { $where .= ' AND ch.status = ?'; $params[] = $status; }
        if ($from) { $where .= ' AND ch.due_date >= ?'; $params[] = $from; }
        if ($to) { $where .= ' AND ch.due_date <= ?'; $params[] = $to; }

        $join = 'FROM cheques ch
                 LEFT JOIN customers cu ON ch.party_type = "customer" AND cu.id = ch.party_id
                 LEFT JOIN cashboxes cb ON cb.id = ch.cashbox_id';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val("SELECT COUNT(*) $join WHERE $where", $params);
        $rows = Database::all(
            "SELECT ch.*, cu.full_name AS party_name, cb.name AS cashbox_name
             $join WHERE $where
             ORDER BY ch.due_date ASC, ch.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $this->view('treasury/cheques', [
            'title'     => 'چک‌ها',
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $perPage)),
            'direction' => $direction,
            'status'    => $status,
            'dueFrom'   => (string) $this->input('due_from', ''),
            'dueTo'     => (string) $this->input('due_to', ''),
            'statuses'  => ['in_hand' => 'در جریان', 'deposited' => 'وصول بانک', 'cleared' => 'وصول شد', 'bounced' => 'برگشتی', 'canceled' => 'لغو'],
        ]);
    }

    public function chequeFormAction(array $args): void
    {
        $this->requirePerm('treasury.view');
        $id = (int) ($args[0] ?? 0);
        $row = null;
        if ($id) {
            $row = Database::one('SELECT * FROM cheques WHERE id = ?', [$id]);
            if (!$row) $this->notFound();
        }
        $this->view('treasury/cheque-form', [
            'title'     => $row ? 'ویرایش چک' : ($this->input('direction', 'received') === 'issued' ? 'چک پرداختی جدید' : 'چک دریافتی جدید'),
            'row'       => $row,
            'customers' => Database::all('SELECT id, full_name FROM customers ORDER BY full_name ASC'),
            'cashboxes' => Database::all('SELECT id, name, type FROM cashboxes WHERE is_active = 1 ORDER BY name ASC'),
        ]);
    }

    public function chequeStoreAction(array $args): void
    {
        $this->requirePerm('treasury.edit');
        $id = (int) ($args[0] ?? 0);
        $direction = (string) $this->input('direction', 'received') === 'issued' ? 'issued' : 'received';
        $chequeNo = (string) $this->input('cheque_no', '');
        $amount = $this->parseMoney((string) $this->input('amount', '0'));
        $dueDate = jinput_to_gregorian((string) $this->input('due_date', ''));
        $partyId = (int) $this->input('party_id', 0) ?: null;
        $cashboxId = (int) $this->input('cashbox_id', 0) ?: null;

        if ($chequeNo === '' || $amount <= 0 || !$dueDate) {
            flash_set('error', 'شماره چک، مبلغ مثبت و تاریخ سرسید الزامی است.');
            $this->redirect($id ? 'treasury/cheque-form/' . $id : 'treasury/cheque-form' . ($direction === 'issued' ? '?direction=issued' : ''));
        }
        if ($partyId && !Database::one('SELECT id FROM customers WHERE id = ?', [$partyId])) {
            $partyId = null;
        }

        $data = [
            'direction'   => $direction,
            'cheque_no'   => $chequeNo,
            'bank_name'   => $this->input('bank_name') ?: null,
            'due_date'    => $dueDate,
            'amount'      => round($amount, 2),
            'party_type'  => $partyId ? 'customer' : null,
            'party_id'    => $partyId,
            'cashbox_id'  => $cashboxId,
            'description' => $this->input('description') ?: null,
        ];
        if ($id) {
            $row = Database::one('SELECT * FROM cheques WHERE id = ?', [$id]);
            if (!$row) $this->notFound();
            Database::update('cheques', $data, 'id = ?', [$id]);
            flash_set('success', 'چک بروزرسانی شد.');
        } else {
            Database::insert('cheques', $data);
            flash_set('success', 'چک جدید ثبت شد.');
        }
        $this->redirect('treasury/cheques');
    }

    /** تغییر وضعیت چک — قواعد مالی وصول در همین متد */
    public function chequeStatusAction(array $args): void
    {
        $this->requirePerm('treasury.edit');
        $id = (int) ($args[0] ?? 0);
        $cheque = Database::one('SELECT * FROM cheques WHERE id = ?', [$id]);
        if (!$cheque) $this->notFound();

        $new = (string) $this->input('new_status', '');
        if (!in_array($new, ['in_hand', 'deposited', 'cleared', 'bounced', 'canceled'], true)) {
            flash_set('error', 'وضعیت نامعتبر است.');
            $this->redirect('treasury/cheques');
        }
        if ($new === $cheque['status']) {
            $this->redirect('treasury/cheques');
        }
        if ($cheque['status'] === 'cleared') {
            flash_set('error', 'چک وصول‌شده قابل تغییر نیست (اثر مالی ثبت شده است).');
            $this->redirect('treasury/cheques');
        }

        $amount = (float) $cheque['amount'];
        $cashboxId = (int) ($cheque['cashbox_id'] ?? 0);
        $partyName = '';
        if ($cheque['party_type'] === 'customer' && $cheque['party_id']) {
            $partyName = (string) (Database::val('SELECT full_name FROM customers WHERE id = ?', [$cheque['party_id']]) ?? '');
        }

        if ($new === 'cleared') {
            if (!$cashboxId) {
                Database::update('cheques', ['status' => 'cleared'], 'id = ?', [$id]);
                flash_set('error', 'وضعیت وصول شد؛ اما صندوقی برای این چک تعیین نشده بود و اثر مالی ثبت نشد.');
                $this->redirect('treasury/cheques');
            }
            $isReceived = $cheque['direction'] === 'received';
            // اثر صندوق
            Database::q(
                'UPDATE cashboxes SET balance = balance ' . ($isReceived ? '+ ' : '- ') . '? WHERE id = ?',
                [round($amount, 2), $cashboxId]
            );
            // حرکت خزانه
            $trxNo = InventoryService::nextNo('trx_prefix', 'trx_counter');
            $trxId = Database::insert('transactions', [
                'trx_no'      => $trxNo,
                'type'        => $isReceived ? 'receipt' : 'payment',
                'party_type'  => in_array($cheque['party_type'] ?? '', ['customer', 'employee', 'promoter', 'other'], true) ? $cheque['party_type'] : 'other',
                'party_id'    => $cheque['party_id'],
                'cashbox_id'  => $cashboxId,
                'method'      => 'cheque',
                'amount'      => round($amount, 2),
                'trx_date'    => today(),
                'invoice_id'  => $cheque['invoice_id'],
                'description' => ($isReceived ? 'وصول چک ' : 'پرداخت چک ') . $cheque['cheque_no'] . ($cheque['bank_name'] ? ' — ' . $cheque['bank_name'] : ''),
                'created_by'  => (int) (Auth::user()['id'] ?? 0) ?: null,
            ]);
            Database::update('cheques', ['status' => 'cleared'], 'id = ?', [$id]);
            // سند حسابداری خودکار
            $trx = Database::one('SELECT * FROM transactions WHERE id = ?', [$trxId]);
            if ($trx) AccountingService::voucherForTransaction($trx, $partyName);
            flash_set('success', 'چک ' . $cheque['cheque_no'] . ' وصول شد و اثر مالی ثبت گردید.');
            $this->redirect('treasury/cheques');
        }

        if ($new === 'bounced') {
            Database::update('cheques', ['status' => 'bounced'], 'id = ?', [$id]);
            flash_set('error', 'چک برگشتی علامت خورد؛ اثر مالی ثبت نشد.');
            $this->redirect('treasury/cheques');
        }

        // در جریان / وصول بانک / لغو — فقط وضعیت
        Database::update('cheques', ['status' => $new], 'id = ?', [$id]);
        flash_set('success', 'وضعیت چک بروزرسانی شد.');
        $this->redirect('treasury/cheques');
    }

    public function chequeDeleteAction(array $args): void
    {
        $this->requirePerm('treasury.manage');
        $id = (int) ($args[0] ?? 0);
        $cheque = Database::one('SELECT * FROM cheques WHERE id = ?', [$id]);
        if (!$cheque) $this->notFound();

        if ($cheque['status'] === 'cleared') {
            flash_set('error', 'چک وصول‌شده اثر مالی دارد و قابل حذف نیست.');
        } else {
            Database::delete('cheques', 'id = ?', [$id]);
            flash_set('success', 'چک حذف شد.');
        }
        $this->redirect('treasury/cheques');
    }

    /* ==================== private ==================== */

    /** فرم مشترک دریافت/پرداخت */
    private function txnForm(string $type, int $invoiceId): void
    {
        $invoice = null;
        if ($invoiceId) {
            $invoice = Database::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            if (!$invoice) $this->notFound();
        }
        $customers = Database::all('SELECT id, full_name, balance FROM customers ORDER BY full_name ASC');
        $cashboxes = Database::all('SELECT id, name, type, balance FROM cashboxes WHERE is_active = 1 ORDER BY name ASC');

        $this->view('treasury/txn-form', [
            'title'     => $type === 'receipt' ? 'ثبت دریافت' : 'ثبت پرداخت',
            'type'      => $type,
            'invoice'   => $invoice,
            'customers' => $customers,
            'cashboxes' => $cashboxes,
            'methods'   => self::METHODS,
        ]);
    }

    /** ذخیره دریافت/پرداخت + اثر صندوق + فاکتور + سند خودکار + مانده مشتری */
    private function saveTransaction(string $type): void
    {
        $amount = $this->parseMoney((string) $this->input('amount', '0'));
        $cashboxId = (int) $this->input('cashbox_id', 0);
        $trxDate = jinput_to_gregorian((string) $this->input('trx_date', ''));
        $method = (string) $this->input('method', 'cash');
        if (!isset(self::METHODS[$method])) $method = 'cash';
        $invoiceId = (int) $this->input('invoice_id', 0) ?: null;
        $description = (string) $this->input('description', '');

        $backTo = 'treasury/' . ($type === 'receipt' ? 'receipt' : 'payment');
        if ($invoiceId && Database::one('SELECT id FROM invoices WHERE id = ?', [$invoiceId])) {
            $backTo = 'treasury/' . ($type === 'receipt' ? 'receipt' : 'payment') . '/' . $invoiceId;
        }
        if ($amount <= 0) {
            flash_set('error', 'مبلغ باید بزرگ‌تر از صفر باشد.');
            $this->redirect($backTo);
        }
        $cashbox = Database::one('SELECT * FROM cashboxes WHERE id = ?', [$cashboxId]);
        if (!$cashbox) {
            flash_set('error', 'صندوق/حساب انتخابی معتبر نیست.');
            $this->redirect($backTo);
        }
        if (!$trxDate) {
            flash_set('error', 'تاریخ نامعتبر است (نمونه: ۱۴۰۴/۰۵/۰۱).');
            $this->redirect($backTo);
        }

        // طرف حساب: دریافت فقط مشتری؛ پرداخت مشتری یا متن آزاد (تأمین‌کننده/سایر)
        $partyType = 'other';
        $partyId = null;
        $partyName = '';
        $customerId = (int) $this->input('party_id', 0);
        if ($customerId && Database::one('SELECT id FROM customers WHERE id = ?', [$customerId])) {
            $partyType = 'customer';
            $partyId = $customerId;
            $partyName = (string) (Database::val('SELECT full_name FROM customers WHERE id = ?', [$customerId]) ?? '');
        } elseif ($type === 'payment') {
            $supplier = trim((string) $this->input('party_text', ''));
            if ($supplier !== '') {
                $description = trim('طرف حساب: ' . $supplier . ($description !== '' ? ' — ' . $description : ''));
            }
        }

        $trxNo = InventoryService::nextNo('trx_prefix', 'trx_counter');
        $trxId = Database::insert('transactions', [
            'trx_no'      => $trxNo,
            'type'        => $type,
            'party_type'  => $partyType,
            'party_id'    => $partyId,
            'cashbox_id'  => $cashboxId,
            'method'      => $method,
            'amount'      => round($amount, 2),
            'trx_date'    => $trxDate,
            'invoice_id'  => $invoiceId,
            'description' => $description ?: null,
            'created_by'  => (int) (Auth::user()['id'] ?? 0) ?: null,
        ]);

        // اثر صندوق: دریافت +، پرداخت −
        Database::q(
            'UPDATE cashboxes SET balance = balance ' . ($type === 'receipt' ? '+ ' : '- ') . '? WHERE id = ?',
            [round($amount, 2), $cashboxId]
        );

        // اعمال بر فاکتور (پرداخت با سقف کل)
        $redirect = 'treasury/transactions';
        if ($invoiceId) {
            $invoice = Database::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            if ($invoice && $invoice['status'] !== 'canceled') {
                $total = (float) $invoice['total'];
                $newPaid = min((float) $invoice['paid_amount'] + $amount, $total);
                $status = $newPaid >= $total ? 'paid' : 'partial';
                Database::update('invoices', ['paid_amount' => round($newPaid, 2), 'status' => $status], 'id = ?', [$invoiceId]);
                $redirect = 'invoices/view/' . $invoiceId;
                if (!$partyId && $invoice['customer_id']) $partyId = (int) $invoice['customer_id'];
            } else {
                flash_set('error', 'فاکتور یافت نشد یا لغو شده؛ مبلغ به فاکتور اعمال نشد.');
            }
        }

        // سند حسابداری خودکار
        $trx = Database::one('SELECT * FROM transactions WHERE id = ?', [$trxId]);
        if ($trx) AccountingService::voucherForTransaction($trx, $partyName);

        // مانده مشتری
        if ($partyType === 'customer' && $partyId) {
            SyncService::refreshCustomerBalance($partyId);
        }

        flash_set('success', ($type === 'receipt' ? 'دریافت ' : 'پرداخت ') . $trxNo . ' ثبت شد.');
        $this->redirect($redirect);
    }

    /** «۱٬۲۳۴,۵۶» یا «1234.5» → float */
    private function parseMoney(string $raw): float
    {
        $raw = fa_num_reverse(str_replace([',', '٬', ' '], '', trim($raw)));
        return (float) ($raw === '' ? 0 : $raw);
    }
}
