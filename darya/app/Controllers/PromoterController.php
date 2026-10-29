<?php
/**
 * مدیریت مبلغان (موبوآردر) — CRUD
 * دسترسی‌ها: employees.view / employees.edit
 */
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class PromoterController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('employees.view');

        $q = (string) $this->input('q', '');
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(full_name LIKE ? OR mobile LIKE ? OR code LIKE ?)';
            $like = "%$q%";
            $params = [$like, $like, $like];
        }

        $data = $this->paginate('promoters', $where, $params, 20, '*', 'id DESC');
        $this->view('promoters/index', [
            'title' => 'مبلغان (موبوآردر)',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'q' => $q,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('employees.edit');
        $this->view('promoters/form', ['title' => 'مبلغ جدید', 'row' => null]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $row = Database::one('SELECT * FROM promoters WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->view('promoters/form', ['title' => 'ویرایش مبلغ', 'row' => $row]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('employees.edit');
        $id = $this->savePromoter(null);
        flash_set('success', 'مبلغ جدید ثبت شد (می‌تواند با موبایل و PIN وارد اپ موبوآردر شود).');
        $this->redirect('promoters');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $id = (int) ($args[0] ?? 0);
        $this->savePromoter($id);
        flash_set('success', 'اطلاعات مبلغ بروزرسانی شد.');
        $this->redirect('promoters');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('employees.edit');
        $id = (int) ($args[0] ?? 0);
        $hasOrders = (bool) Database::val('SELECT COUNT(*) FROM invoices WHERE promoter_id = ?', [$id]);
        if ($hasOrders) {
            flash_set('error', 'این مبلغ سفارش (فاکتور) دارد و قابل حذف نیست؛ می‌توانید آن را غیرفعال کنید.');
        } else {
            Database::delete('promoters', 'id = ?', [$id]);
            flash_set('success', 'مبلغ حذف شد.');
        }
        $this->redirect('promoters');
    }

    private function savePromoter(?int $id): void
    {
        $back = $id ? 'promoters/edit/' . $id : 'promoters/create';

        $fullName = trim((string) $this->input('full_name', ''));
        $mobile = trim((string) $this->input('mobile', ''));
        $pin = trim((string) $this->input('pin', ''));
        $code = trim((string) $this->input('code', ''));
        $rate = max(0.0, min(100.0, (float) str_replace(',', '', (string) $this->input('commission_rate', '0'))));

        if ($fullName === '') {
            flash_set('error', 'نام مبلغ الزامی است.');
            $this->redirect($back);
        }
        if ($mobile === '') {
            flash_set('error', 'شماره موبایل الزامی است (برای ورود به اپ موبوآردر).');
            $this->redirect($back);
        }
        if ($pin !== '' && !preg_match('#^\d{4,6}$#', $pin)) {
            flash_set('error', 'PIN باید ۴ تا ۶ رقم باشد.');
            $this->redirect($back);
        }
        $dupMobile = Database::val('SELECT id FROM promoters WHERE mobile = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$mobile, $id] : [$mobile]);
        if ($dupMobile) {
            flash_set('error', 'این شماره موبایل قبلاً برای مبلغ دیگری ثبت شده است.');
            $this->redirect($back);
        }

        $data = [
            'code' => $code !== '' ? $code : null,
            'full_name' => $fullName,
            'mobile' => $mobile,
            'pin' => $pin !== '' ? $pin : null,
            'commission_rate' => round($rate, 2),
            'is_active' => (string) $this->input('is_active', '0') === '1' ? 1 : 0,
            'notes' => (string) $this->input('notes', '') ?: null,
        ];

        if ($id) {
            Database::update('promoters', $data, 'id = ?', [$id]);
            return;
        }
        $data['code'] = $data['code'] ?? ('P-' . str_pad((string) (Database::val('SELECT COUNT(*) FROM promoters') + 1), 5, '0', STR_PAD_LEFT));
        Database::insert('promoters', $data);
    }
}
