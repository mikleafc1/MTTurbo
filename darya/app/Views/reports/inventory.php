<?php /** گزارش موجودی انبار — با هایلایت کمبود موجودی و ردیف جمع */ $currency = setting('currency', 'تومان'); ?>
<div class="page-head">
    <h2>گزارش موجودی انبار</h2>
    <div class="page-actions">
        <?php if (can('excel.view')): ?><a class="btn btn-outline" href="<?= url('excel/export/inventory') ?>"><?= icon_svg('sheet', 15) ?> خروجی CSV</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('reports') ?>">← گزارش‌ها</a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">تعداد کالا</div>
        <div class="stat-value"><?= fa_num($itemCount) ?></div>
        <div class="stat-hint">قلم کالا</div>
    </div>
    <div class="card stat">
        <div class="stat-label">ارزش موجودی</div>
        <div class="stat-value"><?= money($stockValue) ?></div>
        <div class="stat-hint">موجودی × قیمت خرید — <?= e($currency) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">کمبود موجودی</div>
        <div class="stat-value text-danger"><?= fa_num($lowCount) ?></div>
        <div class="stat-hint">زیر حداقل مجاز</div>
    </div>
    <div class="card stat">
        <div class="stat-label">ناموجود</div>
        <div class="stat-value text-amber"><?= fa_num($zeroCount) ?></div>
        <div class="stat-hint">موجودی صفر</div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>کالا</th><th>دسته‌بندی</th><th>واحد</th>
                    <th>موجودی</th><th>حداقل</th><th>قیمت خرید</th><th>قیمت فروش</th><th>ارزش موجودی</th><th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="10" class="empty">کالایی ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $qty = (float) $r['stock_qty'];
                $min = (float) $r['min_stock'];
                $isZero = $qty <= 0;
                $isLow = !$isZero && $min > 0 && $qty <= $min;
            ?>
                <tr class="<?= $isZero || $isLow ? 'text-danger' : '' ?>">
                    <td class="muted"><?= e($r['sku'] ?? '—') ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td class="muted"><?= e($r['category']) ?></td>
                    <td><?= e($r['unit']) ?></td>
                    <td><?= fa_num(rtrim(rtrim((string) $r['stock_qty'], '0'), '.')) ?></td>
                    <td><?= fa_num(rtrim(rtrim((string) $r['min_stock'], '0'), '.')) ?></td>
                    <td><?= money($r['purchase_price']) ?></td>
                    <td><?= money($r['sale_price']) ?></td>
                    <td><?= money($r['stock_value']) ?></td>
                    <td>
                        <?php if ($isZero): ?><span class="badge badge-red">ناموجود</span>
                        <?php elseif ($isLow): ?><span class="badge badge-amber">کمبود</span>
                        <?php else: ?><span class="badge badge-green">مناسب</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows): ?>
                <tr>
                    <th colspan="8">جمع کل (<?= fa_num($itemCount) ?> قلم)</th>
                    <th><?= money($stockValue) ?></th>
                    <th></th>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="hint-box">ارزش موجودی = موجودی فعلی × قیمت خرید (میانگین موزون).</div>
</div>
