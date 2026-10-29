<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\InventoryService;

/**
 * ورود/خروج اکسل (CSV)
 * خروجی: CSV با UTF-8 BOM؛ ورودی: آپلود CSV/TXT حداکثر ۲ مگابایت با تشخیص جداکننده , یا ;
 */
class ExcelController extends Controller
{
    private const MAX_UPLOAD = 2 * 1024 * 1024; // 2MB

    /* ---------------- صفحات ---------------- */

    public function indexAction(): void
    {
        $this->requirePerm('excel.view');
        $this->view('excel/index', ['title' => 'ورود/خروج اکسل']);
    }

    public function importProductsAction(): void
    {
        $this->requirePerm('excel.edit');
        $this->importForm('products');
    }

    public function importCustomersAction(): void
    {
        $this->requirePerm('excel.edit');
        $this->importForm('customers');
    }

    /** فرم مشترک بارگذاری */
    private function importForm(string $type): void
    {
        $columns = $type === 'products'
            ? [
                ['کد کالا (SKU)', 'اختیاری؛ کلید تشخیص کالای موجود برای بروزرسانی'],
                ['نام کالا', 'الزامی'],
                ['دسته‌بندی', 'در صورت نبود، دسته ساخته می‌شود'],
                ['واحد', 'پیش‌فرض: عدد'],
                ['قیمت خرید', 'عدد، بدون جداکننده'],
                ['قیمت فروش', 'عدد، بدون جداکننده'],
                ['حداقل موجودی', 'عدد'],
                ['موجودی', 'اختلاف با موجودی فعلی در کاردکس ثبت می‌شود'],
            ]
            : [
                ['نام کامل', 'الزامی'],
                ['موبایل', 'کلید تشخیص مشتری موجود (اولویت اول)'],
                ['تلفن', 'اختیاری'],
                ['ایمیل', 'کلید تشخیص مشتری موجود (اولویت دوم)'],
                ['شهر', 'اختیاری'],
                ['آدرس', 'اختیاری'],
            ];

        $this->view('excel/import', [
            'title'   => $type === 'products' ? 'بارگذاری کالاها از CSV' : 'بارگذاری مشتریان از CSV',
            'type'    => $type,
            'columns' => $columns,
        ]);
    }

    /* ---------------- خروجی CSV ---------------- */

    /** مسیر excel/export/<type> — مسیریاب بخش سوم را به‌عنوان آرگومان می‌دهد */
    public function exportAction(array $args): void
    {
        $this->requirePerm('excel.view');
        $type = (string) ($args[0] ?? '');
        match ($type) {
            'products'  => $this->exportProductsAction(),
            'customers' => $this->exportCustomersAction(),
            'sales'     => $this->exportSalesAction(),
            'inventory' => $this->exportInventoryAction(),
            default     => $this->notFound(),
        };
    }

    public function exportProductsAction(): void
    {
        $this->requirePerm('excel.view');
        $rows = Database::all(
            "SELECT p.sku, p.name, COALESCE(c.name, '') AS category, p.unit,
                    p.purchase_price, p.sale_price, p.min_stock, p.stock_qty
             FROM products p LEFT JOIN product_categories c ON c.id = p.category_id
             ORDER BY p.id"
        );
        $this->csvStream('products.csv', ['کد کالا (SKU)', 'نام کالا', 'دسته‌بندی', 'واحد', 'قیمت خرید', 'قیمت فروش', 'حداقل موجودی', 'موجودی'], $rows);
    }

    public function exportCustomersAction(): void
    {
        $this->requirePerm('excel.view');
        $rows = Database::all(
            "SELECT full_name, mobile, phone, email, city, address FROM customers ORDER BY id"
        );
        $this->csvStream('customers.csv', ['نام کامل', 'موبایل', 'تلفن', 'ایمیل', 'شهر', 'آدرس'], $rows);
    }

    public function exportSalesAction(): void
    {
        $this->requirePerm('excel.view');

        $where = "i.invoice_type = 'sale' AND i.status <> 'canceled'";
        $params = [];
        $from = trim((string) $this->input('from', ''));
        $to = trim((string) $this->input('to', ''));
        if ($from !== '' && ($g = jinput_to_gregorian($from)) !== null) {
            $where .= ' AND i.invoice_date >= ?';
            $params[] = $g;
        }
        if ($to !== '' && ($g = jinput_to_gregorian($to)) !== null) {
            $where .= ' AND i.invoice_date <= ?';
            $params[] = $g;
        }

        $rows = Database::all(
            "SELECT i.invoice_no, i.invoice_date, COALESCE(c.full_name, '') AS customer,
                    i.status, COALESCE(i.payment_method, '') AS payment_method,
                    i.subtotal, i.discount, i.tax_amount, i.total, i.paid_amount
             FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
             WHERE $where ORDER BY i.invoice_date, i.id",
            $params
        );
        $this->csvStream(
            'sales.csv',
            ['شماره فاکتور', 'تاریخ (میلادی)', 'مشتری', 'وضعیت', 'روش پرداخت', 'جمع کل', 'تخفیف', 'مالیات', 'مبلغ نهایی', 'پرداخت شده'],
            $rows
        );
    }

    public function exportInventoryAction(): void
    {
        $this->requirePerm('excel.view');
        $rows = Database::all(
            "SELECT p.sku, p.name, p.unit, p.stock_qty, p.min_stock,
                    p.purchase_price, p.sale_price, (p.stock_qty * p.purchase_price) AS stock_value
             FROM products p ORDER BY p.name"
        );
        $this->csvStream(
            'inventory.csv',
            ['کد کالا (SKU)', 'نام کالا', 'واحد', 'موجودی', 'حداقل موجودی', 'قیمت خرید', 'قیمت فروش', 'ارزش موجودی'],
            $rows
        );
    }

    /** فایل نمونه: excel/sample/<type> */
    public function sampleAction(array $args): void
    {
        $this->requirePerm('excel.view');
        $type = (string) ($args[0] ?? '');
        if ($type === 'products') {
            $this->csvStream('sample-products.csv', ['کد کالا (SKU)', 'نام کالا', 'دسته‌بندی', 'واحد', 'قیمت خرید', 'قیمت فروش', 'حداقل موجودی', 'موجودی'], [
                ['TSHIRT-1', 'تیشرت مردانه', 'پوشاک', 'عدد', '180000', '250000', '5', '42'],
                ['MUG-01', 'لیوان سرامیکی', 'سرویس آشپزخانه', 'عدد', '60000', '95000', '10', '25'],
            ]);
        }
        if ($type === 'customers') {
            $this->csvStream('sample-customers.csv', ['نام کامل', 'موبایل', 'تلفن', 'ایمیل', 'شهر', 'آدرس'], [
                ['علی رضایی', '09121234567', '02155443322', 'ali@example.com', 'تهران', 'خیابان آزادی، پلاک ۱۲'],
                ['فروشگاه نمونه', '09351112233', '', 'shop@example.com', 'اصفهان', 'خیابان چهارباغ'],
            ]);
        }
        $this->notFound();
    }

    /* ---------------- ورود CSV: کالاها ---------------- */

    public function storeImportProductsAction(): void
    {
        $this->requirePerm('excel.edit');
        $path = $this->uploadedCsvPath();
        if ($path === null) {
            $this->redirect('excel/import-products');
        }

        try {
            $handle = fopen($path, 'r');
            if (!$handle) throw new \RuntimeException('فایل قابل خواندن نیست.');
            $delim = $this->detectDelimiter($handle);
            $created = 0; $updated = 0; $skipped = 0;
            $isHeader = true;

            while (($row = fgetcsv($handle, null, $delim)) !== false) {
                if ($row === [null] || count($row) === 1 && trim((string) $row[0]) === '') continue;
                $row = array_map(fn($c) => trim((string) ($c ?? '')), $row);

                if ($isHeader) {
                    $isHeader = false;
                    $first = $row[0] ?? '';
                    if (mb_strpos($first, 'نام') !== false || mb_strpos($first, 'کد') !== false || mb_strpos($first, 'قیمت') !== false) {
                        continue; // ردیف سرستون
                    }
                }

                $sku = $row[0] ?? '';
                $name = $row[1] ?? '';
                if ($name === '') { $skipped++; continue; }

                $categoryId = ($row[2] ?? '') !== '' ? $this->ensureCategory($row[2]) : null;
                $unit = ($row[3] ?? '') !== '' ? $row[3] : 'عدد';
                $purchasePrice = $this->parseNum($row[4] ?? '0');
                $salePrice = $this->parseNum($row[5] ?? '0');
                $minStock = $this->parseNum($row[6] ?? '0');
                $stockQty = $this->parseNum($row[7] ?? '0');

                $existing = $sku !== '' ? Database::one('SELECT * FROM products WHERE sku = ?', [$sku]) : null;

                if ($existing) {
                    Database::update('products', [
                        'name' => $name,
                        'category_id' => $categoryId,
                        'unit' => $unit,
                        'purchase_price' => $purchasePrice,
                        'sale_price' => $salePrice,
                        'min_stock' => $minStock,
                    ], 'id = ?', [$existing['id']]);
                    // اختلاف موجودی فقط از طریق کاردکس (InventoryService)
                    $delta = $stockQty - (float) $existing['stock_qty'];
                    if (abs($delta) >= 0.001) {
                        InventoryService::move(
                            (int) $existing['id'], InventoryService::defaultWarehouseId(),
                            $delta > 0 ? 'in' : 'out', abs($delta), 0,
                            'import', null, 'ورود اکسل — اصلاح موجودی'
                        );
                    }
                    $updated++;
                } else {
                    $id = Database::insert('products', [
                        'sku' => $sku !== '' ? $sku : null,
                        'name' => $name,
                        'category_id' => $categoryId,
                        'unit' => $unit,
                        'purchase_price' => $purchasePrice,
                        'sale_price' => $salePrice,
                        'min_stock' => $minStock,
                        'stock_qty' => 0,
                        'is_active' => 1,
                        'source' => 'excel',
                    ]);
                    if ($stockQty > 0) {
                        InventoryService::move(
                            $id, InventoryService::defaultWarehouseId(),
                            'in', $stockQty, $purchasePrice,
                            'import', null, 'ورود اکسل — موجودی اولیه'
                        );
                    }
                    $created++;
                }
            }
            fclose($handle);
            flash_set('success', "ورود کالاها انجام شد: " . fa_num($created) . ' ایجاد، ' . fa_num($updated) . ' بروزرسانی، ' . fa_num($skipped) . ' رد شد.');
        } catch (\Throwable $e) {
            error_log('[DARYA excel] ' . $e->getMessage());
            flash_set('error', 'خطا در پردازش فایل: ' . $e->getMessage());
        } finally {
            @unlink($path);
        }
        $this->redirect('excel');
    }

    /* ---------------- ورود CSV: مشتریان ---------------- */

    public function storeImportCustomersAction(): void
    {
        $this->requirePerm('excel.edit');
        $path = $this->uploadedCsvPath();
        if ($path === null) {
            $this->redirect('excel/import-customers');
        }

        try {
            $handle = fopen($path, 'r');
            if (!$handle) throw new \RuntimeException('فایل قابل خواندن نیست.');
            $delim = $this->detectDelimiter($handle);
            $created = 0; $updated = 0; $skipped = 0;
            $isHeader = true;

            while (($row = fgetcsv($handle, null, $delim)) !== false) {
                if ($row === [null] || count($row) === 1 && trim((string) $row[0]) === '') continue;
                $row = array_map(fn($c) => trim((string) ($c ?? '')), $row);

                if ($isHeader) {
                    $isHeader = false;
                    $first = $row[0] ?? '';
                    if (mb_strpos($first, 'نام') !== false) {
                        continue; // ردیف سرستون
                    }
                }

                $fullName = $row[0] ?? '';
                $mobile = $row[1] ?? '';
                $phone = $row[2] ?? '';
                $email = $row[3] ?? '';
                $city = $row[4] ?? '';
                $address = $row[5] ?? '';
                if ($fullName === '') { $skipped++; continue; }
                if ($mobile === '' && $email === '') { $skipped++; continue; } // بدون کلید تشخیص

                $existing = null;
                if ($mobile !== '') $existing = Database::one('SELECT * FROM customers WHERE mobile = ?', [$mobile]);
                if (!$existing && $email !== '') $existing = Database::one('SELECT * FROM customers WHERE email = ?', [$email]);

                if ($existing) {
                    Database::update('customers', [
                        'full_name' => $fullName,
                        'mobile' => $mobile !== '' ? $mobile : $existing['mobile'],
                        'phone' => $phone !== '' ? $phone : $existing['phone'],
                        'email' => $email !== '' ? $email : $existing['email'],
                        'city' => $city !== '' ? $city : $existing['city'],
                        'address' => $address !== '' ? $address : $existing['address'],
                    ], 'id = ?', [$existing['id']]);
                    $updated++;
                } else {
                    Database::insert('customers', [
                        'code' => 'C-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM customers') + 1), 5, '0', STR_PAD_LEFT),
                        'full_name' => $fullName,
                        'mobile' => $mobile !== '' ? $mobile : null,
                        'phone' => $phone !== '' ? $phone : null,
                        'email' => $email !== '' ? $email : null,
                        'city' => $city !== '' ? $city : null,
                        'address' => $address !== '' ? $address : null,
                        'source' => 'excel',
                    ]);
                    $created++;
                }
            }
            fclose($handle);
            flash_set('success', "ورود مشتریان انجام شد: " . fa_num($created) . ' ایجاد، ' . fa_num($updated) . ' بروزرسانی، ' . fa_num($skipped) . ' رد شد.');
        } catch (\Throwable $e) {
            error_log('[DARYA excel] ' . $e->getMessage());
            flash_set('error', 'خطا در پردازش فایل: ' . $e->getMessage());
        } finally {
            @unlink($path);
        }
        $this->redirect('excel');
    }

    /* ---------------- helpers ---------------- */

    /** خروجی CSV با UTF-8 BOM (بدون لایه قالب) */
    private function csvStream(string $filename, array $headers, array $rows): never
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, array_values((array) $r));
        }
        fclose($out);
        exit;
    }

    /** اعتبارسنجی آپلود و انتقال به فایل موقت؛ null = خطا (فلش ثبت شده) */
    private function uploadedCsvPath(): ?string
    {
        $f = $_FILES['csv_file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_set('error', 'فایلی بارگذاری نشد یا بارگذاری ناموفق بود.');
            return null;
        }
        if ((int) $f['size'] > self::MAX_UPLOAD) {
            flash_set('error', 'حجم فایل باید کمتر از ۲ مگابایت باشد.');
            return null;
        }
        $ext = strtolower((string) pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            flash_set('error', 'فقط فایل با پسوند CSV یا TXT مجاز است.');
            return null;
        }
        $isUploaded = is_uploaded_file((string) $f['tmp_name']);
        $path = tempnam(sys_get_temp_dir(), 'darya_csv_');
        if (!$isUploaded || !$path || !@move_uploaded_file((string) $f['tmp_name'], $path)) {
            flash_set('error', 'انتقال فایل بارگذاری‌شده ناموفق بود.');
            return null;
        }
        return $path;
    }

    /** تشخیص جداکننده بر اساس خط اول (پشتیبانی از ; و ,) */
    private function detectDelimiter($handle): string
    {
        $pos = ftell($handle);
        $line = (string) fgets($handle);
        if ($pos !== false) fseek($handle, $pos);
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /** تبدیل سلول عددی فارسی/انگلیسی با جداکننده هزارگان */
    private function parseNum(string $v): float
    {
        $v = str_replace([',', '،', '٬', ' '], '', fa_num_reverse(trim($v)));
        return (float) ($v === '' ? '0' : $v);
    }

    private function ensureCategory(string $name): int
    {
        $row = Database::one('SELECT id FROM product_categories WHERE name = ?', [$name]);
        if ($row) return (int) $row['id'];
        return Database::insert('product_categories', ['name' => $name]);
    }
}
