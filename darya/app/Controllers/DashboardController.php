<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\InventoryService;

class DashboardController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('dashboard.view');

        $today = today();
        $monthStart = substr($today, 0, 8) . '01';

        $stats = [
            'sales_today'  => (float) Database::val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE invoice_type='sale' AND status<>'canceled' AND invoice_date = ?", [$today]),
            'sales_month'  => (float) Database::val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE invoice_type='sale' AND status<>'canceled' AND invoice_date >= ?", [$monthStart]),
            'receivable'   => (float) Database::val("SELECT COALESCE(SUM(total-paid_amount),0) FROM invoices WHERE invoice_type='sale' AND status IN ('unpaid','partial','pending')"),
            'unpaid_count' => (int) Database::val("SELECT COUNT(*) FROM invoices WHERE invoice_type='sale' AND status IN ('unpaid','partial','pending')"),
            'cash_total'   => (float) Database::val('SELECT COALESCE(SUM(balance),0) FROM cashboxes WHERE is_active=1'),
            'low_stock'    => (int) Database::val('SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty <= min_stock'),
            'pending_orders'   => (int) Database::val("SELECT COUNT(*) FROM invoices WHERE source='moboorder' AND status='pending'"),
            'abandoned_carts'  => (int) Database::val("SELECT COUNT(*) FROM abandoned_carts WHERE status='abandoned'"),
            'customers'    => (int) Database::val('SELECT COUNT(*) FROM customers'),
            'products'     => (int) Database::val('SELECT COUNT(*) FROM products WHERE is_active=1'),
        ];

        $recentInvoices = Database::all(
            "SELECT i.*, c.full_name AS customer_name FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             ORDER BY i.id DESC LIMIT 8"
        );

        $lowStockRows = Database::all(
            'SELECT id, name, sku, stock_qty, min_stock, unit FROM products WHERE is_active=1 AND stock_qty <= min_stock ORDER BY stock_qty ASC LIMIT 8'
        );

        $lastSync = Database::one('SELECT * FROM sync_log ORDER BY id DESC LIMIT 1');

        // فروش ۶ ماه اخیر برای نمودار ساده
        $chart = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("first day of -$i month"));
            $sum = (float) Database::val(
                "SELECT COALESCE(SUM(total),0) FROM invoices WHERE invoice_type='sale' AND status<>'canceled' AND invoice_date LIKE ?",
                [$m . '%']
            );
            $chart[] = ['month' => $m, 'total' => $sum, 'label' => jdate($m . '-01', 'm')];
        }
        $chartMax = max(1, max(array_column($chart, 'total')));

        $this->view('dashboard/index', compact('stats', 'recentInvoices', 'lowStockRows', 'lastSync', 'chart', 'chartMax'));
    }
}
