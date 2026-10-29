<?php
/**
 * فاکتورهای فروش و برگشتی فروش (+ چاپ، لغو، تأیید سفارش موبوآردر)
 * PurchaseController از متدهای محافظت‌شده همین کلاس ارث‌بری می‌کند.
 */
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\InventoryService;
use App\Services\AccountingService;
use App\Services\SyncService;

class InvoiceController extends Controller
{
    /** وضعیت‌های مجاز انتخابی در فرم دستی */
    public const FORM_STATUSES = ['draft', 'unpaid', 'paid'];
    /** روش‌های پرداخت */
    public const PAY_METHODS = [
        'cash' => 'نقد',
        'card' => 'کارت',
        'transfer' => 'کارت‌به‌کارت',
        'cheque' => 'چک',
        'online' => 'آنلاین',
    ];

    /* ============================ لیست‌ها ============================ */

    public function indexAction(): void
    {
        $this->requirePerm('invoices.view');
        $this->listView('sale', 'فاکتورهای فروش', 'invoices', 'invoices/create', 'فاکتور فروش جدید');
    }

    public function returnsAction(): void
    {
        $this->requirePerm('invoices.view');
        $this->listView('return_sale', 'فاکتورهای برگشتی فروش', 'invoices/returns', 'invoices/create-return', 'فاکتور برگشتی فروش');
    }

    /** لیست عمومی فاکتورها بر اساس نوع (نمای مشترک invoices/index) */
    protected function listView(string $type, string $heading, string $listRoute, string $createRoute, string $createLabel): void
    {
        $isSaleSide = in_array($type, ['sale', 'return_sale'], true);
        $f = $this->invoiceFilters($isSaleSide);
        $where = "invoice_type = '" . $type . "'" . ($f['where'] !== '' ? ' AND ' . $f['where'] : '');

        $select = $isSaleSide
            ? 'invoices.*, (SELECT full_name FROM customers cx WHERE cx.id = invoices.customer_id LIMIT 1) AS customer_name'
            : 'invoices.*';
        $data = $this->paginate('invoices', $where, $f['params'], 20, $select, 'id DESC');

        $qs = http_build_query(array_filter($f['filters'], fn($v) => $v !== null && $v !== ''));
        $base = url($listRoute) . ($qs !== '' ? '?' . $qs . '&' : '?');

        $this->view('invoices/index', [
            'title' => $heading,
            'heading' => $heading,
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'filters' => $f['filters'],
            'base' => $base,
            'listRoute' => $listRoute,
            'createRoute' => $createRoute,
            'createLabel' => $createLabel,
            'isPurchase' => !$isSaleSide,
        ]);
    }

    /** ساخت شرط‌های فیلتر (وضعیت، جستجو، بازه تاریخ شمسی) */
    protected function invoiceFilters(bool $withCustomer): array
    {
        $status = (string) $this->input('status', '');
        $q = (string) $this->input('q', '');
        $fromRaw = (string) $this->input('from', '');
        $toRaw = (string) $this->input('to', '');

        $parts = [];
        $params = [];
        if ($status !== '' && in_array($status, ['draft', 'pending', 'unpaid', 'partial', 'paid', 'canceled'], true)) {
            $parts[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $like = "%$q%";
            if ($withCustomer) {
                $parts[] = '(invoice_no LIKE ? OR customer_id IN (SELECT id FROM customers WHERE full_name LIKE ?))';
                $params[] = $like;
                $params[] = $like;
            } else {
                $parts[] = '(invoice_no LIKE ? OR supplier_name LIKE ?)';
                $params[] = $like;
                $params[] = $like;
            }
        }
        $from = null;
        $to = null;
        if ($fromRaw !== '') {
            $from = jinput_to_gregorian($fromRaw);
            if ($from === null) {
                flash_set('error', '«از تاریخ» شمسی معتبر نیست (نمونه صحیح: ۱۴۰۳/۰۱/۰۱).');
            } else {
                $parts[] = 'invoice_date >= ?';
                $params[] = $from;
            }
        }
        if ($toRaw !== '') {
            $to = jinput_to_gregorian($toRaw);
            if ($to === null) {
                flash_set('error', '«تا تاریخ» شمسی معتبر نیست (نمونه صحیح: ۱۴۰۳/۰۱/۰۱).');
            } else {
                $parts[] = 'invoice_date <= ?';
                $params[] = $to;
            }
        }

        return [
            'where' => implode(' AND ', $parts),
            'params' => $params,
            'filters' => ['status' => $status, 'q' => $q, 'from' => $fromRaw, 'to' => $toRaw],
        ];
    }

    /* ============================ فرم‌ها ============================ */

    public function createAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->formView('sale');
    }

    public function createReturnAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->formView('return_sale');
    }

    /** نمای فرم (فروش/برگشتی فروش در invoices/form، خرید/برگشتی خرید در purchases/form) */
    protected function formView(string $type): void
    {
        $isPurchase = in_array($type, ['purchase', 'return_purchase'], true);
        $isReturn = str_starts_with($type, 'return_');

        [$heading, $backRoute, $formAction] = match ($type) {
            'sale' => ['فاکتور فروش جدید', 'invoices', 'invoices/store'],
            'return_sale' => ['فاکتور برگشتی فروش', 'invoices/returns', 'invoices/store-return'],
            'purchase' => ['فاکتور خرید جدید', 'purchases', 'purchases/store'],
            default => ['فاکتور برگشتی خرید', 'purchases/returns', 'purchases/store-return'],
        };

        $this->view($isPurchase ? 'purchases/form' : 'invoices/form', [
            'title' => $heading,
            'heading' => $heading,
            'type' => $type,
            'typeLabel' => invoice_type_label($type),
            'isPurchase' => $isPurchase,
            'isReturn' => $isReturn,
            'formAction' => $formAction,
            'backRoute' => $backRoute,
            'customers' => $isPurchase ? [] : Database::all('SELECT id, full_name, code FROM customers ORDER BY full_name ASC'),
            'products' => Database::all('SELECT id, name, sku, unit, sale_price, purchase_price FROM products WHERE is_active = 1 ORDER BY name ASC'),
            'employees' => $isPurchase ? Database::all("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC") : [],
            'taxRate' => (string) setting('tax_rate', '9'),
            'todayJ' => jdate(today()),
        ]);
    }

    /* ============================ ذخیره ============================ */

    public function storeAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->store('sale');
    }

    public function storeReturnAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->store('return_sale');
    }

    /** ذخیره فاکتور (فروش/خرید/برگشتی‌ها) — محاسبات سمت سرور */
    protected function store(string $type): void
    {
        $isPurchase = in_array($type, ['purchase', 'return_purchase'], true);
        $backRoute = match ($type) {
            'sale' => 'invoices/create',
            'return_sale' => 'invoices/create-return',
            'purchase' => 'purchases/create',
            default => 'purchases/create-return',
        };

        // تاریخ‌ها (شمسی → میلادی)
        $date = jinput_to_gregorian((string) $this->input('invoice_date', ''));
        if ($date === null) {
            flash_set('error', 'تاریخ فاکتور الزامی است و باید شمسی معتبر باشد (نمونه: ۱۴۰۳/۰۱/۰۱).');
            $this->redirect($backRoute);
        }
        $dueJ = (string) $this->input('due_date', '');
        $due = null;
        if ($dueJ !== '') {
            $due = jinput_to_gregorian($dueJ);
            if ($due === null) {
                flash_set('error', 'تاریخ سررسید شمسی معتبر نیست.');
                $this->redirect($backRoute);
            }
        }

        // طرف حساب
        $customerId = null;
        $supplierName = null;
        $employeeId = (int) $this->input('employee_id', 0) ?: null;
        if ($isPurchase) {
            $supplierName = trim((string) $this->input('supplier_name', ''));
            if ($supplierName === '') {
                flash_set('error', 'نام تأمین‌کننده الزامی است.');
                $this->redirect($backRoute);
            }
        } else {
            $customerId = (int) $this->input('customer_id', 0) ?: null;
            if (!$customerId || !Database::val('SELECT id FROM customers WHERE id = ?', [$customerId])) {
                flash_set('error', 'انتخاب مشتری الزامی است.');
                $this->redirect($backRoute);
            }
        }

        // اقلام
        $rows = $this->collectItems();
        if (!$rows) {
            flash_set('error', 'حداقل یک ردیف کالا با تعداد بزرگ‌تر از صفر (و شرح یا کالای انتخابی) الزامی است.');
            $this->redirect($backRoute);
        }

        // محاسبات
        $subtotal = 0.0;
        foreach ($rows as $r) {
            $subtotal += $r['total'];
        }
        $subtotal = round($subtotal, 2);
        $discount = max(0.0, min(self::num($this->input('discount', '0')), $subtotal));
        $taxRate = max(0.0, self::num($this->input('tax_rate', (string) setting('tax_rate', '9'))));
        $tax = round(max(0.0, $subtotal - $discount) * $taxRate / 100, 2);
        $shipping = max(0.0, self::num($this->input('shipping', '0')));
        $total = round(max(0.0, $subtotal - $discount + $tax + $shipping), 2);

        $status = (string) $this->input('status', 'draft');
        if (!in_array($status, self::FORM_STATUSES, true)) {
            $status = 'draft';
        }
        $paymentMethod = (string) $this->input('payment_method', '');
        if (!isset(self::PAY_METHODS[$paymentMethod])) {
            $paymentMethod = null;
        }

        $no = InventoryService::nextNo($isPurchase ? 'purchase_prefix' : 'invoice_prefix', 'invoice_counter');
        $userId = (int) (Auth::user()['id'] ?? 0) ?: null;

        $invoiceId = Database::insert('invoices', [
            'invoice_no' => $no,
            'invoice_type' => $type,
            'customer_id' => $customerId,
            'supplier_name' => $supplierName,
            'employee_id' => $employeeId,
            'promoter_id' => null,
            'invoice_date' => $date,
            'due_date' => $due,
            'subtotal' => $subtotal,
            'discount' => round($discount, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => $tax,
            'shipping' => round($shipping, 2),
            'total' => $total,
            'paid_amount' => $status === 'paid' ? $total : 0.0,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'description' => (string) $this->input('description', '') ?: null,
            'source' => 'manual',
            'created_by' => $userId,
        ]);

        foreach ($rows as $r) {
            Database::insert('invoice_items', [
                'invoice_id' => $invoiceId,
                'product_id' => $r['product_id'],
                'description' => $r['description'],
                'qty' => $r['qty'],
                'unit_price' => $r['price'],
                'discount' => $r['discount'],
                'total' => $r['total'],
            ]);
        }

        // موجودی (فاکتورهای غیرپیش‌نویس)
        if ($status !== 'draft') {
            $this->moveStockForInvoice($type, $invoiceId, $no, $rows, 'فاکتور ');
        }

        // سند حسابداری (به‌جز پیش‌نویس و لغو)
        if (!in_array($status, ['draft', 'canceled'], true)) {
            $invoice = Database::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            if ($invoice) {
                if ($type === 'return_sale') {
                    $this->returnSaleVoucher($invoice, $userId);
                } elseif ($type === 'return_purchase') {
                    $this->returnPurchaseVoucher($invoice, $userId);
                } else {
                    AccountingService::voucherForInvoice($invoice);
                }
            }
        }

        if ($customerId) {
            SyncService::refreshCustomerBalance($customerId);
        }

        flash_set('success', 'فاکتور ' . $no . ' با موفقیت ثبت شد.');
        $this->redirect('invoices/view/' . $invoiceId);
    }

    /** نرمال‌سازی ردیف‌های اقلام دریافتی از فرم */
    protected function collectItems(): array
    {
        $in = $_POST['items'] ?? [];
        if (!is_array($in)) {
            return [];
        }
        $pids = (array) ($in['product_id'] ?? []);
        $descs = (array) ($in['description'] ?? []);
        $qtys = (array) ($in['qty'] ?? []);
        $prices = (array) ($in['unit_price'] ?? []);
        $discs = (array) ($in['discount'] ?? []);

        $count = max(count($pids), count($descs), count($qtys), count($prices), count($discs));
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $pid = (int) ($pids[$i] ?? 0);
            $desc = trim((string) ($descs[$i] ?? ''));
            $qty = self::num($qtys[$i] ?? 0);
            $price = self::num($prices[$i] ?? 0);
            $disc = self::num($discs[$i] ?? 0);

            if ($qty <= 0 || ($pid === 0 && $desc === '')) {
                continue;
            }
            if ($pid !== 0) {
                $product = Database::one('SELECT id, name FROM products WHERE id = ?', [$pid]);
                $pid = $product ? (int) $product['id'] : 0;
                if ($desc === '' && $product) {
                    $desc = (string) $product['name'];
                }
            }
            $disc = max(0.0, min($disc, $qty * $price));
            $rows[] = [
                'product_id' => $pid > 0 ? $pid : null,
                'description' => $desc !== '' ? mb_substr($desc, 0, 190) : null,
                'qty' => $qty,
                'price' => round($price, 2),
                'discount' => round($disc, 2),
                'total' => round(max(0.0, $qty * $price - $disc), 2),
            ];
        }
        return $rows;
    }

    /** حرکت انبار برای فاکتور تازه‌ثبت‌شده */
    protected function moveStockForInvoice(string $type, int $invoiceId, string $no, array $rows, string $labelPrefix): void
    {
        $direction = self::stockDirection($type);
        if ($direction === '') {
            return;
        }
        $wh = InventoryService::defaultWarehouseId();
        $userId = (int) (Auth::user()['id'] ?? 0) ?: null;
        $isPurchaseSide = in_array($type, ['purchase', 'return_purchase'], true);

        foreach ($rows as $r) {
            if (!$r['product_id']) {
                continue;
            }
            $cost = $isPurchaseSide ? (float) $r['price'] : 0.0;
            InventoryService::move((int) $r['product_id'], $wh, $direction, (float) $r['qty'], $cost, 'invoice', $invoiceId, $labelPrefix . $no, $userId);
        }
    }

    /** جهت حرکت انبار هنگام ثبت فاکتور (خالی = بدون حرکت) */
    protected static function stockDirection(string $type): string
    {
        return match ($type) {
            'sale' => 'out',
            'return_sale' => 'in',
            'purchase' => 'in',
            'return_purchase' => 'out',
            default => '',
        };
    }

    /** سند برگشتی فروش: بدهکار فروش/مالیات (و حمل) / بستانکار دریافتنی — همیشه تراز */
    protected function returnSaleVoucher(array $inv, ?int $userId = null): int
    {
        $net = max(0.0, (float) $inv['subtotal'] - (float) $inv['discount']);
        $tax = (float) $inv['tax_amount'];
        $ship = (float) $inv['shipping'];

        $lines = [
            ['account_id' => (int) setting('account_sales', 17), 'debit' => $net, 'credit' => 0, 'description' => 'برگشت از فروش'],
        ];
        if ($tax > 0) {
            $lines[] = ['account_id' => (int) setting('account_vat', 11), 'debit' => $tax, 'credit' => 0, 'description' => 'برگشت مالیات فروش'];
        }
        if ($ship > 0) {
            $shipAcc = (int) Database::val("SELECT id FROM accounts WHERE code = '5102'") ?: 21;
            $lines[] = ['account_id' => $shipAcc, 'debit' => $ship, 'credit' => 0, 'description' => 'برگشت هزینه حمل'];
        }
        $lines[] = ['account_id' => (int) setting('account_ar', 5), 'debit' => 0, 'credit' => (float) $inv['total'], 'description' => 'کاهش مطالبات مشتری'];

        return AccountingService::postVoucher(
            (string) $inv['invoice_date'],
            'فاکتور برگشتی فروش ' . $inv['invoice_no'],
            $lines,
            'return_sale',
            'invoice',
            (int) $inv['id'],
            $userId
        );
    }

    /** سند برگشتی خرید: بدهکار پرداختنی / بستانکار موجودی و مالیات — همیشه تراز */
    protected function returnPurchaseVoucher(array $inv, ?int $userId = null): int
    {
        $net = max(0.0, (float) $inv['subtotal'] - (float) $inv['discount']);
        $tax = (float) $inv['tax_amount'];
        $ship = (float) $inv['shipping'];

        $lines = [
            ['account_id' => (int) setting('account_inventory', 6), 'debit' => 0, 'credit' => $net, 'description' => 'برگشت کالا به تأمین‌کننده'],
        ];
        if ($tax > 0) {
            $lines[] = ['account_id' => (int) setting('account_vat', 11), 'debit' => 0, 'credit' => $tax, 'description' => 'برگشت مالیات خرید'];
        }
        if ($ship > 0) {
            $shipAcc = (int) Database::val("SELECT id FROM accounts WHERE code = '5102'") ?: 21;
            $lines[] = ['account_id' => $shipAcc, 'debit' => 0, 'credit' => $ship, 'description' => 'برگشت هزینه حمل'];
        }
        $lines[] = ['account_id' => (int) setting('account_ap', 10), 'debit' => (float) $inv['total'], 'credit' => 0, 'description' => 'کاهش بدهی به تأمین‌کننده'];

        return AccountingService::postVoucher(
            (string) $inv['invoice_date'],
            'فاکتور برگشتی خرید ' . $inv['invoice_no'],
            $lines,
            'return_purchase',
            'invoice',
            (int) $inv['id'],
            $userId
        );
    }

    /* ============================ مشاهده / چاپ ============================ */

    public function viewAction(array $args): void
    {
        $this->requirePerm('invoices.view');
        [$row, $items, $transactions] = $this->loadInvoice((int) ($args[0] ?? 0));

        $isPurchaseSide = in_array($row['invoice_type'], ['purchase', 'return_purchase'], true);
        $backRoute = match ($row['invoice_type']) {
            'purchase' => 'purchases',
            'return_purchase' => 'purchases/returns',
            'return_sale' => 'invoices/returns',
            default => 'invoices',
        };

        $this->view('invoices/view', [
            'title' => 'فاکتور ' . $row['invoice_no'],
            'row' => $row,
            'items' => $items,
            'transactions' => $transactions,
            'typeLabel' => invoice_type_label($row['invoice_type']),
            'isPurchase' => $isPurchaseSide,
            'backRoute' => $backRoute,
            'remaining' => max(0.0, (float) $row['total'] - (float) $row['paid_amount']),
        ]);
    }

    public function printAction(array $args): void
    {
        $this->requirePerm('invoices.view');
        [$row, $items] = $this->loadInvoice((int) ($args[0] ?? 0));

        $this->view('invoices/print', [
            'title' => invoice_type_label($row['invoice_type']) . ' ' . $row['invoice_no'],
            'row' => $row,
            'items' => $items,
            'typeLabel' => invoice_type_label($row['invoice_type']),
            'isPurchase' => in_array($row['invoice_type'], ['purchase', 'return_purchase'], true),
            'remaining' => max(0.0, (float) $row['total'] - (float) $row['paid_amount']),
        ], 'print');
    }

    /** بارگذاری فاکتور + اقلام + تراکنش‌ها */
    protected function loadInvoice(int $id): array
    {
        $row = Database::one(
            'SELECT i.*, c.full_name AS customer_name, c.mobile AS customer_mobile,
                    p.full_name AS promoter_name, u.full_name AS creator_name
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN promoters p ON p.id = i.promoter_id
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.id = ?',
            [$id]
        );
        if (!$row) {
            $this->notFound();
        }
        $items = Database::all(
            'SELECT ii.*, pr.name AS product_name, pr.unit AS product_unit
             FROM invoice_items ii LEFT JOIN products pr ON pr.id = ii.product_id
             WHERE ii.invoice_id = ? ORDER BY ii.id ASC',
            [$id]
        );
        $transactions = Database::all(
            'SELECT t.*, cb.name AS cashbox_name, cb.type AS cashbox_type
             FROM transactions t LEFT JOIN cashboxes cb ON cb.id = t.cashbox_id
             WHERE t.invoice_id = ? ORDER BY t.id DESC',
            [$id]
        );
        return [$row, $items, $transactions];
    }

    /* ============================ لغو / تأیید ============================ */

    public function cancelAction(array $args): void
    {
        $this->requirePerm('invoices.manage');
        $id = (int) ($args[0] ?? 0);
        $inv = Database::one('SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$inv) {
            $this->notFound();
        }
        if ($inv['status'] === 'canceled') {
            flash_set('error', 'این فاکتور قبلاً لغو شده است.');
            $this->redirect('invoices/view/' . $id);
        }

        $wasShipped = in_array($inv['status'], ['unpaid', 'partial', 'paid'], true);
        Database::update('invoices', ['status' => 'canceled'], 'id = ?', [$id]);

        // برگشت موجودی فقط اگر قبلاً حرکت انبار ثبت شده باشد
        if ($wasShipped) {
            $reverse = self::stockDirection((string) $inv['invoice_type']) === 'in' ? 'out' : 'in';
            $items = Database::all('SELECT * FROM invoice_items WHERE invoice_id = ? AND product_id IS NOT NULL', [$id]);
            $wh = InventoryService::defaultWarehouseId();
            $userId = (int) (Auth::user()['id'] ?? 0) ?: null;
            foreach ($items as $r) {
                InventoryService::move((int) $r['product_id'], $wh, $reverse, (float) $r['qty'], 0.0, 'invoice', $id, 'لغو فاکتور ' . $inv['invoice_no'], $userId);
            }
        }

        if ($inv['customer_id']) {
            SyncService::refreshCustomerBalance((int) $inv['customer_id']);
        }

        flash_set('success', 'فاکتور ' . $inv['invoice_no'] . ' لغو شد' . ($wasShipped ? ' و موجودی مرتبط برگشت داده شد.' : '.'));
        $this->redirect('invoices/view/' . $id);
    }

    /** تأیید سفارش موبوآردر (pending → unpaid) همراه خروج موجودی و سند حسابداری */
    public function approveAction(array $args): void
    {
        $this->requirePerm('invoices.manage');
        $id = (int) ($args[0] ?? 0);
        $inv = Database::one('SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$inv) {
            $this->notFound();
        }
        if ($inv['status'] !== 'pending' || $inv['invoice_type'] !== 'sale') {
            flash_set('error', 'فقط سفارش‌های در انتظار تأیید قابل تأیید هستند.');
            $this->redirect('invoices/view/' . $id);
        }

        Database::update('invoices', ['status' => 'unpaid'], 'id = ?', [$id]);

        $items = Database::all('SELECT * FROM invoice_items WHERE invoice_id = ? AND product_id IS NOT NULL', [$id]);
        $wh = InventoryService::defaultWarehouseId();
        $userId = (int) (Auth::user()['id'] ?? 0) ?: null;
        foreach ($items as $r) {
            InventoryService::move((int) $r['product_id'], $wh, 'out', (float) $r['qty'], 0.0, 'invoice', $id, 'تأیید سفارش موبوآردر ' . $inv['invoice_no'], $userId);
        }

        $fresh = Database::one('SELECT * FROM invoices WHERE id = ?', [$id]);
        if ($fresh) {
            AccountingService::voucherForInvoice($fresh);
        }
        if ($inv['customer_id']) {
            SyncService::refreshCustomerBalance((int) $inv['customer_id']);
        }

        flash_set('success', 'سفارش موبوآردر تأیید شد؛ خروج موجودی و سند حسابداری ثبت گردید.');
        $this->redirect('invoices/view/' . $id);
    }

    /* ============================ کمکی ============================ */

    /** تبدیل رشته عددی با جداکننده هزارگان به float */
    protected static function num(mixed $v): float
    {
        return (float) str_replace(',', '', (string) $v);
    }
}
