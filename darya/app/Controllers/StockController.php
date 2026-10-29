<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\InventoryService;

/** کاردکس، اصلاح موجودی و انتقال بین انبارها — موجودی فقط از طریق InventoryService::move() تغییر می‌کند */
class StockController extends Controller
{
    private const TYPES = ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment'];

    public function kardexAction(): void
    {
        $this->requirePerm('stock.view');

        $productId = (int) $this->input('product_id', 0);
        $type = (string) $this->input('type', '');
        $from = (string) $this->input('from', '');
        $to = (string) $this->input('to', '');

        $where = '1=1';
        $params = [];
        if ($productId > 0) {
            $where .= ' AND product_id = ?';
            $params[] = $productId;
        }
        if (in_array($type, self::TYPES, true)) {
            $where .= ' AND movement_type = ?';
            $params[] = $type;
        }
        if ($from !== '') {
            $fromG = jinput_to_gregorian($from);
            if ($fromG === null) {
                flash_set('error', 'تاریخ «از» معتبر نیست (مثل 1403/01/01).');
            } else {
                $where .= ' AND created_at >= ?';
                $params[] = $fromG . ' 00:00:00';
            }
        }
        if ($to !== '') {
            $toG = jinput_to_gregorian($to);
            if ($toG === null) {
                flash_set('error', 'تاریخ «تا» معتبر نیست (مثل 1403/01/01).');
            } else {
                $where .= ' AND created_at <= ?';
                $params[] = $toG . ' 23:59:59';
            }
        }

        $data = $this->paginate('stock_movements', $where, $params, 20, '*', 'id DESC');

        // نام کالاها و انبارهای مربوط به ردیف‌های همین صفحه
        $productNames = [];
        $productUnits = [];
        $pids = array_values(array_unique(array_map(fn (array $r): int => (int) $r['product_id'], $data['rows'])));
        if ($pids) {
            $in = implode(',', array_fill(0, count($pids), '?'));
            foreach (Database::all("SELECT id, name, unit FROM products WHERE id IN ($in)", $pids) as $p) {
                $productNames[(int) $p['id']] = (string) $p['name'];
                $productUnits[(int) $p['id']] = (string) $p['unit'];
            }
        }
        $warehouseNames = [];
        $wids = array_values(array_unique(array_map(fn (array $r): int => (int) $r['warehouse_id'], $data['rows'])));
        if ($wids) {
            $in = implode(',', array_fill(0, count($wids), '?'));
            foreach (Database::all("SELECT id, name FROM warehouses WHERE id IN ($in)", $wids) as $w) {
                $warehouseNames[(int) $w['id']] = (string) $w['name'];
            }
        }

        $products = Database::all('SELECT id, name, sku FROM products ORDER BY name');

        $this->view('stock/kardex', [
            'title' => 'کاردکس کالا',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'productId' => $productId,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'products' => $products,
            'productNames' => $productNames,
            'productUnits' => $productUnits,
            'warehouseNames' => $warehouseNames,
        ]);
    }

    public function adjustAction(): void
    {
        $this->requirePerm('stock.edit');
        $this->view('stock/adjust', [
            'title' => 'اصلاح موجودی',
            'products' => Database::all('SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name'),
            'preProductId' => (int) $this->input('product_id', 0),
            'defaultWarehouseName' => $this->warehouseName(InventoryService::defaultWarehouseId()),
        ]);
    }

    public function storeAdjustAction(): void
    {
        $this->requirePerm('stock.edit');

        $productId = (int) $this->input('product_id', 0);
        $type = $this->input('type') === 'out' ? 'out' : 'in';
        $qty = $this->qty('qty');
        $note = mb_substr((string) $this->input('note', ''), 0, 150);

        if (!$productId || !Database::one('SELECT id FROM products WHERE id = ?', [$productId])) {
            flash_set('error', 'کالا را انتخاب کنید.');
            $this->redirect('stock/adjust');
        }
        if ($qty <= 0) {
            flash_set('error', 'مقدار اصلاح باید بزرگ‌تر از صفر باشد.');
            $this->redirect('stock/adjust');
        }

        InventoryService::move(
            $productId,
            InventoryService::defaultWarehouseId(),
            $type,
            $qty,
            0.0,
            'adjustment',
            null,
            'اصلاح موجودی: ' . $note,
            $this->currentUserId()
        );

        flash_set('success', 'اصلاح موجودی با موفقیت ثبت شد.');
        $this->redirect('stock/kardex');
    }

    public function transferAction(): void
    {
        $this->requirePerm('stock.edit');
        $this->view('stock/transfer', [
            'title' => 'انتقال بین انبارها',
            'products' => Database::all('SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name'),
            'warehouses' => Database::all('SELECT id, name, is_default FROM warehouses ORDER BY id'),
            'preProductId' => (int) $this->input('product_id', 0),
        ]);
    }

    public function storeTransferAction(): void
    {
        $this->requirePerm('stock.edit');

        $productId = (int) $this->input('product_id', 0);
        $fromId = (int) $this->input('from_warehouse_id', 0);
        $toId = (int) $this->input('to_warehouse_id', 0);
        $qty = $this->qty('qty');
        $note = mb_substr((string) $this->input('note', ''), 0, 120);

        if (!$productId || !Database::one('SELECT id FROM products WHERE id = ?', [$productId])) {
            flash_set('error', 'کالا را انتخاب کنید.');
            $this->redirect('stock/transfer');
        }
        if ($qty <= 0) {
            flash_set('error', 'مقدار انتقال باید بزرگ‌تر از صفر باشد.');
            $this->redirect('stock/transfer');
        }
        if ($fromId === $toId) {
            flash_set('error', 'انبار مبدأ و مقصد نمی‌توانند یکسان باشند.');
            $this->redirect('stock/transfer');
        }
        $from = Database::one('SELECT id, name FROM warehouses WHERE id = ?', [$fromId]);
        $to = Database::one('SELECT id, name FROM warehouses WHERE id = ?', [$toId]);
        if (!$from || !$to) {
            flash_set('error', 'انبار مبدأ یا مقصد نامعتبر است.');
            $this->redirect('stock/transfer');
        }

        $userId = $this->currentUserId();

        // خروج از انبار مبدأ
        InventoryService::move(
            $productId,
            $fromId,
            'transfer_out',
            $qty,
            0.0,
            'transfer',
            null,
            'انتقال به «' . $to['name'] . '»: ' . $note,
            $userId
        );

        // ورود به انبار مقصد
        InventoryService::move(
            $productId,
            $toId,
            'transfer_in',
            $qty,
            0.0,
            'transfer',
            null,
            'انتقال از «' . $from['name'] . '»: ' . $note,
            $userId
        );

        flash_set('success', 'انتقال موجودی با موفقیت ثبت شد (خروج از «' . $from['name'] . '» و ورود به «' . $to['name'] . '»).');
        $this->redirect('stock/kardex');
    }

    private function qty(string $key): float
    {
        $raw = str_replace(',', '', fa_num_reverse((string) $this->input($key, '0')));
        return (float) $raw;
    }

    private function currentUserId(): ?int
    {
        $u = Auth::user();
        return $u ? (int) $u['id'] : null;
    }

    private function warehouseName(int $id): string
    {
        $w = Database::one('SELECT name FROM warehouses WHERE id = ?', [$id]);
        return $w ? (string) $w['name'] : 'انبار مرکزی';
    }
}
