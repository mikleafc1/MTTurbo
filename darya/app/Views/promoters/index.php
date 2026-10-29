<?php /** لیست مبلغان (موبوآردر) */ ?>
<div class="page-head">
    <h2>مبلغان (موبوآردر) <small class="muted">(<?= fa_num($total) ?> نفر)</small></h2>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= url('mobo/index.php') ?>" target="_blank" rel="noopener">اپ موبوآردر ↗</a>
        <?php if (can('employees.edit')): ?><a class="btn btn-primary" href="<?= url('promoters/create') ?>">+ مبلغ جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('promoters') ?>" class="search-bar">
    <input type="hidden" name="route" value="promoters">
    <input type="text" name="q" class="form-input" placeholder="جستجو: نام، موبایل، کد..." value="<?= e($q) ?>">
    <button class="btn btn-outline">جستجو</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>نام</th><th>موبایل</th><th>کمیسیون</th><th>سفارش‌ها</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">مبلغی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $orderCount = (int) \App\Core\Database::val('SELECT COUNT(*) FROM invoices WHERE promoter_id = ?', [$r['id']]); ?>
                <tr>
                    <td class="muted"><?= e($r['code']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td dir="ltr"><?= e(fa_num($r['mobile'])) ?></td>
                    <td><?= e(fa_num(rtrim(rtrim(number_format((float) $r['commission_rate'], 2, '.', ''), '0'), '.'))) ?>٪</td>
                    <td><?= fa_num($orderCount) ?></td>
                    <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-green">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?></td>
                    <td>
                        <?php if (can('employees.edit')): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('promoters/edit/' . $r['id']) ?>">ویرایش</a>
                        <form method="post" action="<?= url('promoters/delete/' . $r['id']) ?>" style="display:inline" data-confirm="مبلغ «<?= e($r['full_name']) ?>» حذف شود؟">
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

<div class="hint-box">
    مبلغان با شماره موبایل و PIN خود از آدرس <b dir="ltr"><?= e(url('mobo/index.php')) ?></b> وارد اپ موبوآردر می‌شوند؛ سفارش‌های آن‌ها با وضعیت «در انتظار تأیید» در فاکتورهای فروش ثبت می‌شود.
</div>

<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('promoters') . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) . '&' : '?')]); ?>
