<?php
/** چاپ فاکتور — A4 (لایه print) — مشترک بین فروش/خرید/برگشتی‌ها */
$payLabels = \App\Controllers\InvoiceController::PAY_METHODS;
$qtyStr = static function ($q): string {
    return rtrim(rtrim(number_format((float) $q, 3, '.', ''), '0'), '.');
};
?>
<div class="print-head">
    <div>
        <h1><?= e(setting('company_name', 'شرکت من')) ?></h1>
        <div style="font-size:11.5px;color:#555">
            <?= setting('company_phone') ? 'تلفن: ' . e(fa_num((string) setting('company_phone'))) : '' ?>
            <?= setting('company_address') ? (setting('company_phone') ? ' — ' : '') . e((string) setting('company_address')) : '' ?>
        </div>
    </div>
    <div class="print-meta">
        <div><b><?= e($typeLabel) ?></b></div>
        <div>شماره: <?= e($row['invoice_no']) ?></div>
        <div>تاریخ: <?= jdate($row['invoice_date']) ?></div>
        <?php if ($row['due_date']): ?><div>سررسید: <?= jdate($row['due_date']) ?></div><?php endif; ?>
    </div>
</div>

<div style="margin-bottom:12px;font-size:13px;line-height:1.9">
    <?php if ($isPurchase): ?>
        <b>تأمین‌کننده:</b> <?= e($row['supplier_name'] ?? '—') ?>
    <?php else: ?>
        <b>مشتری:</b> <?= $row['customer_name'] ? e($row['customer_name']) : 'مشتری متفرقه' ?>
        <?= $row['customer_mobile'] ? ' — موبایل: ' . e(fa_num((string) $row['customer_mobile'])) : '' ?>
    <?php endif; ?>
    <?php if ($row['payment_method']): ?>
        &nbsp;|&nbsp; <b>روش پرداخت:</b> <?= e($payLabels[$row['payment_method']] ?? (string) $row['payment_method']) ?>
    <?php endif; ?>
    <?php if (!empty($row['promoter_name'])): ?>
        &nbsp;|&nbsp; <b>مبلغ:</b> <?= e($row['promoter_name']) ?>
    <?php endif; ?>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width:28px">#</th>
            <th>کالا / شرح</th>
            <th style="width:70px">تعداد</th>
            <th style="width:110px">قیمت واحد</th>
            <th style="width:90px">تخفیف</th>
            <th style="width:120px">جمع</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
        <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td>
                <?= e($it['product_name'] ?? $it['description'] ?? '—') ?>
                <?= ($it['product_name'] && $it['description'] && $it['description'] !== $it['product_name']) ? ' — ' . e($it['description']) : '' ?>
                <?= !empty($it['product_unit']) ? ' <small>(' . e($it['product_unit']) . ')</small>' : '' ?>
            </td>
            <td><?= e(fa_num($qtyStr($it['qty']))) ?></td>
            <td><?= money($it['unit_price']) ?></td>
            <td><?= (float) $it['discount'] > 0 ? money($it['discount']) : '—' ?></td>
            <td><?= money($it['total']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="totals">
    <div><span>جمع اقلام</span><span><?= money($row['subtotal']) ?></span></div>
    <div><span>تخفیف</span><span><?= money($row['discount']) ?></span></div>
    <div><span>مالیات بر ارزش افزوده (<?= e(fa_num($qtyStr($row['tax_rate']))) ?>٪)</span><span><?= money($row['tax_amount']) ?></span></div>
    <div><span>هزینه حمل</span><span><?= money($row['shipping']) ?></span></div>
    <div class="grand"><span>مبلغ قابل پرداخت</span><span><?= money($row['total']) ?> <?= e(setting('currency', 'تومان')) ?></span></div>
    <div><span>پرداخت‌شده</span><span><?= money($row['paid_amount']) ?></span></div>
    <div><span>مانده</span><span><?= money($remaining) ?></span></div>
</div>

<?php if ($row['description']): ?>
    <p style="font-size:12px;margin-top:12px"><b>توضیحات:</b> <?= e($row['description']) ?></p>
<?php endif; ?>

<div class="print-foot">
    <div>امضای فروشنده: ..............................</div>
    <div>امضای خریدار: ..............................</div>
</div>
