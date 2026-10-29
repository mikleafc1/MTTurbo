<?php /** مشاهده سند حسابداری */
$sources = [
    'manual'        => 'دستی',
    'auto_sale'     => 'فاکتور فروش',
    'auto_purchase' => 'فاکتور خرید',
    'auto_cogs'     => 'بهای تمام‌شده',
    'auto_treasury' => 'خزانه (دریافت/پرداخت)',
];
$totalDebit = 0.0;
$totalCredit = 0.0;
foreach ($lines as $l) {
    $totalDebit += (float) $l['debit'];
    $totalCredit += (float) $l['credit'];
}
$balanced = abs($totalDebit - $totalCredit) <= 0.01;
?>
<div class="page-head">
    <h2>سند <?= e($voucher['voucher_no']) ?> <?= $voucher['status'] === 'posted' ? '<span class="badge badge-green">ثبت‌شده</span>' : status_label('draft') ?></h2>
    <div class="page-actions">
        <?php if ($voucher['source'] === 'manual' && can('accounting.edit')): ?>
            <a class="btn btn-outline" href="<?= url('accounting/voucher-form/' . $voucher['id']) ?>">ویرایش</a>
        <?php endif; ?>
        <?php if ($voucher['source'] === 'manual' && can('accounting.manage')): ?>
        <form method="post" action="<?= url('accounting/voucher-delete/' . $voucher['id']) ?>" style="display:inline" data-confirm="سند «<?= e($voucher['voucher_no']) ?>» حذف شود؟">
            <?= csrf_field() ?>
            <button class="btn btn-danger">حذف</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= url('accounting/vouchers') ?>">← بازگشت</a>
    </div>
</div>

<div class="card">
    <div class="form-row">
        <div><b>تاریخ:</b> <?= jdate($voucher['voucher_date']) ?></div>
        <div><b>شرح:</b> <?= e($voucher['description'] ?: '—') ?></div>
        <div><b>منبع:</b> <?= e($sources[$voucher['source']] ?? $voucher['source']) ?></div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>کد حساب</th><th>نام حساب</th><th>شرح سطر</th><th>بدهکار</th><th>بستانکار</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$lines): ?><tr><td colspan="6" class="empty">سطری برای این سند ثبت نشده.</td></tr><?php endif; ?>
            <?php foreach ($lines as $i => $l): ?>
                <tr>
                    <td class="muted"><?= fa_num($i + 1) ?></td>
                    <td class="muted" dir="ltr"><?= e($l['account_code']) ?></td>
                    <td><?= e($l['account_name']) ?></td>
                    <td class="muted"><?= e($l['description'] ?: '—') ?></td>
                    <td class="text-danger"><?= (float) $l['debit'] > 0 ? money($l['debit']) : '—' ?></td>
                    <td><?= (float) $l['credit'] > 0 ? money($l['credit']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" style="text-align:left">جمع کل</th>
                    <th><?= money($totalDebit) ?></th>
                    <th><?= money($totalCredit) ?></th>
                </tr>
                <tr>
                    <th colspan="4" style="text-align:left">تراز</th>
                    <th colspan="2"><?= $balanced ? '<span class="badge badge-green">تراز است ✓</span>' : '<span class="badge badge-red">اختلاف: ' . money(abs($totalDebit - $totalCredit)) . '</span>' ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
