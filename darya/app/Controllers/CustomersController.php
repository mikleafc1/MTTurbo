<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\SyncService;

/** نمونه مرجع پیاده‌سازی CRUD برای سایر ماژول‌ها */
class CustomersController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('customers.view');

        $q = (string) $this->input('q', '');
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(full_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR code LIKE ? OR company LIKE ?)';
            $like = "%$q%";
            $params = [$like, $like, $like, $like, $like];
        }

        $data = $this->paginate('customers', $where, $params, 20, '*', 'id DESC');
        $this->view('customers/index', [
            'title' => 'مشتریان',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'q' => $q,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('customers.edit');
        $this->view('customers/form', ['title' => 'مشتری جدید', 'row' => null]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('customers.edit');
        $row = Database::one('SELECT * FROM customers WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->view('customers/form', ['title' => 'ویرایش مشتری', 'row' => $row]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('customers.edit');
        $id = $this->saveCustomer(null);
        flash_set('success', 'مشتری جدید ثبت شد.');
        $this->redirect('customers/view/' . $id);
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('customers.edit');
        $id = (int) ($args[0] ?? 0);
        $this->saveCustomer($id);
        flash_set('success', 'اطلاعات مشتری بروزرسانی شد.');
        $this->redirect('customers/view/' . $id);
    }

    public function viewAction(array $args): void
    {
        $this->requirePerm('customers.view');
        $row = Database::one('SELECT * FROM customers WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();

        $invoices = Database::all('SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC LIMIT 15', [$row['id']]);
        $transactions = Database::all('SELECT * FROM transactions WHERE party_type = "customer" AND party_id = ? ORDER BY id DESC LIMIT 15', [$row['id']]);
        $totalSales = (float) Database::val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE customer_id = ? AND invoice_type = 'sale' AND status <> 'canceled'", [$row['id']]);

        $this->view('customers/view', [
            'title' => $row['full_name'],
            'row' => $row,
            'invoices' => $invoices,
            'transactions' => $transactions,
            'totalSales' => $totalSales,
        ]);
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('customers.manage');
        $id = (int) ($args[0] ?? 0);
        $hasInvoice = (bool) Database::val('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$id]);
        if ($hasInvoice) {
            flash_set('error', 'این مشتری فاکتور دارد و قابل حذف نیست (می‌توانید غیرفعال/ویرایش کنید).');
        } else {
            Database::delete('customers', 'id = ?', [$id]);
            flash_set('success', 'مشتری حذف شد.');
        }
        $this->redirect('customers');
    }

    private function saveCustomer(?int $id): int
    {
        $data = [
            'full_name' => (string) $this->input('full_name', ''),
            'company' => $this->input('company') ?: null,
            'type' => $this->input('type') === 'company' ? 'company' : 'person',
            'mobile' => $this->input('mobile') ?: null,
            'phone' => $this->input('phone') ?: null,
            'email' => $this->input('email') ?: null,
            'address' => $this->input('address') ?: null,
            'city' => $this->input('city') ?: null,
            'postal_code' => $this->input('postal_code') ?: null,
            'national_id' => $this->input('national_id') ?: null,
            'economic_code' => $this->input('economic_code') ?: null,
            'credit_limit' => (float) str_replace(',', '', (string) $this->input('credit_limit', '0')),
            'notes' => $this->input('notes') ?: null,
        ];
        if (trim($data['full_name']) === '') {
            flash_set('error', 'نام مشتری الزامی است.');
            $this->redirect($id ? 'customers/edit/' . $id : 'customers/create');
        }
        if ($id) {
            Database::update('customers', $data, 'id = ?', [$id]);
            return $id;
        }
        $data['code'] = 'C-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM customers') + 1), 5, '0', STR_PAD_LEFT);
        return Database::insert('customers', $data);
    }
}
