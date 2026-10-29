<?php /** لیست فاکتورها — مشترک بین فروش / خرید / برگشتی‌ها (متغیرها از کنترلر) */
$statusOptions = [
    'draft' => 'پیش‌نویس',
    'pending' => 'در انتظار تأیید',
    'unpaid' => 'پرداخت نشده',
    'partial' => 'پرداخت جزئی',
    'paid' => 'پرداخت شده',
    'canceled' => 'لغو شده',
];
$sourceBadge = static function (string $s): string {
    return match ($s) {
        'woocommerce' => '<span class="badge badge-green">ووکامرس</span>',
        'moboorder' => '<span class="badge badge-amber">موبوآردر</span>',
        default => '<span class="badge badge-gray">دستی</span>',
    };
};
?>
<div class="page-head">
    <h2><?= e($heading) ?> <small class="muted">(<?= fa_num($total) ?> فاکتور)</small></h2>
    <div class="page-actions">
        <?php if (can('invoices.edit')): ?>
            <a class="btn btn-primary" href="<?= url($createRoute) ?>">+ <?= e($createLabel) ?></a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url($listRoute) ?>" class="search-bar">
    <input type="hidden" name="route" value="<?= e($listRoute) ?>">
    <select name="status" class="form-input" style="max-width:170px">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach ($statusOptions as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="q" class="form-input" placeholder="جستجو: شماره فاکتور<?= $isPurchase ? ' یا تأمین‌کننده' : ' یا مشتری' ?>..." value="<?= e($filters['q'] ?? '') ?>">
    <input type="text" name="from" class="form-input" dir="ltr" style="max-width:130px" placeholder="از ۱۴۰۳/۰۱/۰۱" value="<?= e($filters['from'] ?? '') ?>">
    <input type="text" name="to" class="form-input" dir="ltr" style="max-width:130px" placeholder="تا ۱۴۰۳/۰۱/۰۱" value="<?= e($filters['to'] ?? '') ?>">
    <button class="btn btn-outline">اعمال فیلتر</button>
    <a class="btn btn-outline" href="<?= url($listRoute) ?>">حذف فیلترها</a>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>شماره</th>
                    <th><?= $isPurchase ? 'تأمین‌کننده' : 'مشتری' ?></th>
                    <th>مبلغ کل</th>
                    <th>پرداخت‌شده</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>منبع</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="empty">فاکتوری یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= url('invoices/view/' . $r['id']) ?>"><?= e($r['invoice_no']) ?></a></td>
                    <td><?= e($isPurchase ? ($r['supplier_name'] ?? '—') : ($r['customer_name'] ?? '—')) ?></td>
                    <td><?= money($r['total']) ?></td>
                    <td><?= money($r['paid_amount']) ?></td>
                    <td><?= status_label($r['status']) ?></td>
                    <td><?= jdate($r['invoice_date']) ?></td>
                    <td><?= $sourceBadge((string) $r['source']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline" href="<?= url('invoices/view/' . $r['id']) ?>">مشاهده</a>
                        <a class="btn btn-sm btn-outline" href="<?= url('invoices/print/' . $r['id']) ?>" target="_blank" rel="noopener">چاپ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => $base]); ?>
