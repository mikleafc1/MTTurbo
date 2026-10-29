<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * حریم موجودی انبار (کاردکس) + میانگین موزون هزینه
 */
class InventoryService
{
    public static function defaultWarehouseId(): int
    {
        $id = (int) Database::val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1');
        return $id > 0 ? $id : 1;
    }

    /**
     * ثبت حرکت در کاردکس و بروزرسانی موجودی
     * type: in | out | transfer_in | transfer_out | adjustment
     */
    public static function move(int $productId, int $warehouseId, string $type, float $qty, float $unitCost = 0.0, ?string $refType = null, ?int $refId = null, string $note = '', ?int $userId = null): void
    {
        if ($qty == 0.0) return;
        $sign = in_array($type, ['in', 'transfer_in'], true) ? 1 : -1;
        $delta = $sign * abs($qty);

        $product = Database::one('SELECT * FROM products WHERE id = ?', [$productId]);
        if (!$product) return;

        // هزینه میانگین موزون برای ورودی‌ها
        $avg = (float) $product['purchase_price'];
        if ($type === 'in' && $unitCost > 0) {
            $oldQty = max(0, (float) $product['stock_qty']);
            $oldVal = $oldQty * $avg;
            $newQty = $oldQty + abs($qty);
            $avg = $newQty > 0 ? ($oldVal + abs($qty) * $unitCost) / $newQty : $unitCost;
            Database::update('products', ['purchase_price' => round($avg, 2)], 'id = ?', [$productId]);
            $unitCostForLog = $unitCost;
        } else {
            $unitCostForLog = $unitCost > 0 ? $unitCost : $avg;
        }

        // بروزرسانی موجودی کل کالا (بدون تفکیک انبار در جدول کالا)
        Database::q('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$delta, $productId]);

        Database::insert('stock_movements', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $type,
            'qty' => abs($qty),
            'unit_cost' => round($unitCostForLog, 2),
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $note,
            'user_id' => $userId,
        ]);
    }

    /** سریال شماره سند */
    public static function nextNo(string $prefixKey, string $counterKey, string $padFormat = '%04d'): string
    {
        $prefix = (string) setting($prefixKey, 'D');
        $n = (int) setting($counterKey, '0') + 1;
        Database::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [(string) $n, $counterKey]);
        return $prefix . '-' . sprintf($padFormat, $n);
    }
}
