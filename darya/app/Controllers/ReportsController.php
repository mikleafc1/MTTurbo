<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** گزارش‌ها — همه اکشن‌ها نیازمند reports.view */
class ReportsController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('reports.view');

        // بازه ماه جاری شمسی برای آمار سریع
        [$fromG, $toG] = $this->defaultMonthRange();

        $salesMonth = (float) Database::val(
            "SELECT COALESCE(SUM(total),0) FROM invoices
             WHERE invoice_type = 'sale' AND status <> 'canceled' AND invoice_date BETWEEN ? AND ?",
            [$fromG, $toG]
        );
        $stockValue = (float) Database::val('SELECT COALESCE(SUM(stock_qty * purchase_price),0) FROM products');
        $customersCount = (int) Database::val('SELECT COUNT(*) FROM customers');
        $cashTotal = (float) Database::val('SELECT COALESCE(SUM(balance),0) FROM cashboxes WHERE is_active = 1');

        $bestCustomers = Database::all(
            "SELECT c.id, c.full_name, COUNT(i.id) AS inv_count, COALESCE(SUM(i.total),0) AS total_sales
             FROM customers c
             JOIN invoices i ON i.customer_id = c.id AND i.invoice_type = 'sale' AND i.status <> 'canceled'
             GROUP BY c.id ORDER BY total_sales DESC LIMIT 5"
        );
        $topProducts = Database::all(
            "SELECT p.id, p.name, p.unit, COALESCE(SUM(it.qty),0) AS qty_sold, COALESCE(SUM(it.total),0) AS revenue
             FROM invoice_items it
             JOIN invoices i ON i.id = it.invoice_id AND i.invoice_type = 'sale' AND i.status <> 'canceled'
             LEFT JOIN products p ON p.id = it.product_id
             GROUP BY p.id ORDER BY qty_sold DESC LIMIT 5"
        );

        $this->view('reports/index', [
            'title'          => 'گزارش‌ها',
            'salesMonth'     => $salesMonth,
            'stockValue'     => $stockValue,
            'customersCount' => $customersCount,
            'cashTotal'      => $cashTotal,
            'bestCustomers'  => $bestCustomers,
            'topProducts'    => $topProducts,
        ]);
    }

    public function salesAction(): void
    {
        $this->requirePerm('reports.view');
        [$fromG, $toG, $fromJ, $toJ] = $this->dateRange();
        $where = "invoice_type = 'sale' AND status <> 'canceled' AND invoice_date BETWEEN ? AND ?";
        $params = [$fromG, $toG];

        $totals = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total, COALESCE(SUM(discount),0) AS discount,
                    COALESCE(SUM(tax_amount),0) AS tax, COALESCE(SUM(shipping),0) AS shipping,
                    COALESCE(SUM(subtotal),0) AS subtotal
             FROM invoices WHERE $where",
            $params
        ) ?? ['cnt' => 0, 'total' => 0, 'discount' => 0, 'tax' => 0, 'shipping' => 0, 'subtotal' => 0];

        $byDay = Database::all(
            "SELECT invoice_date, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total,
                    COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(tax_amount),0) AS tax
             FROM invoices WHERE $where GROUP BY invoice_date ORDER BY invoice_date",
            $params
        );
        $byMethod = Database::all(
            "SELECT COALESCE(payment_method, '—') AS method, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
             FROM invoices WHERE $where GROUP BY payment_method ORDER BY total DESC",
            $params
        );

        $this->view('reports/sales', [
            'title'    => 'گزارش فروش',
            'fromJ'    => $fromJ,
            'toJ'      => $toJ,
            'totals'   => $totals,
            'byDay'    => $byDay,
            'byMethod' => $byMethod,
        ]);
    }

    public function inventoryAction(): void
    {
        $this->requirePerm('reports.view');

        $rows = Database::all(
            "SELECT p.id, p.sku, p.name, p.unit, p.stock_qty, p.min_stock, p.purchase_price, p.sale_price,
                    COALESCE(c.name, '—') AS category,
                    (p.stock_qty * p.purchase_price) AS stock_value
             FROM products p
             LEFT JOIN product_categories c ON c.id = p.category_id
             ORDER BY p.name"
        );

        $itemCount = 0; $stockValue = 0.0; $lowCount = 0; $zeroCount = 0;
        foreach ($rows as $r) {
            $itemCount++;
            $stockValue += (float) $r['stock_value'];
            $qty = (float) $r['stock_qty'];
            $min = (float) $r['min_stock'];
            if ($qty <= 0) $zeroCount++;
            elseif ($min > 0 && $qty <= $min) $lowCount++;
        }

        $this->view('reports/inventory', [
            'title'      => 'گزارش موجودی انبار',
            'rows'       => $rows,
            'itemCount'  => $itemCount,
            'stockValue' => $stockValue,
            'lowCount'   => $lowCount,
            'zeroCount'  => $zeroCount,
        ]);
    }

    public function customersAction(): void
    {
        $this->requirePerm('reports.view');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val('SELECT COUNT(*) FROM customers');
        $offset = ($page - 1) * $perPage;

        $rows = Database::all(
            "SELECT c.id, c.code, c.full_name, c.mobile, c.balance, c.credit_limit,
                    COUNT(i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_sales,
                    MAX(i.invoice_date) AS last_purchase
             FROM customers c
             LEFT JOIN invoices i ON i.customer_id = c.id AND i.invoice_type = 'sale' AND i.status <> 'canceled'
             GROUP BY c.id
             ORDER BY c.balance DESC, c.id ASC
             LIMIT $perPage OFFSET $offset"
        );
        $pages = max(1, (int) ceil($total / $perPage));

        $this->view('reports/customers', [
            'title' => 'مانده حساب مشتریان',
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ]);
    }

    public function treasuryAction(): void
    {
        $this->requirePerm('reports.view');
        [$fromG, $toG, $fromJ, $toJ] = $this->dateRange();

        $cashboxes = Database::all('SELECT * FROM cashboxes ORDER BY type, name');

        $trx = Database::all(
            "SELECT type, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
             FROM transactions WHERE trx_date BETWEEN ? AND ? GROUP BY type",
            [$fromG, $toG]
        );
        $receipt = ['cnt' => 0, 'total' => 0.0];
        $payment = ['cnt' => 0, 'total' => 0.0];
        foreach ($trx as $t) {
            if ($t['type'] === 'receipt') $receipt = ['cnt' => (int) $t['cnt'], 'total' => (float) $t['total']];
            if ($t['type'] === 'payment') $payment = ['cnt' => (int) $t['cnt'], 'total' => (float) $t['total']];
        }

        $cheques = Database::all(
            "SELECT status,
                    COALESCE(SUM(direction = 'received'),0) AS cnt_received,
                    COALESCE(SUM(IF(direction = 'received', amount, 0)),0) AS amount_received,
                    COALESCE(SUM(direction = 'issued'),0) AS cnt_issued,
                    COALESCE(SUM(IF(direction = 'issued', amount, 0)),0) AS amount_issued
             FROM cheques GROUP BY status
             ORDER BY FIELD(status, 'in_hand', 'deposited', 'cleared', 'bounced', 'canceled')"
        );

        $this->view('reports/treasury', [
            'title'    => 'گزارش خزانه',
            'fromJ'    => $fromJ,
            'toJ'      => $toJ,
            'cashboxes' => $cashboxes,
            'receipt'  => $receipt,
            'payment'  => $payment,
            'cheques'  => $cheques,
        ]);
    }

    /* ----------------helpers---------------- */

    /** بازه پیش‌فرض: اول تا امروزِ ماه جاری شمسی → [gregorian_from, gregorian_to] */
    private function defaultMonthRange(): array
    {
        [$jy, $jm] = gregorian_to_jalali((int) date('Y'), (int) date('n'), (int) date('j'));
        $from = jinput_to_gregorian(sprintf('%04d/%02d/01', $jy, $jm)) ?? date('Y-m-01');
        $to = today();
        return [$from, $to];
    }

    /**
     * بازه تاریخ شمسی از ورودی کاربر (پیش‌فرض: ماه جاری)
     * @return array{0:string,1:string,2:string,3:string} [from_greg, to_greg, from_jalali, to_jalali]
     */
    private function dateRange(): array
    {
        [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y'), (int) date('n'), (int) date('j'));
        $fromDefault = sprintf('%04d/%02d/01', $jy, $jm);
        $toDefault = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        $fromJ = trim((string) $this->input('from', ''));
        $toJ = trim((string) $this->input('to', ''));

        $fromG = $fromJ !== '' ? jinput_to_gregorian($fromJ) : null;
        $toG = $toJ !== '' ? jinput_to_gregorian($toJ) : null;

        if ($fromJ !== '' && $fromG === null) {
            flash_set('error', 'تاریخ «از» نامعتبر است؛ اولین روز ماه جاری اعمال شد.');
            $fromJ = '';
        }
        if ($toJ !== '' && $toG === null) {
            flash_set('error', 'تاریخ «تا» نامعتبر است؛ امروز اعمال شد.');
            $toJ = '';
        }
        if ($fromJ === '') {
            $fromJ = $fromDefault;
            $fromG = jinput_to_gregorian($fromDefault);
        }
        if ($toJ === '') {
            $toJ = $toDefault;
            $toG = jinput_to_gregorian($toDefault);
        }
        if ($fromG !== null && $toG !== null && $fromG > $toG) {
            [$fromJ, $toJ] = [$toJ, $fromJ];
            [$fromG, $toG] = [$toG, $fromG];
        }
        return [(string) $fromG, (string) $toG, $fromJ, $toJ];
    }
}
