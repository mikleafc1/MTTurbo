<?php /** گزارش خزانه — صندوق/بانک + دریافت و پرداخت بازه + خلاصه چک‌ها */ $currency = setting('currency', 'تومان'); ?>
<div class="page-head">
    <h2>گزارش خزانه</h2>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= url('reports') ?>">← گزارش‌ها</a>
    </div>
</div>

<form method="get" action="<?= url('reports/treasury') ?>" class="search-bar">
    <input type="hidden" name="route" value="reports/treasury">
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
        <div class="stat-label">جمع دریافتی‌ها</div>
        <div class="stat-value text-success"><?= money($receipt['total']) ?></div>
        <div class="stat-hint"><?= fa_num($receipt['cnt']) ?> تراکنش در بازه</div>
    </div>
    <div class="card stat">
        <div class="stat-label">جمع پرداختی‌ها</div>
        <div class="stat-value text-danger"><?= money($payment['total']) ?></div>
        <div class="stat-hint"><?= fa_num($payment['cnt']) ?> تراکنش در بازه</div>
    </div>
    <div class="card stat">
        <div class="stat-label">خالص جریان نقدی</div>
        <div class="stat-value"><?= money($receipt['total'] - $payment['total']) ?></div>
        <div class="stat-hint">دریافت − پرداخت</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>صندوق‌ها و بانک‌ها</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>نام</th><th>نوع</th><th>بانک</th><th>موجودی</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php if (!$cashboxes): ?><tr><td colspan="5" class="empty">صندوقی ثبت نشده است.</td></tr><?php endif; ?>
                <?php foreach ($cashboxes as $cb): ?>
                    <tr>
                        <td><?= e($cb['name']) ?></td>
                        <td><?= $cb['type'] === 'bank' ? 'بانک' : 'نقدی' ?></td>
                        <td class="muted"><?= e($cb['bank_name'] ?? '—') ?></td>
                        <td class="<?= (float) $cb['balance'] < 0 ? 'text-danger' : '' ?>"><?= money($cb['balance']) ?></td>
                        <td><?= status_label((int) $cb['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>خلاصه چک‌ها (کل سیستم)</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th rowspan="2">وضعیت</th>
                        <th colspan="2">دریافتی (از مشتری)</th>
                        <th colspan="2">صادره (به تامین‌کننده)</th>
                    </tr>
                    <tr><th>تعداد</th><th>مبلغ</th><th>تعداد</th><th>مبلغ</th></tr>
                </thead>
                <tbody>
                <?php if (!$cheques): ?><tr><td colspan="5" class="empty">چکی ثبت نشده است.</td></tr><?php endif; ?>
                <?php foreach ($cheques as $ch): ?>
                    <tr>
                        <td><?= status_label($ch['status']) ?></td>
                        <td><?= fa_num($ch['cnt_received']) ?></td>
                        <td><?= money($ch['amount_received']) ?></td>
                        <td><?= fa_num($ch['cnt_issued']) ?></td>
                        <td><?= money($ch['amount_issued']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="hint-box">دریافتی‌ها/پرداختی‌های بالا فقط شامل تراکنش‌های خزانه در بازه انتخابی است؛ موجودی صندوق‌ها مقدار لحظه‌ای است.</div>
