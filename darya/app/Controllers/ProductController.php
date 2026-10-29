<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** مدیریت کالاها (کد، دسته، قیمت، موجودی) */
class ProductController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('products.view');

        $q = (string) $this->input('q', '');
        $cat = (int) $this->input('cat', 0);
        $low = ((int) $this->input('low', 0)) === 1 ? 1 : 0;

        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR sku LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($cat > 0) {
            $where .= ' AND category_id = ?';
            $params[] = $cat;
        }
        if ($low === 1) {
            $where .= ' AND stock_qty <= min_stock';
        }

        $data = $this->paginate('products', $where, $params, 20, '*', 'id DESC');

        $categories = Database::all('SELECT id, name FROM product_categories ORDER BY name');
        $catNames = [];
        foreach ($categories as $c) {
            $catNames[(int) $c['id']] = (string) $c['name'];
        }

        $this->view('products/index', [
            'title' => 'کالاها',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'q' => $q,
            'cat' => $cat,
            'low' => $low,
            'categories' => $categories,
            'catNames' => $catNames,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('products.edit');
        $this->view('products/form', [
            'title' => 'کالای جدید',
            'row' => null,
            'categories' => Database::all('SELECT id, name FROM product_categories ORDER BY name'),
        ]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $row = Database::one('SELECT * FROM products WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->view('products/form', [
            'title' => 'ویرایش کالا',
            'row' => $row,
            'categories' => Database::all('SELECT id, name FROM product_categories ORDER BY name'),
        ]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('products.edit');
        $id = $this->saveProduct(null);
        flash_set('success', 'کالای جدید ثبت شد.');
        $this->redirect('products');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $id = (int) ($args[0] ?? 0);
        if (!Database::one('SELECT id FROM products WHERE id = ?', [$id])) $this->notFound();
        $this->saveProduct($id);
        flash_set('success', 'اطلاعات کالا بروزرسانی شد.');
        $this->redirect('products');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('products.manage');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT id, name FROM products WHERE id = ?', [$id]);
        if ($row) {
            $hasMovement = (int) Database::val('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?', [$id]) > 0;
            $hasInvoiceItem = (int) Database::val('SELECT COUNT(*) FROM invoice_items WHERE product_id = ?', [$id]) > 0;
            if ($hasMovement || $hasInvoiceItem) {
                flash_set('error', 'کالای «' . $row['name'] . '» در کاردکس یا فاکتورها استفاده شده و قابل حذف نیست (می‌توانید آن را غیرفعال کنید).');
            } else {
                Database::delete('products', 'id = ?', [$id]);
                flash_set('success', 'کالا حذف شد.');
            }
        }
        $this->redirect('products');
    }

    private function saveProduct(?int $id): int
    {
        $name = (string) $this->input('name', '');
        $back = $id ? 'products/edit/' . $id : 'products/create';
        if (trim($name) === '') {
            flash_set('error', 'نام کالا الزامی است.');
            $this->redirect($back);
        }

        $sku = (string) $this->input('sku', '');
        $skuVal = null;
        if ($sku !== '') {
            $dupSql = 'SELECT id FROM products WHERE sku = ?' . ($id ? ' AND id <> ?' : '');
            $dupParams = $id ? [$sku, $id] : [$sku];
            if (Database::val($dupSql, $dupParams)) {
                flash_set('error', 'این کد کالا (SKU) قبلاً ثبت شده است.');
                $this->redirect($back);
            }
            $skuVal = $sku;
        }

        $unit = (string) $this->input('unit', 'عدد');

        $data = [
            'sku' => $skuVal,
            'name' => $name,
            'category_id' => (int) $this->input('category_id', 0) ?: null,
            'unit' => trim($unit) !== '' ? $unit : 'عدد',
            'purchase_price' => $this->numericField('purchase_price'),
            'sale_price' => $this->numericField('sale_price'),
            'min_stock' => $this->numericField('min_stock'),
            'description' => $this->input('description') ?: null,
            'is_active' => $this->input('is_active') ? 1 : 0,
        ];

        if ($id) {
            Database::update('products', $data, 'id = ?', [$id]);
            return $id;
        }
        return Database::insert('products', $data);
    }

    /** پارس فیلدهای عددی/ریالی که با فرمت سه‌رقمی (کاما) ارسال می‌شوند */
    private function numericField(string $key): float
    {
        return (float) str_replace(',', '', (string) $this->input($key, '0'));
    }
}
