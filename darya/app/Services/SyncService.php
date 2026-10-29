<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Api\ApiError;

/**
 * اجراکننده REST API همگام‌سازی
 * مسیرها: v1/ping, v1/products/upsert, v1/orders, v1/customers/upsert,
 *         v1/users/sync, v1/abandoned-carts, v1/abandoned-carts/convert,
 *         v1/role-map, v1/sync/run, v1/sync-log
 */
class SyncService
{
    public static function route(string $path, array $body, array $keyRow): array
    {
        if ((int) setting('sync_enabled', '1') !== 1 && $path !== 'v1/ping') {
            throw new ApiError(403, 'sync_disabled', 'همگام‌سازی در تنظیمات غیرفعال است');
        }

        switch ($path) {
            case 'v1/ping':
                return ['pong' => true, 'app' => 'Darya', 'version' => '1.0.0', 'time' => now()];

            case 'v1/products/upsert':
                return self::productsUpsert($body);

            case 'v1/orders':
                return self::orderCreate($body);

            case 'v1/customers/upsert':
                return self::customersUpsert($body);

            case 'v1/users/sync':
                return self::usersRoleSync($body);

            case 'v1/abandoned-carts':
                return self::cartsUpsert($body);

            case 'v1/abandoned-carts/convert':
                return self::cartConvert($body);

            case 'v1/role-map':
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                    return ['map' => json_decode((string) setting('role_map', '{}'), true) ?: []];
                }
                Database::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [json_encode($body['map'] ?? [], JSON_UNESCAPED_UNICODE), 'role_map']);
                self::log('map', 'role_map', 'update', 'ok', 'نقشه نقش‌ها بروزرسانی شد');
                return ['saved' => true];

            case 'v1/sync/run':
                return self::queueRun();

            case 'v1/sync-log':
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                return ['items' => Database::all('SELECT * FROM sync_log ORDER BY id DESC LIMIT ' . $limit)];

            default:
                throw new ApiError(404, 'not_found', 'اندپوینت یافت نشد: ' . $path);
        }
    }

    /* ---------------- Products ---------------- */

    public static function productsUpsert(array $body): array
    {
        $products = $body['products'] ?? [];
        if (!is_array($products) || !$products) throw new ApiError(422, 'invalid', 'آرایه products الزامی است');
        $saved = 0;
        foreach ($products as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') continue;
            $wcId = $p['wc_product_id'] ?? null;
            $existing = null;
            if ($wcId) {
                $existing = Database::one('SELECT * FROM products WHERE wc_product_id = ?', [(int) $wcId]);
            }
            if (!$existing && !empty($p['sku'])) {
                $existing = Database::one('SELECT * FROM products WHERE sku = ?', [(string) $p['sku']]);
            }

            $fields = [
                'name' => $name,
                'sku' => $p['sku'] ?? ($existing['sku'] ?? null),
                'sale_price' => (float) ($p['sale_price'] ?? 0),
                'purchase_price' => (float) ($p['purchase_price'] ?? ($existing['purchase_price'] ?? 0)),
                'stock_qty' => isset($p['stock_qty']) ? (float) $p['stock_qty'] : ($existing['stock_qty'] ?? 0),
                'is_active' => (int) (!isset($p['is_active']) || $p['is_active']),
                'source' => 'woocommerce',
            ];
            if (!empty($p['category'])) {
                $catId = self::ensureCategory((string) $p['category']);
                $fields['category_id'] = $catId;
            }

            if ($existing) {
                Database::update('products', $fields, 'id = ?', [$existing['id']]);
                $pid = (int) $existing['id'];
            } else {
                $fields['wc_product_id'] = $wcId ? (int) $wcId : null;
                $pid = Database::insert('products', $fields);
            }
            $saved++;
            self::log('in', 'product', (string) ($p['sku'] ?? $pid), 'upsert', 'ok', 'کالا همگام شد: ' . $name);
        }
        return ['saved' => $saved];
    }

    private static function ensureCategory(string $name): int
    {
        $row = Database::one('SELECT id FROM product_categories WHERE name = ?', [$name]);
        if ($row) return (int) $row['id'];
        return Database::insert('product_categories', ['name' => $name]);
    }

    /* ---------------- Customers ---------------- */

    public static function customersUpsert(array $body): array
    {
        $list = $body['customers'] ?? [];
        if (!is_array($list)) throw new ApiError(422, 'invalid', 'آرایه customers الزامی است');
        $ids = [];
        foreach ($list as $c) {
            $ids[] = self::upsertCustomer($c);
        }
        return ['ids' => $ids, 'saved' => count($ids)];
    }

    public static function upsertCustomer(array $c): int
    {
        $wpId = $c['wp_user_id'] ?? null;
        $email = $c['email'] ?? null;
        $phone = $c['phone'] ?? ($c['mobile'] ?? null);

        $existing = null;
        if ($wpId) {
            $existing = Database::one('SELECT * FROM customers WHERE wp_user_id = ?', [(int) $wpId]);
        }
        if (!$existing && $email) {
            $existing = Database::one('SELECT * FROM customers WHERE email = ?', [$email]);
        }
        if (!$existing && $phone) {
            $existing = Database::one('SELECT * FROM customers WHERE mobile = ? OR phone = ?', [$phone, $phone]);
        }

        $fields = [
            'full_name' => trim((string) ($c['full_name'] ?? $c['display_name'] ?? 'مشتری')) ?: 'مشتری',
            'company' => $c['company'] ?? null,
            'email' => $email,
            'mobile' => $phone,
            'phone' => $c['phone'] ?? null,
            'address' => $c['address'] ?? null,
            'city' => $c['city'] ?? null,
            'postal_code' => $c['postal_code'] ?? null,
            'source' => $c['source'] ?? 'woocommerce',
        ];
        if (!empty($c['wp_roles'])) {
            $roles = is_array($c['wp_roles']) ? $c['wp_roles'] : [$c['wp_roles']];
            $fields['wp_roles'] = implode(',', $roles);
        }

        if ($existing) {
            Database::update('customers', $fields, 'id = ?', [$existing['id']]);
            return (int) $existing['id'];
        }
        $fields['wp_user_id'] = $wpId ? (int) $wpId : null;
        $fields['code'] = 'C-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM customers') + 1), 5, '0', STR_PAD_LEFT);
        return Database::insert('customers', $fields);
    }

    /* ---------------- نقش‌های کاربران وردپرس (مهم) ---------------- */

    public static function usersRoleSync(array $body): array
    {
        $users = $body['users'] ?? [];
        if (!is_array($users) || !$users) throw new ApiError(422, 'invalid', 'آرایه users الزامی است');
        $map = json_decode((string) setting('role_map', '{}'), true) ?: [];

        $processed = 0; $customersCreated = 0; $usersCreated = 0; $usersUpdated = 0;

        foreach ($users as $u) {
            $wpId = $u['wp_user_id'] ?? null;
            $username = trim((string) ($u['username'] ?? ''));
            if (!$wpId && $username === '') continue;

            $roles = is_array($u['roles'] ?? null) ? $u['roles'] : [];
            $rolesStr = implode(',', $roles);

            // ۱) مشتری/شخص مرتبط را بروز نگه دار
            $existing = null;
            if ($wpId) $existing = Database::one('SELECT * FROM customers WHERE wp_user_id = ?', [(int) $wpId]);
            if (!$existing && !empty($u['email'])) $existing = Database::one('SELECT * FROM customers WHERE email = ?', [$u['email']]);

            if ($existing) {
                Database::update('customers', [
                    'full_name' => trim((string) ($u['display_name'] ?? $existing['full_name'])) ?: $existing['full_name'],
                    'email' => $u['email'] ?? $existing['email'],
                    'mobile' => $u['phone'] ?? $existing['mobile'],
                    'wp_user_id' => $wpId ? (int) $wpId : $existing['wp_user_id'],
                    'wp_roles' => $rolesStr,
                    'source' => 'woocommerce',
                ], 'id = ?', [$existing['id']]);
                $customerId = (int) $existing['id'];
            } else {
                $customerId = Database::insert('customers', [
                    'code' => 'C-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM customers') + 1), 5, '0', STR_PAD_LEFT),
                    'full_name' => trim((string) ($u['display_name'] ?? $username)) ?: $username,
                    'email' => $u['email'] ?? null,
                    'mobile' => $u['phone'] ?? null,
                    'wp_user_id' => $wpId ? (int) $wpId : null,
                    'wp_roles' => $rolesStr,
                    'source' => 'woocommerce',
                ]);
                $customersCreated++;
            }

            // ۲) نگاشت نقش وردپرس → نقش داریا؛ در صورت وجود نگاشت، کاربر داریا ساخته/بروزرسانی می‌شود
            $daryaRoleSlug = null;
            foreach ($roles as $r) {
                if (isset($map[$r]) && $map[$r]) {
                    $daryaRoleSlug = $map[$r];
                    break;
                }
            }

            if ($daryaRoleSlug) {
                $role = Database::one('SELECT * FROM roles WHERE slug = ?', [$daryaRoleSlug]);
                if ($role) {
                    $dUser = Database::one('SELECT * FROM users WHERE username = ? OR email = ?', [$username ?: ('wp_' . $wpId), $u['email'] ?? '@@none@@']);
                    if ($dUser) {
                        Database::update('users', [
                            'role_id' => (int) $role['id'],
                            'full_name' => trim((string) ($u['display_name'] ?? $dUser['full_name'])),
                            'email' => $u['email'] ?? $dUser['email'],
                            'is_active' => 1,
                        ], 'id = ?', [$dUser['id']]);
                        $usersUpdated++;
                    } else {
                        Database::insert('users', [
                            'username' => $username !== '' ? $username : ('wp_' . $wpId),
                            'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
                            'full_name' => trim((string) ($u['display_name'] ?? $username)) ?: $username,
                            'email' => $u['email'] ?? null,
                            'role_id' => (int) $role['id'],
                            'is_active' => 1,
                        ]);
                        $usersCreated++;
                    }
                }
            }

            $processed++;
            self::log('in', 'wp_user', (string) ($username ?: $wpId), 'roles_sync', 'ok', 'نقش‌ها: ' . ($rolesStr ?: '-') . ' ← نگاشت: ' . ($daryaRoleSlug ?? 'ندارد'));
        }

        return [
            'processed' => $processed,
            'customers_created' => $customersCreated,
            'users_created' => $usersCreated,
            'users_updated' => $usersUpdated,
        ];
    }

    /* ---------------- Orders (WooCommerce → فاکتور فروش) ---------------- */

    public static function orderCreate(array $body): array
    {
        $o = $body['order'] ?? null;
        if (!is_array($o)) throw new ApiError(422, 'invalid', 'آبجکت order الزامی است');

        $wcId = (int) ($o['wc_order_id'] ?? 0);
        if (!$wcId) throw new ApiError(422, 'invalid', 'wc_order_id الزامی است');

        $existing = Database::one('SELECT * FROM invoices WHERE wc_order_id = ?', [$wcId]);

        $statusMap = [
            'completed' => 'paid', 'processing' => 'unpaid', 'on-hold' => 'pending',
            'pending' => 'pending', 'cancelled' => 'canceled', 'canceled' => 'canceled',
            'failed' => 'canceled', 'refunded' => 'canceled', 'trash' => 'canceled',
        ];
        $wcStatus = (string) ($o['status'] ?? 'pending');
        $invStatus = $statusMap[$wcStatus] ?? 'pending';
        $wasShipped = false;
        if ($existing) {
            $wasShipped = in_array($existing['status'], ['unpaid', 'partial', 'paid'], true);
        }

        // مشتری
        $customer = $o['customer'] ?? [];
        $customerId = null;
        if ($customer) {
            $customerId = self::upsertCustomer($customer);
        }

        $total = (float) ($o['total'] ?? 0);
        $paid = ($invStatus === 'paid') ? $total : (float) ($o['paid_amount'] ?? 0);

        $fields = [
            'invoice_type' => 'sale',
            'customer_id' => $customerId,
            'invoice_date' => substr((string) ($o['date'] ?? now()), 0, 10),
            'subtotal' => (float) ($o['subtotal'] ?? $total),
            'discount' => (float) ($o['discount'] ?? 0),
            'tax_amount' => (float) ($o['tax'] ?? 0),
            'shipping' => (float) ($o['shipping'] ?? 0),
            'total' => $total,
            'paid_amount' => $paid,
            'status' => $invStatus,
            'payment_method' => $o['payment_method'] ?? null,
            'source' => 'woocommerce',
            'wc_order_id' => $wcId,
            'wc_order_no' => (string) ($o['number'] ?? $wcId),
            'description' => 'سفارش ووکامرس #' . $wcId,
        ];

        if ($existing) {
            Database::update('invoices', $fields, 'id = ?', [$existing['id']]);
            $invoiceId = (int) $existing['id'];
            $invoiceNo = $existing['invoice_no'];
            Database::delete('invoice_items', 'invoice_id = ?', [$invoiceId]);
        } else {
            $fields['invoice_no'] = InventoryService::nextNo('invoice_prefix', 'invoice_counter');
            $invoiceId = Database::insert('invoices', $fields);
            $invoiceNo = $fields['invoice_no'];
        }

        // اقلام
        $items = $o['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $it) {
                $pid = null;
                if (!empty($it['wc_product_id'])) {
                    $pid = Database::val('SELECT id FROM products WHERE wc_product_id = ?', [(int) $it['wc_product_id']]);
                }
                if (!$pid && !empty($it['sku'])) {
                    $pid = Database::val('SELECT id FROM products WHERE sku = ?', [(string) $it['sku']]);
                }
                Database::insert('invoice_items', [
                    'invoice_id' => $invoiceId,
                    'product_id' => $pid ? (int) $pid : null,
                    'description' => (string) ($it['name'] ?? 'کالا'),
                    'qty' => (float) ($it['qty'] ?? 1),
                    'unit_price' => (float) ($it['price'] ?? 0),
                    'total' => (float) ($it['total'] ?? 0),
                ]);
            }
        }

        // موجودی: فقط برای پردازش‌شده/تکمیل‌شده خروج می‌زنیم؛ لغو ← برگشت
        if ($existing && $wasShipped && $invStatus === 'canceled') {
            self::stockForOrder($invoiceId, 'in', 'woocommerce', $wcId, 'برگشت موجودی به‌دلیل لغو سفارش');
        } elseif (!$existing && in_array($invStatus, ['unpaid', 'partial', 'paid'], true)) {
            self::stockForOrder($invoiceId, 'out', 'woocommerce', $wcId, 'خروج موجودی سفارش ووکامرس');
        }

        // سند حسابداری (فقط بار اول)
        if (!$existing && $invStatus !== 'canceled' && (int) setting('sync_enabled', '1') === 1) {
            $invoice = Database::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            AccountingService::voucherForInvoice($invoice);
        }

        // مانده مشتری
        if ($customerId) {
            self::refreshCustomerBalance($customerId);
        }

        self::log('in', 'order', '#' . $wcId, $existing ? 'update' : 'create', 'ok', 'فاکتور ' . $invoiceNo . ' با وضعیت ' . $invStatus);
        return ['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'customer_id' => $customerId, 'status' => $invStatus];
    }

    private static function stockForOrder(int $invoiceId, string $type, string $refType, int $refId, string $note): void
    {
        $items = Database::all('SELECT * FROM invoice_items WHERE invoice_id = ? AND product_id IS NOT NULL', [$invoiceId]);
        foreach ($items as $it) {
            InventoryService::move((int) $it['product_id'], InventoryService::defaultWarehouseId(), $type, (float) $it['qty'], 0, $refType, $refId, $note);
        }
    }

    public static function refreshCustomerBalance(int $customerId): void
    {
        Database::q(
            'UPDATE customers c SET c.balance = COALESCE((SELECT SUM(i.total - i.paid_amount) FROM invoices i WHERE i.customer_id = c.id AND i.status IN ("unpaid","partial","pending")),0) WHERE c.id = ?',
            [$customerId]
        );
    }

    /* ---------------- سبد رهاشده ---------------- */

    public static function cartsUpsert(array $body): array
    {
        $carts = $body['carts'] ?? [];
        if (!is_array($carts)) throw new ApiError(422, 'invalid', 'آرایه carts الزامی است');
        $saved = 0;
        foreach ($carts as $c) {
            $cartId = (string) ($c['cart_id'] ?? '');
            if ($cartId === '') continue;
            $existing = Database::one('SELECT * FROM abandoned_carts WHERE cart_id = ?', [$cartId]);

            $fields = [
                'customer_name' => $c['customer_name'] ?? null,
                'phone' => $c['phone'] ?? null,
                'email' => $c['email'] ?? null,
                'wp_user_id' => !empty($c['wp_user_id']) ? (int) $c['wp_user_id'] : null,
                'wp_user_login' => $c['wp_user_login'] ?? null,
                'wp_roles' => is_array($c['wp_roles'] ?? null) ? implode(',', $c['wp_roles']) : ($c['wp_roles'] ?? null),
                'items' => json_encode($c['items'] ?? [], JSON_UNESCAPED_UNICODE),
                'items_count' => (int) ($c['items_count'] ?? 0),
                'cart_total' => (float) ($c['total'] ?? 0),
                'currency' => (string) ($c['currency'] ?? 'IRT'),
                'status' => (string) ($c['status'] ?? 'abandoned'),
                'abandoned_at' => $c['abandoned_at'] ?? null,
                'last_activity' => $c['last_activity'] ?? null,
            ];

            if ($existing) {
                if (($existing['status'] ?? '') === 'converted') {
                    continue; // سبد تبدیل‌شده را دست نزن
                }
                Database::update('abandoned_carts', $fields, 'id = ?', [$existing['id']]);
            } else {
                Database::insert('abandoned_carts', $fields);
            }
            $saved++;
        }
        self::log('in', 'cart', 'batch', 'upsert', 'ok', "$saved سبد رهاشده ذخیره شد");
        return ['saved' => $saved];
    }

    public static function cartConvert(array $body): array
    {
        $cartId = (string) ($body['cart_id'] ?? '');
        if ($cartId === '') throw new ApiError(422, 'invalid', 'cart_id الزامی است');
        $row = Database::one('SELECT * FROM abandoned_carts WHERE cart_id = ?', [$cartId]);
        if (!$row) throw new ApiError(404, 'not_found', 'سبد یافت نشد');

        Database::update('abandoned_carts', [
            'status' => (string) ($body['status'] ?? 'converted'),
            'converted_wc_order_id' => !empty($body['wc_order_id']) ? (int) $body['wc_order_id'] : null,
            'converted_at' => $body['converted_at'] ?? now(),
        ], 'id = ?', [$row['id']]);

        self::log('in', 'cart', $cartId, 'convert', 'ok', 'سبد به سفارش #' . ($body['wc_order_id'] ?? '-') . ' تبدیل شد');
        return ['updated' => true];
    }

    /* ---------------- صف رویدادها ---------------- */

    public static function queueRun(): array
    {
        $events = Database::all("SELECT * FROM sync_events WHERE status IN ('pending','failed') AND attempts < 5 ORDER BY id ASC LIMIT 100");
        $done = 0; $failed = 0;
        foreach ($events as $ev) {
            Database::q("UPDATE sync_events SET status = 'processing' WHERE id = ?", [$ev['id']]);
            try {
                $payload = json_decode((string) $ev['payload'], true) ?: [];
                switch ($ev['event_type']) {
                    case 'order': self::orderCreate(['order' => $payload]); break;
                    case 'product': self::productsUpsert(['products' => [$payload]]); break;
                    case 'customer': self::customersUpsert(['customers' => [$payload]]); break;
                    case 'wp_user': self::usersRoleSync(['users' => [$payload]]); break;
                    case 'cart': self::cartsUpsert(['carts' => [$payload]]); break;
                    case 'cart_convert': self::cartConvert($payload); break;
                    default: throw new ApiError(422, 'unknown_event', 'رویداد ناشناخته: ' . $ev['event_type']);
                }
                Database::q("UPDATE sync_events SET status = 'done', processed_at = ?, last_error = NULL WHERE id = ?", [now(), $ev['id']]);
                $done++;
            } catch (\Throwable $e) {
                Database::q(
                    "UPDATE sync_events SET status = 'failed', attempts = attempts + 1, last_error = ? WHERE id = ?",
                    [mb_substr($e->getMessage(), 0, 500), $ev['id']]
                );
                $failed++;
            }
        }
        return ['processed' => $done, 'failed' => $failed];
    }

    /* ---------------- لاگ ---------------- */

    public static function log(string $direction, string $entityType, string $entityRef, string $action, string $status, string $message = '', $payload = null): void
    {
        try {
            Database::insert('sync_log', [
                'direction' => $direction,
                'entity_type' => $entityType,
                'entity_ref' => mb_substr($entityRef, 0, 80),
                'action' => $action,
                'status' => $status,
                'message' => mb_substr($message, 0, 1000),
                'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[DARYA sync_log] ' . $e->getMessage());
        }
    }
}
