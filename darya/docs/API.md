# API همگام‌سازی داریا v1 — قرارداد (افزونه ووکامرس + نرم‌افزار ویندوزی)

## آدرس پایه
```
{DARYA_URL}/api/index.php?path=v1/<endpoint>
```
احراز: هدر `X-Api-Key: dk_...` (یا `&api_key=dk_...`)
پاسخ موفق: `{"ok": true, "data": {...}}`
پاسخ خطا: `{"ok": false, "error": {"code": "...", "message": "..."}}`

## اندپوینت‌ها

| متد | اندپوینت | بدنه/پارامتر | پاسخ |
|---|---|---|---|
| GET/POST | `v1/ping` | — | `{pong, app, version, time}` |
| POST | `v1/products/upsert` | `{"products":[{...}]}` | `{saved}` |
| POST | `v1/orders` | `{"order":{...}}` | `{invoice_id, invoice_no, customer_id, status}` |
| POST | `v1/customers/upsert` | `{"customers":[{...}]}` | `{ids, saved}` |
| POST | `v1/users/sync` | `{"users":[{...}]}` | `{processed, customers_created, users_created, users_updated}` |
| POST | `v1/abandoned-carts` | `{"carts":[{...}]}` | `{saved}` |
| POST | `v1/abandoned-carts/convert` | `{cart_id, wc_order_id, converted_at, status}` | `{updated}` |
| GET | `v1/role-map` | — | `{map:{wp_role: darya_role|null}}` |
| POST | `v1/role-map` | `{"map":{...}}` | `{saved}` |
| POST | `v1/sync/run` | — | `{processed, failed}` |
| GET | `v1/sync-log?limit=50` | — | `{items:[...]}` |

## ساختار آبجکت‌ها

### product
```json
{"wc_product_id": 123, "sku": "TSHIRT-1", "name": "تیشرت مردانه", "category": "پوشاک",
 "sale_price": 250000, "purchase_price": 180000, "stock_qty": 42, "is_active": 1}
```

### order (سفارش ووکامرس → فاکتور فروش)
```json
{"order": {
  "wc_order_id": 55, "number": "#55", "status": "processing", "date": "2025-08-01 10:00:00",
  "payment_method": "cod", "subtotal": 500000, "discount": 0, "tax": 45000, "shipping": 40000, "total": 585000,
  "customer": {"wp_user_id": 7, "username": "ali", "display_name": "علی رضایی", "email": "a@b.c", "phone": "0912...", "address": "...", "roles": ["customer"]},
  "items": [{"wc_product_id": 123, "sku": "TSHIRT-1", "name": "تیشرت مردانه", "qty": 2, "price": 250000, "total": 500000}]
}}
```
نگاشت وضعیت: completed→paid، processing→unpaid (خروج از انبار)، on-hold/pending→pending، canceled/failed/refunded→canceled (برگشت موجودی).

### user (همگام‌سازی نقش کاربر وردپرس — مهم)
```json
{"users": [{"wp_user_id": 7, "username": "ali", "email": "a@b.c",
  "display_name": "علی رضایی", "roles": ["customer", "vip"], "phone": "0912..."}]}
```
رفتار: ۱) مشتری متناظر ساخته/بروزرسانی می‌شود (فیلدهای wp_user_id و wp_roles). ۲) نقش وردپرس بر اساس `role-map` به نقش داریا نگاشت و در صورت وجود نگاشت، **کاربر داریا** (با دسترسی) ساخته یا نقشش بروزرسانی می‌شود.

### cart (سبد رهاشده)
```json
{"carts": [{"cart_id": "sess_abc", "customer_name": "سارا", "phone": "09...", "email": "s@b.c",
  "wp_user_id": 12, "wp_user_login": "sara", "wp_roles": ["customer"],
  "items": [{"name": "تیشرت", "qty": 1, "price": 250000}], "items_count": 1, "total": 250000,
  "currency": "IRT", "status": "abandoned", "abandoned_at": "ISO8601", "last_activity": "ISO8601"}]}
```
status: open (فعال)، abandoned (رهاشده)، converted (تبدیل به سفارش).

## اندپوینت‌های افزونه (نرم‌افزار ویندوزی ← وردپرس)
```
GET/POST {WP_URL}/wp-json/darya-bridge/v1/status?secret=SECRET
POST     {WP_URL}/wp-json/darya-bridge/v1/run-sync?secret=SECRET
GET      {WP_URL}/wp-json/darya-bridge/v1/export/products?page=1&per_page=50&secret=SECRET
GET      {WP_URL}/wp-json/darya-bridge/v1/export/orders?since=ISO8601&page=1&per_page=20&secret=SECRET
GET      {WP_URL}/wp-json/darya-bridge/v1/export/carts?since=ISO8601&secret=SECRET
GET      {WP_URL}/wp-json/darya-bridge/v1/export/roles?page=1&per_page=100&secret=SECRET
```
پاسخ: `{"ok": true, "items": [...], "page": 1, "total_pages": 3}`
