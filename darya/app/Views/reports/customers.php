<?php /** گزارش مانده و گردش مشتریان — مرتب بر اساس مانده بدهی */ $currency = setting('currency', 'تومان'); ?>
<div class="page-head">
    <h2>مانده حساب مشتریان <small class="muted">(<?= fa_num($total) ?> نفر)</small></h2>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= url('reports') ?>">← گزارش‌ها</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>مشتری</th><th>موبایل</th>
                    <th>مانده حساب</th><th>سقف اعتبار</th><th>تعداد فاکتور</th><th>مجموع فروش</th><th>آخرین خرید</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="empty">مشتری‌ای ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted"><?= e($r['code'] ?? '—') ?></td>
                    <td><a href="<?= url('customers/view/' . $r['id']) ?>"><?= e($r['full_name']) ?></a></td>
                    <td dir="ltr"><?= e(fa_num($r['mobile'] ?? '')) ?></td>
                    <td class="<?= (float) $r['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= money($r['balance']) ?></td>
                    <td class="muted"><?= money($r['credit_limit']) ?></td>
                    <td><?= fa_num($r['invoice_count']) ?></td>
                    <td><?= money($r['total_sales']) ?></td>
                    <td class="muted"><?= $r['last_purchase'] ? jdate($r['last_purchase']) : '—' ?></td>
                    <td><?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline" href="<?= url('customers/edit/' . $r['id']) ?>">ویرایش</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="hint-box">مانده مثبت = بدهی مشتری به شما (فاکتورهای باز). مجموع فروش شامل فاکتورهای فروش غیر لغو شده است.</div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('reports/customers') . '?']); ?>
