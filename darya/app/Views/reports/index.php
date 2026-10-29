<?php /** مرکز گزارش‌ها — کارت‌های میان‌بر + آمار سریع */ $currency = setting('currency', 'تومان'); ?>
<div class="page-head">
    <h2>گزارش‌ها</h2>
</div>

<div class="stat-grid">
    <a class="card stat" href="<?= url('reports/sales') ?>" style="text-decoration:none;color:inherit">
        <div class="stat-label"><?= icon_svg('cart') ?> گزارش فروش</div>
        <div class="stat-value"><?= money($salesMonth) ?></div>
        <div class="stat-hint">فروش این ماه — <?= e($currency) ?></div>
    </a>
    <a class="card stat" href="<?= url('reports/inventory') ?>" style="text-decoration:none;color:inherit">
        <div class="stat-label"><?= icon_svg('box') ?> موجودی انبار</div>
        <div class="stat-value"><?= money($stockValue) ?></div>
        <div class="stat-hint">ارزش موجودی — <?= e($currency) ?></div>
    </a>
    <a class="card stat" href="<?= url('reports/customers') ?>" style="text-decoration:none;color:inherit">
        <div class="stat-label"><?= icon_svg('users') ?> مانده مشتریان</div>
        <div class="stat-value"><?= fa_num($customersCount) ?></div>
        <div class="stat-hint">تعداد مشتریان</div>
    </a>
    <a class="card stat" href="<?= url('reports/treasury') ?>" style="text-decoration:none;color:inherit">
        <div class="stat-label"><?= icon_svg('wallet') ?> خزانه</div>
        <div class="stat-value"><?= money($cashTotal) ?></div>
        <div class="stat-hint">موجودی صندوق و بانک</div>
    </a>
    <a class="card stat" href="<?= url('excel') ?>" style="text-decoration:none;color:inherit">
        <div class="stat-label"><?= icon_svg('sheet') ?> خروجی اکسل</div>
        <div class="stat-value">CSV</div>
        <div class="stat-hint">گرفتن خروجی گزارش‌ها</div>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>بهترین مشتریان (بر اساس فروش)</h3><a class="btn btn-sm btn-outline" href="<?= url('reports/customers') ?>">گزارش کامل</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>مشتری</th><th>تعداد فاکتور</th><th>مجموع فروش</th></tr></thead>
                <tbody>
                <?php if (!$bestCustomers): ?><tr><td colspan="4" class="empty">هنوز فروشی ثبت نشده است.</td></tr><?php endif; ?>
                <?php foreach ($bestCustomers as $i => $c): ?>
                    <tr>
                        <td class="muted"><?= fa_num($i + 1) ?></td>
                        <td><a href="<?= url('customers/view/' . $c['id']) ?>"><?= e($c['full_name']) ?></a></td>
                        <td><?= fa_num($c['inv_count']) ?></td>
                        <td><?= money($c['total_sales']) ?> <small class="muted"><?= e($currency) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>پرفروش‌ترین کالاها (بر اساس تعداد)</h3><a class="btn btn-sm btn-outline" href="<?= url('reports/inventory') ?>">موجودی</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>کالا</th><th>تعداد فروش</th><th>درآمد</th></tr></thead>
                <tbody>
                <?php if (!$topProducts): ?><tr><td colspan="4" class="empty">هنوز قلم فروشی ثبت نشده است.</td></tr><?php endif; ?>
                <?php foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td class="muted"><?= fa_num($i + 1) ?></td>
                        <td><?= e($p['name'] ?? 'کالای حذف‌شده') ?><?= $p['unit'] ? ' <small class="muted">(' . e($p['unit']) . ')</small>' : '' ?></td>
                        <td><?= fa_num(rtrim(rtrim((string) $p['qty_sold'], '0'), '.')) ?></td>
                        <td><?= money($p['revenue']) ?> <small class="muted"><?= e($currency) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
