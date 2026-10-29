<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;

/** مدیریت کاربران — users.view / users.edit */
class UsersController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('users.view');

        $q = (string) $this->input('q', '');
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            $like = "%$q%";
            $params = [$like, $like, $like];
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = (int) Database::val(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE $where",
            $params
        );
        $rows = Database::all(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE $where ORDER BY u.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );
        $pages = max(1, (int) ceil($total / $perPage));

        $this->view('users/index', [
            'title' => 'کاربران',
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'q'     => $q,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('users.edit');
        $this->view('users/form', [
            'title'  => 'کاربر جدید',
            'row'    => null,
            'roles'  => $this->roleOptions(),
            'isEdit' => false,
            'isSelf' => false,
        ]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $row = Database::one('SELECT * FROM users WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $this->view('users/form', [
            'title'  => 'ویرایش کاربر',
            'row'    => $row,
            'roles'  => $this->roleOptions(),
            'isEdit' => true,
            'isSelf' => (int) $row['id'] === (int) (Auth::user()['id'] ?? 0),
        ]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('users.edit');
        $data = $this->readForm(null);
        if ($data === null) {
            $this->redirect('users/create');
        }
        Database::insert('users', $data);
        flash_set('success', 'کاربر جدید ثبت شد.');
        $this->redirect('users');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        // محافظت: غیرفعال کردن خود کاربر
        $isSelf = $id === (int) (Auth::user()['id'] ?? 0);
        $isActive = $this->input('is_active') ? 1 : 0;
        if ($isSelf && $isActive !== 1) {
            flash_set('error', 'نمی‌توانید حساب خودتان را غیرفعال کنید.');
            $this->redirect('users/edit/' . $id);
        }
        // محافظت: آخرین مدیر فعال
        $newRoleId = (int) $this->input('role_id', '0');
        if ($this->isLastActiveAdmin($row) && ($isActive !== 1 || $newRoleId !== (int) $row['role_id'])) {
            flash_set('error', 'حداقل یک مدیر فعال باید باقی بماند؛ این تغییر مجاز نیست.');
            $this->redirect('users/edit/' . $id);
        }

        $data = $this->readForm($row);
        if ($data === null) {
            $this->redirect('users/edit/' . $id);
        }
        Database::update('users', $data, 'id = ?', [$id]);
        flash_set('success', 'اطلاعات کاربر بروزرسانی شد.');
        $this->redirect('users');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        if ($id === (int) (Auth::user()['id'] ?? 0)) {
            flash_set('error', 'نمی‌توانید حساب خودتان را حذف کنید.');
        } elseif ($this->isLastActiveAdmin($row)) {
            flash_set('error', 'حذف آخرین مدیر فعال مجاز نیست.');
        } else {
            Database::delete('users', 'id = ?', [$id]);
            flash_set('success', 'کاربر حذف شد.');
        }
        $this->redirect('users');
    }

    /* ----------------helpers---------------- */

    private function roleOptions(): array
    {
        return Database::all('SELECT id, name, slug FROM roles ORDER BY id');
    }

    /**
     * خواندن و اعتبارسنجی فرم کاربر؛ null = خطا (فلش ثبت شده)
     * @return array<string,mixed>|null
     */
    private function readForm(?array $currentRow): ?array
    {
        $username = (string) $this->input('username', '');
        $fullName = (string) $this->input('full_name', '');
        $email = (string) $this->input('email', '');
        $password = (string) $this->input('password', '');
        $roleId = (int) $this->input('role_id', '0');
        $isActive = $this->input('is_active') ? 1 : 0;
        $currentId = $currentRow ? (int) $currentRow['id'] : null;

        if ($username === '' || $fullName === '') {
            flash_set('error', 'نام کاربری و نام کامل الزامی است.');
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_\.\-]{3,64}$/', $username)) {
            flash_set('error', 'نام کاربری فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط (حداقل ۳ کاراکتر).');
            return null;
        }
        $dup = Database::one('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $currentId ?? 0]);
        if ($dup) {
            flash_set('error', 'این نام کاربری قبلاً استفاده شده است.');
            return null;
        }
        $role = Database::one('SELECT * FROM roles WHERE id = ?', [$roleId]);
        if (!$role) {
            flash_set('error', 'نقش انتخابی نامعتبر است.');
            return null;
        }
        if (!$currentRow && $password === '') {
            flash_set('error', 'برای کاربر جدید رمز عبور الزامی است.');
            return null;
        }

        $data = [
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'role_id' => $roleId,
            'is_active' => $isActive,
        ];
        if ($password !== '') {
            if (mb_strlen($password) < 6) {
                flash_set('error', 'رمز عبور حداقل ۶ کاراکتر باشد.');
                return null;
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return $data;
    }

    /** آیا این کاربر، آخرین مدیر فعال سیستم است؟ */
    private function isLastActiveAdmin(array $row): bool
    {
        if ((int) $row['is_active'] !== 1) return false;
        $role = Database::one('SELECT slug FROM roles WHERE id = ?', [(int) $row['role_id']]);
        if (!$role || $role['slug'] !== 'admin') return false;
        $others = (int) Database::val(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'admin' AND u.is_active = 1 AND u.id <> ?",
            [(int) $row['id']]
        );
        return $others === 0;
    }
}
