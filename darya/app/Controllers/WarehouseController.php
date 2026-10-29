<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** مدیریت انبارها (warehouses) */
class WarehouseController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('products.view');
        $this->renderIndex(null);
    }

    public function createAction(): void
    {
        $this->requirePerm('products.edit');
        $this->redirect('warehouses');
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $row = Database::one('SELECT * FROM warehouses WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->renderIndex($row);
    }

    public function storeAction(): void
    {
        $this->requirePerm('products.edit');
        $this->saveWarehouse(null);
        flash_set('success', 'انبار جدید ثبت شد.');
        $this->redirect('warehouses');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $id = (int) ($args[0] ?? 0);
        if (!Database::one('SELECT id FROM warehouses WHERE id = ?', [$id])) $this->notFound();
        $this->saveWarehouse($id);
        flash_set('success', 'اطلاعات انبار بروزرسانی شد.');
        $this->redirect('warehouses');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('products.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT id, name, is_default FROM warehouses WHERE id = ?', [$id]);
        if ($row) {
            if ((int) $row['is_default'] === 1) {
                flash_set('error', 'انبار پیش‌فرض قابل حذف نیست. ابتدا انبار دیگری را پیش‌فرض کنید.');
            } elseif ((int) Database::val('SELECT COUNT(*) FROM stock_movements WHERE warehouse_id = ?', [$id]) > 0) {
                flash_set('error', 'انبار «' . $row['name'] . '» حرکت موجودی ثبت‌شده در کاردکس دارد و قابل حذف نیست.');
            } else {
                Database::delete('warehouses', 'id = ?', [$id]);
                flash_set('success', 'انبار حذف شد.');
            }
        }
        $this->redirect('warehouses');
    }

    /** نمای لیست + فرم درج/ویرایش درون‌خطی */
    private function renderIndex(?array $editRow): void
    {
        $data = $this->paginate('warehouses', '1=1', [], 20, '*', 'id DESC');

        $movementCounts = [];
        foreach (Database::all('SELECT warehouse_id, COUNT(*) AS cnt FROM stock_movements GROUP BY warehouse_id') as $g) {
            $movementCounts[(int) $g['warehouse_id']] = (int) $g['cnt'];
        }

        $this->view('warehouses/index', [
            'title' => 'انبارها',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'editRow' => $editRow,
            'movementCounts' => $movementCounts,
        ]);
    }

    private function saveWarehouse(?int $id): void
    {
        $name = (string) $this->input('name', '');
        $location = (string) $this->input('location', '');
        $isDefault = $this->input('is_default') ? 1 : 0;
        $back = $id ? 'warehouses/edit/' . $id : 'warehouses';

        if (trim($name) === '') {
            flash_set('error', 'نام انبار الزامی است.');
            $this->redirect($back);
        }

        // حداقل یک انبار پیش‌فرض باید باقی بماند
        if ($id && $isDefault === 0) {
            $current = Database::one('SELECT is_default FROM warehouses WHERE id = ?', [$id]);
            if ($current && (int) $current['is_default'] === 1) {
                $others = (int) Database::val('SELECT COUNT(*) FROM warehouses WHERE is_default = 1 AND id <> ?', [$id]);
                if ($others === 0) {
                    flash_set('error', 'حداقل یک انبار باید پیش‌فرض باشد.');
                    $this->redirect($back);
                }
            }
        }

        if ($isDefault === 1) {
            Database::update('warehouses', ['is_default' => 0], 'is_default = 1');
        }

        $data = [
            'name' => $name,
            'location' => trim($location) !== '' ? $location : null,
            'is_default' => $isDefault,
        ];

        if ($id) {
            Database::update('warehouses', $data, 'id = ?', [$id]);
        } else {
            Database::insert('warehouses', $data);
        }
    }
}
