<?php /** لیست تراکنش‌های دریافت و پرداخت */
$qs = array_filter(['type' => $type, 'cashbox_id' => $cashboxId, 'date_from' => $from, 'date_to' => $to], fn($v) => $v !== '' && $v !== null && $v !== 0);
$base = url('treasury/transactions') . '?' . http_build_query($qs) . ($qs ? '&' : '');
?>
<div class="page-head">
    <h2>دریافت و پرداخت <small class="muted">(<?= fa_num($total) ?> تراکنش)</small></h2>
    <div class="page-actions">
        <?php if (can('treasury.edit')): ?>
            <a class="btn btn-primary" href="<?= url('treasury/receipt') ?>">+ دریافت جدید</a>
            <a class="btn btn-outline" href="<?= url('treasury/payment') ?>">+ پرداخت جدید</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('treasury/transactions') ?>" class="search-bar">
    <input type="hidden" name="route" value="treasury/transactions">
    <select class="form-input" name="type">
        <option value="">همه انواع</option>
        <option value="receipt" <?= $type === 'receipt' ? 'selected' : '' ?>>دریافت</option>
        <option value="payment" <?= $type === 'payment' ? 'selected' : '' ?>>پرداخت</option>
    </select>
    <select class="form-input" name="cashbox_id">
        <option value="0">همه صندوق‌ها</option>
        <?php foreach ($cashboxes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $cashboxId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="date_from" class="form-input" placeholder="از تاریخ" value="<?= e($from) ?>">
    <input type="text" name="date_to" class="form-input" placeholder="تا تاریخ" value="<?= e($to) ?>">
    <button class="btn btn-outline">فیلتر</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>شماره</th><th>تاریخ</th><th>نوع</th><th>طرف حساب</th><th>صندوق</th><th>روش</th><th>مبلغ</th><th>شرح</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="empty">تراکنشی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $t): ?>
                <tr>
                    <td class="muted"><b><?= e($t['trx_no'] ?: '—') ?></b></td>
                    <td><?= jdate($t['trx_date']) ?></td>
                    <td><?= $t['type'] === 'receipt' ? '<span class="badge badge-green">دریافت</span>' : '<span class="badge badge-red">پرداخت</span>' ?></td>
                    <td><?= e($t['party_name'] ?: ($t['party_type'] === 'other' ? 'سایر' : '—')) ?><?= $t['invoice_id'] ? ' <a class="muted" href="' . url('invoices/view/' . $t['invoice_id']) . '" title="فاکتور">#فاکتور</a>' : '' ?></td>
                    <td><?= e($t['cashbox_name'] ?: '—') ?> <?= $t['cashbox_type'] === 'bank' ? '<small class="muted">(بانک)</small>' : '' ?></td>
                    <td><?= e($methods[$t['method']] ?? $t['method']) ?></td>
                    <td class="<?= $t['type'] === 'receipt' ? 'text-danger' : '' ?>"><b><?= money($t['amount']) ?></b></td>
                    <td class="muted"><?= e(mb_substr((string) ($t['description'] ?? ''), 0, 40)) ?></td>
                    <td>
                        <?php if ($t['invoice_id'] && can('invoices.view')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('invoices/view/' . $t['invoice_id']) ?>">فاکتور</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => $base]); ?>
