<?php /** لیست اسناد حسابداری */
$sources = [
    'manual'        => 'دستی',
    'auto_sale'     => 'فاکتور فروش',
    'auto_purchase' => 'فاکتور خرید',
    'auto_cogs'     => 'بهای تمام‌شده',
    'auto_treasury' => 'خزانه (دریافت/پرداخت)',
];
$qs = array_filter(['source' => $source, 'status' => $status, 'date_from' => $from, 'date_to' => $to], fn($v) => $v !== '' && $v !== null);
$base = url('accounting/vouchers') . '?' . http_build_query($qs) . ($qs ? '&' : '');
?>
<div class="page-head">
    <h2>سند حسابداری <small class="muted">(<?= fa_num($total) ?> سند)</small></h2>
    <div class="page-actions">
        <?php if (can('accounting.edit')): ?><a class="btn btn-primary" href="<?= url('accounting/voucher-form') ?>">+ سند جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('accounting/vouchers') ?>" class="search-bar">
    <input type="hidden" name="route" value="accounting/vouchers">
    <select class="form-input" name="source">
        <option value="">همه منابع</option>
        <?php foreach ($sources as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $source === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-input" name="status">
        <option value="">همه وضعیت‌ها</option>
        <option value="posted" <?= $status === 'posted' ? 'selected' : '' ?>>ثبت‌شده</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
    </select>
    <input type="text" name="date_from" class="form-input" placeholder="از تاریخ ۱۴۰۴/۰۱/۰۱" value="<?= e($from) ?>">
    <input type="text" name="date_to" class="form-input" placeholder="تا تاریخ ۱۴۰۴/۰۱/۳۱" value="<?= e($to) ?>">
    <button class="btn btn-outline">فیلتر</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>شماره</th><th>تاریخ</th><th>شرح</th><th>مبلغ (بدهکار)</th><th>وضعیت</th><th>منبع</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">سندی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= url('accounting/voucher-view/' . $r['id']) ?>"><b><?= e($r['voucher_no']) ?></b></a></td>
                    <td><?= jdate($r['voucher_date']) ?></td>
                    <td><?= e($r['description'] ?: '—') ?></td>
                    <td><?= money($r['total_debit']) ?></td>
                    <td><?= $r['status'] === 'posted' ? '<span class="badge badge-green">ثبت‌شده</span>' : status_label('draft') ?></td>
                    <td class="muted"><?= e($sources[$r['source']] ?? $r['source']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline" href="<?= url('accounting/voucher-view/' . $r['id']) ?>">مشاهده</a>
                        <?php if ($r['source'] === 'manual' && can('accounting.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('accounting/voucher-form/' . $r['id']) ?>">ویرایش</a>
                        <?php endif; ?>
                        <?php if ($r['source'] === 'manual' && can('accounting.manage')): ?>
                        <form method="post" action="<?= url('accounting/voucher-delete/' . $r['id']) ?>" style="display:inline" data-confirm="سند «<?= e($r['voucher_no']) ?>» و خطوط آن حذف شود؟">
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
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => $base]); ?>
