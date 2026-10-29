<?php /** فرم ایجاد/ویرایش چک */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d));
$direction = (string) ($_POST['direction'] ?? ($row['direction'] ?? 'received'));
?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش چک «' . e($row['cheque_no']) . '»' : ($direction === 'issued' ? 'چک پرداختی جدید' : 'چک دریافتی جدید') ?></h2>
    <a class="btn btn-outline" href="<?= url('treasury/cheques') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'treasury/cheque-store/' . $row['id'] : 'treasury/cheque-store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">جهت چک</label>
                <select class="form-input" name="direction">
                    <option value="received" <?= $direction === 'received' ? 'selected' : '' ?>>دریافتی (از مشتری)</option>
                    <option value="issued" <?= $direction === 'issued' ? 'selected' : '' ?>>پرداختی (صادره ما)</option>
                </select>
            </div>
            <div>
                <label class="form-label required">شماره چک</label>
                <input class="form-input" name="cheque_no" required dir="ltr" value="<?= $old('cheque_no') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">بانک</label>
                <input class="form-input" name="bank_name" value="<?= $old('bank_name') ?>">
            </div>
            <div>
                <label class="form-label required">مبلغ (<?= e(setting('currency', 'تومان')) ?>)</label>
                <input class="form-input" name="amount" data-money required dir="ltr" value="<?= $old('amount') ?>" placeholder="0">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label required">سرسید (شمسی)</label>
                <input class="form-input" name="due_date" required dir="ltr" value="<?= $_POST['due_date'] ?? ($row ? jdate($row['due_date'], 'Y/m/d') : '') ?>">
            </div>
            <div>
                <label class="form-label">مشتری / طرف حساب</label>
                <select class="form-input" name="party_id">
                    <option value="0">— بدون انتخاب —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['party_id'] ?? ($row['party_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">صندوق / حساب (محل وصول یا پرداخت)</label>
                <select class="form-input" name="cashbox_id">
                    <option value="0">— تعیین نشده —</option>
                    <?php foreach ($cashboxes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['cashbox_id'] ?? ($row['cashbox_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:2">
                <label class="form-label">شرح</label>
                <input class="form-input" name="description" value="<?= $old('description') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('treasury/cheques') ?>">انصراف</a>
        </div>
    </form>
</div>
<div class="hint-box">چک‌ها ابتدا با وضعیت «در جریان» ثبت می‌شوند؛ اثر مالی صندوق هنگام تغییر وضعیت به «وصول شد» اعمال می‌شود، پس صندوق مقصد را از قبل مشخص کنید.</div>
