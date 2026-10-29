<?php /** لیست نقش‌ها با تعداد اعضا */ ?>
<div class="page-head">
    <h2>نقش‌ها و دسترسی‌ها</h2>
    <div class="page-actions">
        <?php if (can('users.edit')): ?><a class="btn btn-primary" href="<?= url('roles/create') ?>">+ نقش جدید</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('users') ?>">کاربران</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>نقش</th><th>شناسه</th><th>اعضا</th><th>دسترسی‌ها</th><th>نوع</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">نقشی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $perms = json_decode((string) ($r['permissions'] ?? '[]'), true) ?: [];
                $permCount = in_array('*', (array) $perms, true) ? 'همه دسترسی‌ها' : fa_num(count($perms)) . ' مجوز';
            ?>
                <tr>
                    <td><b><?= e($r['name']) ?></b></td>
                    <td dir="ltr" class="muted"><?= e($r['slug']) ?></td>
                    <td><?= fa_num($r['members']) ?> کاربر</td>
                    <td><?= e($permCount) ?></td>
                    <td><?= (int) $r['is_system'] === 1 ? '<span class="badge badge-amber">سیستمی</span>' : '<span class="badge badge-gray">سفارشی</span>' ?></td>
                    <td>
                        <?php if (can('users.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('roles/edit/' . $r['id']) ?>">ویرایش</a>
                            <?php if ((int) $r['is_system'] !== 1): ?>
                                <form method="post" action="<?= url('roles/delete/' . $r['id']) ?>" style="display:inline" data-confirm="نقش «<?= e($r['name']) ?>» حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="hint-box">نقش‌های سیستمی قابل حذف نیستند؛ برای حذف نقش سفارشی ابتدا کاربران آن را به نقش دیگری منتقل کنید.</div>
</div>
