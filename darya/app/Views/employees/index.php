<?php /** لیست کارمندان */ ?>
<div class="page-head">
    <h2>کارمندان <small class="muted">(<?= fa_num($total) ?> نفر)</small></h2>
    <div class="page-actions">
        <?php if (can('employees.edit')): ?><a class="btn btn-primary" href="<?= url('employees/create') ?>">+ کارمند جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('employees') ?>" class="search-bar">
    <input type="hidden" name="route" value="employees">
    <input type="text" name="q" class="form-input" placeholder="جستجو: نام، کد، موبایل، سمت، واحد..." value="<?= e($q) ?>">
    <button class="btn btn-outline">جستجو</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>نام</th><th>سمت</th><th>واحد</th><th>موبایل</th>
                    <th>حقوق پایه</th><th>تاریخ استخدام</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="empty">کارمندی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted"><?= e($r['code'] ?? '—') ?></td>
                    <td><b><?= e($r['full_name']) ?></b></td>
                    <td><?= e($r['position'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['department'] ?? '—') ?></td>
                    <td dir="ltr"><?= e(fa_num($r['mobile'] ?? '')) ?></td>
                    <td><?= money($r['base_salary']) ?></td>
                    <td class="muted"><?= $r['hire_date'] ? jdate($r['hire_date']) : '—' ?></td>
                    <td><?= status_label($r['status']) ?></td>
                    <td>
                        <?php if (can('employees.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('employees/edit/' . $r['id']) ?>">ویرایش</a>
                            <form method="post" action="<?= url('employees/delete/' . $r['id']) ?>" style="display:inline" data-confirm="کارمند «<?= e($r['full_name']) ?>» حذف شود؟">
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
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('employees') . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) . '&' : '?')]); ?>
