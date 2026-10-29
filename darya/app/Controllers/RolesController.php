<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** مدیریت نقش‌ها و دسترسی‌ها — users.view / users.edit */
class RolesController extends Controller
{
    public function indexAction(): void
    {
        $this->requirePerm('users.view');

        $rows = Database::all(
            "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS members
             FROM roles r ORDER BY r.id"
        );

        $this->view('roles/index', [
            'title' => 'نقش‌ها و دسترسی‌ها',
            'rows'  => $rows,
        ]);
    }

    public function createAction(): void
    {
        $this->requirePerm('users.edit');
        $this->view('roles/form', [
            'title'    => 'نقش جدید',
            'row'      => null,
            'catalog'  => $this->catalog(),
            'selected' => [],
            'isAdmin'  => false,
            'isEdit'   => false,
        ]);
    }

    public function editAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $row = Database::one('SELECT * FROM roles WHERE id = ?', [(int) ($args[0] ?? 0)]);
        if (!$row) $this->notFound();
        $perms = json_decode((string) ($row['permissions'] ?? '[]'), true) ?: [];
        $isAdmin = ($row['slug'] === 'admin') || in_array('*', (array) $perms, true);
        $this->view('roles/form', [
            'title'    => 'ویرایش نقش',
            'row'      => $row,
            'catalog'  => $this->catalog(),
            'selected' => is_array($perms) ? $perms : [],
            'isAdmin'  => $isAdmin,
            'isEdit'   => true,
        ]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('users.edit');
        $catalog = $this->catalog();
        $name = trim((string) $this->input('name', ''));
        $slug = $this->normalizeSlug((string) $this->input('slug', ''));

        if ($name === '' || $slug === '') {
            flash_set('error', 'نام نقش و شناسه (اسلاگ) الزامی است.');
            $this->redirect('roles/create');
        }
        if (Database::one('SELECT id FROM roles WHERE slug = ?', [$slug])) {
            flash_set('error', 'این شناسه نقش قبلاً استفاده شده است.');
            $this->redirect('roles/create');
        }

        Database::insert('roles', [
            'name' => $name,
            'slug' => $slug,
            'permissions' => json_encode($this->readPerms($catalog), JSON_UNESCAPED_UNICODE),
            'is_system' => 0,
        ]);
        flash_set('success', 'نقش جدید ثبت شد.');
        $this->redirect('roles');
    }

    public function updateAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            flash_set('error', 'نام نقش الزامی است.');
            $this->redirect('roles/edit/' . $id);
        }

        $isAdmin = $row['slug'] === 'admin';
        $data = ['name' => $name];
        if ($isAdmin) {
            // دسترسی مدیر کل همیشه «*» است و تغییر نمی‌کند
            $data['permissions'] = '*';
        } else {
            $catalog = $this->catalog();
            $data['permissions'] = json_encode($this->readPerms($catalog), JSON_UNESCAPED_UNICODE);
        }
        Database::update('roles', $data, 'id = ?', [$id]);
        flash_set('success', 'نقش بروزرسانی شد.');
        $this->redirect('roles');
    }

    public function deleteAction(array $args): void
    {
        $this->requirePerm('users.edit');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$row) $this->notFound();

        $members = (int) Database::val('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]);
        if ((int) $row['is_system'] === 1) {
            flash_set('error', 'نقش‌های سیستمی قابل حذف نیستند.');
        } elseif ($members > 0) {
            flash_set('error', 'به این نقش ' . fa_num($members) . ' کاربر متصل است؛ ابتدا نقش کاربران را تغییر دهید.');
        } else {
            Database::delete('roles', 'id = ?', [$id]);
            flash_set('success', 'نقش حذف شد.');
        }
        $this->redirect('roles');
    }

    /* ----------------helpers---------------- */

    /** فهرست مجوزهای مجاز از config/permissions.php */
    private function catalog(): array
    {
        $catalog = require BASE_PATH . '/config/permissions.php';
        return is_array($catalog) ? $catalog : [];
    }

    /** فقط کلیدهای شناخته‌شده از ورودی POST گرفته می‌شود */
    private function readPerms(array $catalog): array
    {
        $posted = (array) ($_POST['perms'] ?? []);
        $perms = [];
        foreach ($catalog as $key => $label) {
            if (in_array($key, $posted, true)) $perms[] = $key;
        }
        return $perms;
    }

    private function normalizeSlug(string $input): string
    {
        $slug = str_replace(' ', '-', trim($input));
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug) ?? '';
        return strtolower(trim($slug, '-'));
    }
}
