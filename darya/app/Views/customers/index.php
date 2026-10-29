<?php /** لیست مشتریان */ ?>
<div class="page-head">
    <h2>مشتریان <small class="muted">(<?= fa_num($total) ?> نفر)</small></h2>
    <div class="page-actions">
        <?php if (can('customers.edit')): ?><a class="btn btn-primary" href="<?= url('customers/create') ?>">+ مشتری جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('customers') ?>" class="search-bar">
    <input type="hidden" name="route" value="customers">
    <input type="text" name="q" class="form-input" placeholder="جستجو: نام، موبایل، ایمیل، کد..." value="<?= e($q) ?>">
    <button class="btn btn-outline">جستجو</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>نام</th><th>موبایل</th><th>شهر</th>
                    <th>مانده حساب</th><th>نقش سایت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">مشتری‌ای یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted"><?= e($r['code']) ?></td>
                    <td><a href="<?= url('customers/view/' . $r['id']) ?>"><?= e($r['full_name']) ?></a><?= $r['company'] ? ' <small class="muted">' . e($r['company']) . '</small>' : '' ?></td>
                    <td dir="ltr"><?= e(fa_num($r['mobile'] ?? '')) ?></td>
                    <td><?= e($r['city'] ?? '') ?></td>
                    <td class="<?= (float) $r['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money($r['balance']) ?></td>
                    <td><?= e($r['wp_roles'] ?? '') ?></td>
                    <td>
                        <?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline" href="<?= url('customers/edit/' . $r['id']) ?>">ویرایش</a><?php endif; ?>
                        <?php if (can('customers.manage')): ?>
                        <form method="post" action="<?= url('customers/delete/' . $r['id']) ?>" style="display:inline" data-confirm="مشتری «<?= e($r['full_name']) ?>» حذف شود؟">
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
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('customers') . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) . '&' : '?')]); ?>
