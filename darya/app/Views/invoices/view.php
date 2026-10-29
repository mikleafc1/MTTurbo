<?php
/** نمایش فاکتور — مشترک بین فروش/خرید/برگشتی‌ها ($typeLabel و $isPurchase از کنترلر) */
$payLabels = \App\Controllers\InvoiceController::PAY_METHODS;
$sourceBadge = static function (string $s): string {
    return match ($s) {
        'woocommerce' => '<span class="badge badge-green">ووکامرس</span>',
        'moboorder' => '<span class="badge badge-amber">موبوآردر</span>',
        default => '<span class="badge badge-gray">دستی</span>',
    };
};
$status = (string) $row['status'];
$canReceive = !$isPurchase && can('treasury.edit') && $remaining > 0.009 && in_array($status, ['unpaid', 'partial'], true);
$canPay = $isPurchase && can('treasury.edit') && $remaining > 0.009 && in_array($status, ['unpaid', 'partial'], true);
$canApprove = $status === 'pending' && $row['source'] === 'moboorder' && $row['invoice_type'] === 'sale' && can('invoices.manage');
$canCancel = can('invoices.manage') && $status !== 'canceled';
?>
<div class="page-head">
    <h2>فاکتور <?= e($row['invoice_no']) ?> <small class="muted">(<?= e($typeLabel) ?>)</small></h2>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= url('invoices/print/' . $row['id']) ?>" target="_blank" rel="noopener">چاپ فاکتور</a>
        <?php if ($canReceive): ?>
            <a class="btn btn-primary" href="<?= url('treasury/receipt/' . $row['id']) ?>">ثبت دریافت</a>
        <?php endif; ?>
        <?php if ($canPay): ?>
            <a class="btn btn-primary" href="<?= url('treasury/payment/' . $row['id']) ?>">ثبت پرداخت</a>
        <?php endif; ?>
        <?php if ($canApprove): ?>
            <form method="post" action="<?= url('invoices/approve/' . $row['id']) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-primary">تأیید سفارش</button>
            </form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
            <form method="post" action="<?= url('invoices/cancel/' . $row['id']) ?>" style="display:inline"
                  data-confirm="فاکتور لغو شود؟ در صورت خروج/ورود قبلی موجودی، برگشت انبار ثبت می‌گردد.">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger">لغو فاکتور</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= url($backRoute) ?>">← بازگشت</a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">مبلغ کل</div>
        <div class="stat-value"><?= money($row['total']) ?></div>
        <div class="stat-hint"><?= e(setting('currency', 'تومان')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">پرداخت‌شده</div>
        <div class="stat-value"><?= money($row['paid_amount']) ?></div>
        <div class="stat-hint">مجموع دریافت/پرداخت مرتبط</div>
    </div>
    <div class="card stat">
        <div class="stat-label">مانده</div>
        <div class="stat-value <?= $remaining > 0.009 && !$isPurchase ? 'text-danger' : '' ?>"><?= money($remaining) ?></div>
        <div class="stat-hint"><?= $isPurchase ? 'بدهی به تأمین‌کننده' : 'طلب از مشتری' ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">وضعیت</div>
        <div class="stat-value" style="font-size:15px"><?= status_label($status) ?> <?= $sourceBadge((string) $row['source']) ?></div>
        <div class="stat-hint">ثبت: <?= jdate($row['created_at'], 'Y/m/d H:i') ?: '—' ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>اطلاعات فاکتور</h3></div>
        <table class="table" style="font-size:13px">
            <tbody>
                <?php if ($isPurchase): ?>
                    <tr><th style="width:110px">تأمین‌کننده</th><td><?= e($row['supplier_name'] ?? '—') ?></td></tr>
                    <tr><th>کارمند</th><td><?= e($row['employee_id'] ? (string) (\App\Core\Database::val('SELECT full_name FROM employees WHERE id = ?', [$row['employee_id']]) ?? '—') : '—') ?></td></tr>
                <?php else: ?>
                    <tr><th style="width:110px">مشتری</th><td>
                        <?php if ($row['customer_id']): ?>
                            <a href="<?= url('customers/view/' . $row['customer_id']) ?>"><?= e($row['customer_name'] ?? '—') ?></a>
                        <?php else: ?>
                            متفرقه / بدون مشتری
                        <?php endif; ?>
                        <?= $row['customer_mobile'] ? ' <span class="muted" dir="ltr">' . e(fa_num($row['customer_mobile'])) . '</span>' : '' ?>
                    </td></tr>
                    <?php if ($row['promoter_id']): ?>
                    <tr><th>مبلغ (موبوآردر)</th><td><?= e($row['promoter_name'] ?? '—') ?></td></tr>
                    <?php endif; ?>
                <?php endif; ?>
                <tr><th>تاریخ فاکتور</th><td><?= jdate($row['invoice_date']) ?></td></tr>
                <tr><th>سررسید</th><td><?= $row['due_date'] ? jdate($row['due_date']) : '—' ?></td></tr>
                <tr><th>روش پرداخت</th><td><?= $row['payment_method'] ? e($payLabels[$row['payment_method']] ?? (string) $row['payment_method']) : '—' ?></td></tr>
                <tr><th>ثبت‌کننده</th><td><?= e($row['creator_name'] ?? '—') ?></td></tr>
                <?php if ($row['wc_order_no']): ?>
                    <tr><th>سفارش ووکامرس</th><td dir="ltr"><?= e($row['wc_order_no']) ?></td></tr>
                <?php endif; ?>
                <?php if ($row['description']): ?>
                    <tr><th>توضیحات</th><td><?= e($row['description']) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>اقلام فاکتور</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>کالا / شرح</th><th>تعداد</th><th>قیمت واحد</th><th>تخفیف</th><th>جمع</th></tr></thead>
                <tbody>
                <?php if (!$items): ?><tr><td colspan="6" class="empty">قلمی ثبت نشده.</td></tr><?php endif; ?>
                <?php foreach ($items as $i => $it): ?>
                    <?php $qtyStr = rtrim(rtrim(number_format((float) $it['qty'], 3, '.', ''), '0'), '.'); ?>
                    <tr>
                        <td class="muted"><?= fa_num($i + 1) ?></td>
                        <td>
                            <?php if ($it['product_id']): ?><a href="<?= url('products/view/' . $it['product_id']) ?>"><?= e($it['product_name'] ?? 'کالا') ?></a><?php else: ?><?= e($it['description'] ?? '—') ?><?php endif; ?>
                            <?= $it['product_id'] && $it['description'] && $it['description'] !== $it['product_name'] ? '<br><small class="muted">' . e($it['description']) . '</small>' : '' ?>
                        </td>
                        <td dir="ltr"><?= e(fa_num($qtyStr)) ?> <?= e($it['product_unit'] ?? '') ?></td>
                        <td><?= money($it['unit_price']) ?></td>
                        <td><?= (float) $it['discount'] > 0 ? money($it['discount']) : '—' ?></td>
                        <td><?= money($it['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-right:auto;max-width:320px;margin-top:10px">
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--border)"><span>جمع اقلام</span><b><?= money($row['subtotal']) ?></b></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--border)"><span>تخفیف</span><b><?= money($row['discount']) ?></b></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--border)"><span>مالیات (<?= e(fa_num(rtrim(rtrim(number_format((float) $row['tax_rate'], 2, '.', ''), '0'), '.'))) ?>٪)</span><b><?= money($row['tax_amount']) ?></b></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed var(--border)"><span>هزینه حمل</span><b><?= money($row['shipping']) ?></b></div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:15px"><span>مبلغ کل</span><b style="color:var(--primary)"><?= money($row['total']) ?></b></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>دریافت‌ها / پرداخت‌های این فاکتور</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>شماره</th><th>نوع</th><th>مبلغ</th><th>روش</th><th>صندوق / بانک</th><th>تاریخ</th></tr></thead>
            <tbody>
            <?php if (!$transactions): ?><tr><td colspan="6" class="empty">تراکنشی برای این فاکتور ثبت نشده.</td></tr><?php endif; ?>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['trx_no'] ?? '') ?></td>
                    <td><?= $t['type'] === 'receipt' ? '<span class="badge badge-green">دریافت</span>' : '<span class="badge badge-red">پرداخت</span>' ?></td>
                    <td><?= money($t['amount']) ?></td>
                    <td><?= e($t['method']) ?></td>
                    <td><?= e($t['cashbox_name'] ?? '—') ?> <?= ($t['cashbox_type'] ?? '') === 'bank' ? '<small class="muted">(بانک)</small>' : '' ?></td>
                    <td><?= jdate($t['trx_date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
