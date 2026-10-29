# قواعد کدنویسی داریا (CONVENTIONS) — برای همه ماژول‌ها

هر کنترلر/نمای جدید **موظف است** از همین الگوها پیروی کند. قبل از نوشتن، فایل‌های مرجع را بخوانید:
- `darya/app/Controllers/CustomersController.php` (الگوی نمونه CRUD)
- `darya/app/Views/customers/index.php` و `form.php` و `view.php`
- `darya/app/Core/Controller.php` (متدهای پایه: view, json, redirect, input, requirePerm, paginate)
- `darya/app/Core/helpers.php` (توابع: e, url, asset, money, fa_num, jdate, jinput_to_gregorian, setting, csrf_field, can, status_label, icon_svg)
- `darya/app/Services/InventoryService.php` (حرکت انبار: move(), nextNo())
- `darya/app/Services/AccountingService.php` (ثبت سند: postVoucher, voucherForInvoice, voucherForTransaction, voucherForCogs)
- `darya/database/schema.sql` (ساختار دقیق جداول — هیچ جدول جدیدی نساز)

## قواعد

1. **PHP 8.1 سخت‌گیرانه**، declare(strict_types=1)، فضای نام `App\Controllers`.
2. نام کنترلر: `ProductController` در `app/Controllers/`، اکشن‌ها: `indexAction`, `createAction`, `editAction`, `storeAction`, `updateAction`, `viewAction`, `deleteAction`.
3. مسیرها خودکار تشخیص داده می‌شوند: `products/edit/5` ← `ProductController::editAction(['5'])`. پارامتر در `$args[0]`.
4. شروع هر اکسل محافظت‌شده: `$this->requirePerm('module.perm');`
5. نماها: `$this->view('products/index', [...], 'app');` و متغیر `$title` داخل دیتای نما. فایل نما: `app/Views/<module>/<action>.php`.
6. همه فرم‌ها: `method="post"` + `<?= csrf_field() ?>` (روتر خودش CSRF را برای POST احراز می‌کند).
7. خروجی: **همیشه** `e()` برای متن؛ `money($n)` برای مبلغ؛ `jdate($date)` برای تاریخ. تاریخ ورودی فرم شمسی است → با `jinput_to_gregorian($input)` به میلادی تبدیل و ذخیره شود (NULL اگر نامعتبر؛ در آن صورت خطای اعتبارسنجی).
8. حذف: فرم POST با `data-confirm="مطمئن هستید؟"` و اکشن `delete`.
9. جستجو/صفحه‌بندی: از `$this->paginate('table', $where, $params, 20, 'SELECT...', 'ORDER...')` و پارشال `views/partials/pagination.php` (بخوانید از customers/index.php).
10. وضعیت‌ها با `status_label($status)` نمایش داده شوند.
11. موجودی: فقط از `InventoryService::move()` — هرگز مستقیم `stock_qty` را آپدیت نکنید.
12. اسناد حسابداری خودکار: فقط از `AccountingService`.
13. مانده مشتری (`customers.balance`): بعد از هر تغییر فاکتور/دریافت، `SyncService::refreshCustomerBalance($customerId)` صدا زده شود. مانده مثبت = بدهی مشتری به ما.
14. **کاملاً محلی**: هیچ CDN، فونت اینترنتی یا کتابخانه خارجی. استایل با `assets/css/app.css` موجود؛ کلاس‌های آماده: card, btn, btn-primary, btn-outline, btn-sm, btn-danger, table, form-input, form-label, form-row, form-actions, badge, alert, stat-grid, grid-2, page-head, search-bar, item-row, table-wrap, pagination, hint-box, muted, text-danger, text-amber, empty.
15. آیکون‌ها با `icon_svg('name')`.
16. در نمای فرم، مقدارهای قدیمی را با `$_POST['field'] ?? $row['field'] ?? ''` پر کنید.
17. لینک برگشت در بالای فرم‌ها به لیست همان ماژول.
