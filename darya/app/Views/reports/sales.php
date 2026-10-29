<?php /** گزارش فروش — کارت‌های خلاصه + تفکیک روزانه + تفکیک روش پرداخت */ $currency = setting('currency', 'تومان'); ?>
<div class="page-head">
    <h2>گزارش فروش</h2>
    <div class="page-actions">
        <?php if (can('excel.view')): ?>
            <a class="btn btn-outline" href="<?= url('excel/export/sales') ?>?from=<?= urlencode($fromJ) ?>&to=<?= urlencode($toJ) ?>"><?= icon_svg('sheet', 15) ?> خروجی CSV</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= url('reports') ?>">← گزارش‌ها</a>
    </div>
</div>

<form method="get" action="<?= url('reports/sales') ?>" class="search-bar">
    <input type="hidden" name="route" value="reports/sales">
    <div>
        <label class="form-label">از تاریخ (شمسی)</label>
        <input type="text" name="from" class="form-input" dir="ltr" placeholder="1403/05/01" value="<?= e($fromJ) ?>">
    </div>
    <div>
        <label class="form-label">تا تاریخ (شمسی)</label>
        <input type="text" name="to" class="form-input" dir="ltr" placeholder="1403/05/31" value="<?= e($toJ) ?>">
    </div>
    <div style="align-self:flex-end">
        <button class="btn btn-primary">اعمال</button>
    </div>
</form>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">تعداد فاکتور</div>
        <div class="stat-value"><?= fa_num($totals['cnt']) ?></div>
        <div class="stat-hint">فاکتور فروش (غیر لغو)</div>
    </div>
    <div class="card stat">
        <div class="stat-label">جمع فروش</div>
        <div class="stat-value"><?= money($totals['total']) ?></div>
        <div class="stat-hint"><?= e($currency) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">مجموع تخفیف</div>
        <div class="stat-value text-amber"><?= money($totals['discount']) ?></div>
        <div class="stat-hint"><?= e($currency) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">مجموع مالیات</div>
        <div class="stat-value"><?= money($totals['tax']) ?></div>
        <div class="stat-hint"><?= e($currency) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">هزینه حمل</div>
        <div class="stat-value"><?= money($totals['shipping']) ?></div>
        <div class="stat-hint"><?= e($currency) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>فروش روزانه</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>تاریخ</th><th>تعداد</th><th>فروش</th><th>تخفیف</th><th>مالیات</th></tr></thead>
                <tbody>
                <?php if (!$byDay): ?><tr><td colspan="5" class="empty">در این بازه فاکتوری ثبت نشده است.</td></tr><?php endif; ?>
                <?php foreach ($byDay as $d): ?>
                    <tr>
                        <td><?= jdate($d['invoice_date']) ?></td>
                        <td><?= fa_num($d['cnt']) ?></td>
                        <td><?= money($d['total']) ?></td>
                        <td><?= money($d['discount']) ?></td>
                        <td><?= money($d['tax']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($byDay): ?>
                    <tr>
                        <th>جمع کل</th>
                        <th><?= fa_num($totals['cnt']) ?></th>
                        <th><?= money($totals['total']) ?></th>
                        <th><?= money($totals['discount']) ?></th>
                        <th><?= money($totals['tax']) ?></th>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>بر اساس روش پرداخت</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>روش</th><th>تعداد</th><th>مبلغ</th><th>سهم</th></tr></thead>
                <tbody>
                <?php if (!$byMethod): ?><tr><td colspan="4" class="empty">داده‌ای موجود نیست.</td></tr><?php endif; ?>
                <?php foreach ($byMethod as $m): ?>
                    <?php $share = (float) $totals['total'] > 0 ? round((float) $m['total'] / (float) $totals['total'] * 100) : 0; ?>
                    <tr>
                        <td><?= e(match ($m['method']) {
                            'cash' => 'نقدی', 'transfer' => 'حواله/کارت به کارت', 'card' => 'کارت‌خوان',
                            'online' => 'پرداخت آنلاین', 'cheque' => 'چک', 'cod' => 'پرداخت در محل',
                            '—' => '—', default => $m['method'],
                        }) ?></td>
                        <td><?= fa_num($m['cnt']) ?></td>
                        <td><?= money($m['total']) ?></td>
                        <td class="muted"><?= fa_num($share) ?>٪</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
