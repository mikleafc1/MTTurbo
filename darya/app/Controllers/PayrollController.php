<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AccountingService;
use App\Services\InventoryService;

/** حقوق و دستمزد: لیست حقوق کارمندان و پرداخت */
class PayrollController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('payroll.view');

        $year = (int) $this->input('year', 0);
        $month = (int) $this->input('month', 0);

        $where = '1=1';
        $params = [];
        if ($year > 0) { $where .= ' AND p.period_year = ?'; $params[] = $year; }
        if ($month > 0) { $where .= ' AND p.period_month = ?'; $params[] = $month; }

        $join = 'FROM payrolls p LEFT JOIN employees e ON e.id = p.employee_id';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val("SELECT COUNT(*) $join WHERE $where", $params);
        $rows = Database::all(
            "SELECT p.*, e.full_name AS employee_name, e.code AS employee_code, e.position AS employee_position
             $join WHERE $where
             ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );
        $sumNet = (float) Database::val("SELECT COALESCE(SUM(p.net_pay),0) $join WHERE $where", $params);

        $this->view('payroll/index', [
            'title'  => 'حقوق و دستمزد',
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'year'   => $year,
            'month'  => $month,
            'sumNet' => $sumNet,
        ]);
    }

    public function formAction(array $args): void
    {
        $this->requirePerm('payroll.edit');
        $id = (int) ($args[0] ?? 0);
        $row = null;
        if ($id) {
            $row = Database::one('SELECT * FROM payrolls WHERE id = ?', [$id]);
            if (!$row) $this->notFound();
            if ($row['status'] === 'paid') {
                flash_set('error', 'لیست حقوق پرداخت‌شده قابل ویرایش نیست.');
                $this->redirect('payroll');
            }
        }

        $employees = Database::all("SELECT id, code, full_name, base_salary FROM employees WHERE status = 'active' ORDER BY full_name ASC");

        // پیش‌فرض حقوق پایه از کارمند (در ایجاد با ?employee_id یا در ویرایش)
        $prefillEmployeeId = $id ? (int) $row['employee_id'] : (int) $this->input('employee_id', 0);
        $baseSalaryDefault = '';
        foreach ($employees as $emp) {
            if ((int) $emp['id'] === $prefillEmployeeId) {
                $baseSalaryDefault = (string) $emp['base_salary'];
                break;
            }
        }

        $this->view('payroll/form', [
            'title'             => $row ? 'ویرایش لیست حقوق' : 'لیست حقوق جدید',
            'row'               => $row,
            'employees'         => $employees,
            'prefillEmployeeId' => $prefillEmployeeId,
            'baseSalaryDefault' => $baseSalaryDefault,
        ]);
    }

    public function storeAction(array $args): void
    {
        $this->requirePerm('payroll.edit');
        $id = (int) ($args[0] ?? 0);
        $backTo = $id ? 'payroll/form/' . $id : 'payroll/form';

        $employeeId = (int) $this->input('employee_id', 0);
        $year = (int) $this->input('period_year', 0);
        $month = (int) $this->input('period_month', 0);
        $employee = Database::one("SELECT * FROM employees WHERE id = ? AND status = 'active'", [$employeeId]);

        if (!$employee) {
            flash_set('error', 'کارمند معتبر انتخاب نشده است.');
            $this->redirect($backTo);
        }
        if ($year < 1300 || $year > 1500 || $month < 1 || $month > 12) {
            flash_set('error', 'دوره (سال/ماه) معتبر نیست.');
            $this->redirect($backTo);
        }

        $base = $this->parseMoney((string) $this->input('base_salary', '0'));
        $overtime = $this->parseMoney((string) $this->input('overtime', '0'));
        $bonus = $this->parseMoney((string) $this->input('bonus', '0'));
        $insurance = $this->parseMoney((string) $this->input('insurance_deduction', '0'));
        $tax = $this->parseMoney((string) $this->input('tax_deduction', '0'));
        $other = $this->parseMoney((string) $this->input('other_deduction', '0'));
        $net = round($base + $overtime + $bonus - $insurance - $tax - $other, 2);

        $data = [
            'employee_id'         => $employeeId,
            'period_year'         => $year,
            'period_month'        => $month,
            'base_salary'         => $base,
            'overtime'            => $overtime,
            'bonus'               => $bonus,
            'insurance_deduction' => $insurance,
            'tax_deduction'       => $tax,
            'other_deduction'     => $other,
            'net_pay'             => $net,
            'description'         => $this->input('description') ?: null,
        ];

        try {
            if ($id) {
                $row = Database::one('SELECT * FROM payrolls WHERE id = ?', [$id]);
                if (!$row) $this->notFound();
                if ($row['status'] === 'paid') {
                    flash_set('error', 'لیست حقوق پرداخت‌شده قابل ویرایش نیست.');
                    $this->redirect('payroll');
                }
                Database::update('payrolls', $data, 'id = ?', [$id]);
                flash_set('success', 'لیست حقوق بروزرسانی شد.');
            } else {
                $data['status'] = 'draft';
                Database::insert('payrolls', $data);
                flash_set('success', 'لیست حقوق ثبت شد (پیش‌نویس).');
            }
        } catch (\PDOException $e) {
            // UNIQUE KEY uq_payroll (employee_id, period_year, period_month)
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 || $e->getCode() === '23000') {
                flash_set('error', 'برای این کارمند در این دوره قبلاً لیست حقوق ثبت شده است.');
                $this->redirect($backTo);
            }
            throw $e;
        }
        $this->redirect('payroll');
    }

    /** پرداخت حقوق: تراکنش خزانه + کاهش صندوق + سند خودکار */
    public function payAction(array $args): void
    {
        $this->requirePerm('payroll.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one(
            'SELECT p.*, e.full_name AS employee_name FROM payrolls p
             LEFT JOIN employees e ON e.id = p.employee_id WHERE p.id = ?',
            [$id]
        );
        if (!$row) $this->notFound();
        if ($row['status'] === 'paid') {
            flash_set('error', 'این لیست حقوق قبلاً پرداخت شده است.');
            $this->redirect('payroll');
        }
        $net = (float) $row['net_pay'];
        if ($net <= 0) {
            flash_set('error', 'خالص پرداختی باید بزرگ‌تر از صفر باشد.');
            $this->redirect('payroll');
        }
        $cashbox = Database::one('SELECT * FROM cashboxes WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        if (!$cashbox) {
            flash_set('error', 'هیچ صندوق فعالی وجود ندارد.');
            $this->redirect('payroll');
        }

        $employeeName = (string) ($row['employee_name'] ?? 'کارمند');
        $period = fa_num($row['period_year'] . '/' . str_pad((string) $row['period_month'], 2, '0', STR_PAD_LEFT));
        $description = 'حقوق ' . $period . ' — ' . $employeeName;

        $trxNo = InventoryService::nextNo('trx_prefix', 'trx_counter');
        $trxId = Database::insert('transactions', [
            'trx_no'      => $trxNo,
            'type'        => 'payment',
            'party_type'  => 'employee',
            'party_id'    => (int) $row['employee_id'],
            'cashbox_id'  => (int) $cashbox['id'],
            'method'      => 'transfer',
            'amount'      => round($net, 2),
            'trx_date'    => today(),
            'invoice_id'  => null,
            'description' => $description,
            'created_by'  => (int) (Auth::user()['id'] ?? 0) ?: null,
        ]);

        // اثر صندوق: پرداخت −
        Database::q('UPDATE cashboxes SET balance = balance - ? WHERE id = ?', [round($net, 2), (int) $cashbox['id']]);

        Database::update('payrolls', ['status' => 'paid', 'paid_at' => now()], 'id = ?', [$id]);

        // سند حسابداری خودکار
        $trx = Database::one('SELECT * FROM transactions WHERE id = ?', [$trxId]);
        if ($trx) AccountingService::voucherForTransaction($trx, $employeeName);

        flash_set('success', 'حقوق ' . $employeeName . ' برای دوره ' . $period . ' پرداخت شد (' . $trxNo . ').');
        $this->redirect('payroll');
    }

    /** «۱٬۲۳۴,۵۶» یا «1234.5» → float */
    private function parseMoney(string $raw): float
    {
        $raw = fa_num_reverse(str_replace([',', '٬', ' '], '', trim($raw)));
        return (float) ($raw === '' ? 0 : $raw);
    }
}
