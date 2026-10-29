<?php /** لیست کاربران */ ?>
<div class="page-head">
    <h2>کاربران <small class="muted">(<?= fa_num($total) ?> کاربر)</small></h2>
    <div class="page-actions">
        <?php if (can('users.edit')): ?><a class="btn btn-primary" href="<?= url('users/create') ?>">+ کاربر جدید</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('roles') ?>">نقش‌ها و دسترسی‌ها</a>
    </div>
</div>

<form method="get" action="<?= url('users') ?>" class="search-bar">
    <input type="hidden" name="route" value="users">
    <input type="text" name="q" class="form-input" placeholder="جستجو: نام کاربری، نام، ایمیل..." value="<?= e($q) ?>">
    <button class="btn btn-outline">جستجو</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>نام کاربری</th><th>نام کامل</th><th>ایمیل</th><th>نقش</th>
                    <th>وضعیت</th><th>آخرین ورود</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">کاربری یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td dir="ltr"><b><?= e($r['username']) ?></b></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td class="muted" dir="ltr"><?= e($r['email'] ?? '—') ?></td>
                    <td><span class="badge badge-gray"><?= e($r['role_name']) ?></span></td>
                    <td><?= status_label((int) $r['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                    <td class="muted"><?= $r['last_login_at'] ? jdate($r['last_login_at'], 'Y/m/d H:i') : '—' ?></td>
                    <td>
                        <?php if (can('users.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('users/edit/' . $r['id']) ?>">ویرایش</a>
                            <form method="post" action="<?= url('users/delete/' . $r['id']) ?>" style="display:inline" data-confirm="کاربر «<?= e($r['full_name']) ?>» حذف شود؟">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('users') . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) . '&' : '?')]); ?>
