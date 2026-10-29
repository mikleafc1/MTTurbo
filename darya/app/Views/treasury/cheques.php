<?php /** لیست چک‌های دریافتی و پرداختی + تغییر وضعیت درجای */
$qs = array_filter(['direction' => $direction, 'status' => $status, 'due_from' => $dueFrom, 'due_to' => $dueTo], fn($v) => $v !== '' && $v !== null);
$base = url('treasury/cheques') . '?' . http_build_query($qs) . ($qs ? '&' : '');
?>
<div class="page-head">
    <h2>چک‌ها <small class="muted">(<?= fa_num($total) ?> چک)</small></h2>
    <div class="page-actions">
        <?php if (can('treasury.edit')): ?>
            <a class="btn btn-primary" href="<?= url('treasury/cheque-form') ?>">+ چک دریافتی جدید</a>
            <a class="btn btn-outline" href="<?= url('treasury/cheque-form?direction=issued') ?>">+ چک پرداختی جدید</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('treasury/cheques') ?>" class="search-bar">
    <input type="hidden" name="route" value="treasury/cheques">
    <select class="form-input" name="direction">
        <option value="">همه جهت‌ها</option>
        <option value="received" <?= $direction === 'received' ? 'selected' : '' ?>>دریافتی</option>
        <option value="issued" <?= $direction === 'issued' ? 'selected' : '' ?>>پرداختی</option>
    </select>
    <select class="form-input" name="status">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach ($statuses as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="due_from" class="form-input" placeholder="سرسید از" value="<?= e($dueFrom) ?>">
    <input type="text" name="due_to" class="form-input" placeholder="سرسید تا" value="<?= e($dueTo) ?>">
    <button class="btn btn-outline">فیلتر</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>شماره چک</th><th>جهت</th><th>بانک</th><th>مبلغ</th><th>سرسید</th><th>طرف حساب</th><th>صندوق</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="empty">چکی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><b><?= e($r['cheque_no']) ?></b></td>
                    <td><?= $r['direction'] === 'received' ? '<span class="badge badge-green">دریافتی</span>' : '<span class="badge badge-red">پرداختی</span>' ?></td>
                    <td><?= e($r['bank_name'] ?: '—') ?></td>
                    <td><?= money($r['amount']) ?></td>
                    <td><?= jdate($r['due_date']) ?></td>
                    <td><?= e($r['party_name'] ?: '—') ?></td>
                    <td class="muted"><?= e($r['cashbox_name'] ?: '—') ?></td>
                    <td><?= status_label($r['status']) ?></td>
                    <td style="white-space:nowrap">
                        <?php if ($r['status'] !== 'cleared' && can('treasury.edit')): ?>
                        <form method="post" action="<?= url('treasury/cheque-status/' . $r['id']) ?>" style="display:inline-flex;gap:4px;vertical-align:middle">
                            <?= csrf_field() ?>
                            <select name="new_status" class="form-input" style="width:auto;padding:4px 6px">
                                <?php foreach ($statuses as $k => $label): ?>
                                    <?php if ($k === $r['status']) continue; ?>
                                    <option value="<?= e($k) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline">ثبت</button>
                        </form>
                        <?php endif; ?>
                        <?php if (can('treasury.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('treasury/cheque-form/' . $r['id']) ?>">ویرایش</a>
                        <?php endif; ?>
                        <?php if (can('treasury.manage')): ?>
                        <form method="post" action="<?= url('treasury/cheque-delete/' . $r['id']) ?>" style="display:inline" data-confirm="چک «<?= e($r['cheque_no']) ?>» حذف شود؟">
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
<div class="hint-box">وصول چک دریافتی مانده صندوق مقصد را افزایش و وصول چک پرداختی را کاهش می‌دهد (همراه تراکنش و سند خودکار). برگشت چک فقط وضعیت را تغییر می‌دهد. چک وصول‌شده دیگر قابل تغییر/حذف نیست.</div>
