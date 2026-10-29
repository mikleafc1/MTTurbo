<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** کارمندان — employees.view / employees.edit */
class EmployeesController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('employees.view');

        $q = (string) $this->input('q', '');
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(full_name LIKE ? OR code LIKE ? OR mobile LIKE ? OR position LIKE ? OR department LIKE ?)';
            $like = "%$q%";
            $params = [$like, $like, $like, $like, $like];
        }

        $data = $this->paginate('employees', $where, $params, 20, '*', 'id DESC');
        $this->view('employees/index', [
            'title' => 'کارمندان',
            'rows'  => $data['rows'],
            'total' => $data['total'],
            'page'  => $data['page'],
            'pages' => $data['pages'],
            'q'     => $q,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('employees.edit');
        $this->view('employees/form', ['title' => 'کارمند جدید', 'row' => null]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $row = Database::one('SELECT * FROM employees WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->view('employees/form', ['title' => 'ویرایش کارمند', 'row' => $row]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('employees.edit');
        $data = $this->readForm(null);
        if ($data === null) {
            $this->redirect('employees/create');
        }
        if (trim((string) ($data['code'] ?? '')) === '') {
            $data['code'] = 'E-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM employees') + 1), 4, '0', STR_PAD_LEFT);
        }
        Database::insert('employees', $data);
        flash_set('success', 'کارمند جدید ثبت شد.');
        $this->redirect('employees');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM employees WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        $data = $this->readForm($id);
        if ($data === null) {
            $this->redirect('employees/edit/' . $id);
        }
        if (trim((string) ($data['code'] ?? '')) === '') {
            $data['code'] = $row['code'];
        }
        Database::update('employees', $data, 'id = ?', [$id]);
        flash_set('success', 'اطلاعات کارمند بروزرسانی شد.');
        $this->redirect('employees');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $id = (int) ($args[0] ?? 0);
        $hasPayroll = (bool) Database::val('SELECT COUNT(*) FROM payrolls WHERE employee_id = ?', [$id]);
        if ($hasPayroll) {
            flash_set('error', 'این کارمند فیش حقوقی دارد و قابل حذف نیست (می‌توانید وضعیت او را غیرفعال کنید).');
        } else {
            Database::delete('employees', 'id = ?', [$id]);
            flash_set('success', 'کارمند حذف شد.');
        }
        $this->redirect('employees');
    }

    /* ----------------helpers---------------- */

    /**
     * خواندن و اعتبارسنجی فرم کارمند؛ null = خطا (فلش ثبت شده)
     * @return array<string,mixed>|null
     */
    private function readForm(?int $currentId): ?array
    {
        $fullName = (string) $this->input('full_name', '');
        if (trim($fullName) === '') {
            flash_set('error', 'نام کارمند الزامی است.');
            return null;
        }

        $code = trim((string) $this->input('code', ''));
        if ($code !== '' && Database::one('SELECT id FROM employees WHERE code = ? AND id <> ?', [$code, $currentId ?? 0])) {
            flash_set('error', 'این کد پرسنلی قبلاً استفاده شده است.');
            return null;
        }

        $hireDateJ = trim((string) $this->input('hire_date', ''));
        $hireDate = null;
        if ($hireDateJ !== '') {
            $hireDate = jinput_to_gregorian($hireDateJ);
            if ($hireDate === null) {
                flash_set('error', 'تاریخ استخدام نامعتبر است (نمونه صحیح: 1403/05/12).');
                return null;
            }
        }

        $status = $this->input('status') === 'inactive' ? 'inactive' : 'active';

        return [
            'code' => (string) $this->input('code', ''),
            'full_name' => $fullName,
            'national_id' => $this->input('national_id') ?: null,
            'mobile' => $this->input('mobile') ?: null,
            'position' => $this->input('position') ?: null,
            'department' => $this->input('department') ?: null,
            'hire_date' => $hireDate,
            'base_salary' => (float) str_replace([',', '٬'], '', fa_num_reverse((string) $this->input('base_salary', '0'))),
            'insurance_no' => $this->input('insurance_no') ?: null,
            'bank_name' => $this->input('bank_name') ?: null,
            'bank_account' => $this->input('bank_account') ?: null,
            'status' => $status,
            'notes' => $this->input('notes') ?: null,
        ];
    }
}
