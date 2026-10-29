<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** دسته‌بندی کالاها (product_categories) */
class CategoryController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('products.view');
        $this->renderIndex(null);
    }

    public function createAction(): void
    {
        $this->requirePerm('products.edit');
        $this->redirect('categories');
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $row = Database::one('SELECT * FROM product_categories WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->renderIndex($row);
    }

    public function storeAction(): void
    {
        $this->requirePerm('products.edit');
        $this->saveCategory(null);
        flash_set('success', 'دسته‌بندی جدید ثبت شد.');
        $this->redirect('categories');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $id = (int) ($args[0] ?? 0);
        if (!Database::one('SELECT id FROM product_categories WHERE id = ?', [$id])) $this->notFound();
        $this->saveCategory($id);
        flash_set('success', 'دسته‌بندی بروزرسانی شد.');
        $this->redirect('categories');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT id, name FROM product_categories WHERE id = ?', [$id]);
        if ($row) {
            $hasProducts = (int) Database::val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]) > 0;
            $hasChildren = (int) Database::val('SELECT COUNT(*) FROM product_categories WHERE parent_id = ?', [$id]) > 0;
            if ($hasProducts) {
                flash_set('error', 'دسته «' . $row['name'] . '» کالا دارد و قابل حذف نیست.');
            } elseif ($hasChildren) {
                flash_set('error', 'دسته «' . $row['name'] . '» زیرگروه دارد و قابل حذف نیست.');
            } else {
                Database::delete('product_categories', 'id = ?', [$id]);
                flash_set('success', 'دسته‌بندی حذف شد.');
            }
        }
        $this->redirect('categories');
    }

    /** نمای لیست + فرم درج/ویرایش درون‌خطی (برای ویرایش، editRow پاس داده می‌شود) */
    private function renderIndex(?array $editRow): void
    {
        $data = $this->paginate('product_categories', '1=1', [], 20, '*', 'id DESC');

        $all = Database::all('SELECT id, name FROM product_categories ORDER BY name');
        $catNames = [];
        foreach ($all as $c) {
            $catNames[(int) $c['id']] = (string) $c['name'];
        }
        $productCounts = [];
        foreach (Database::all('SELECT category_id, COUNT(*) AS cnt FROM products GROUP BY category_id') as $g) {
            $productCounts[(int) $g['category_id']] = (int) $g['cnt'];
        }

        $this->view('categories/index', [
            'title' => 'دسته‌بندی‌ها',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'editRow' => $editRow,
            'allCategories' => $all,
            'catNames' => $catNames,
            'productCounts' => $productCounts,
        ]);
    }

    private function saveCategory(?int $id): void
    {
        $name = (string) $this->input('name', '');
        if (trim($name) === '') {
            flash_set('error', 'نام دسته‌بندی الزامی است.');
            $this->redirect($id ? 'categories/edit/' . $id : 'categories');
        }
        $parentId = (int) $this->input('parent_id', 0);
        if ($parentId > 0 && $id !== null && $parentId === $id) {
            flash_set('error', 'دسته‌بندی نمی‌تواند والد خودش باشد.');
            $this->redirect('categories/edit/' . $id);
        }
        $data = [
            'name' => $name,
            'parent_id' => $parentId > 0 ? $parentId : null,
        ];
        if ($id) {
            Database::update('product_categories', $data, 'id = ?', [$id]);
        } else {
            Database::insert('product_categories', $data);
        }
    }
}
