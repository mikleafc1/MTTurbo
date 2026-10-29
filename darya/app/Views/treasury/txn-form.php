<?php /** فرم مشترک ثبت دریافت/پرداخت — $type = receipt|payment */
$isReceipt = $type === 'receipt';
$old = fn(string $k, $d = '') => e($_POST[$k] ?? ($d ?? ''));
$amountDefault = $_POST['amount'] ?? '';
$partyDefault = (int) ($_POST['party_id'] ?? 0);
if (!$partyDefault && $invoice) $partyDefault = (int) ($invoice['customer_id'] ?? 0);
$remaining = $invoice ? max((float) $invoice['total'] - (float) $invoice['paid_amount'], 0) : null;
?>
<div class="page-head">
    <h2><?= $isReceipt ? 'ثبت دریافت' : 'ثبت پرداخت' ?><?= $invoice ? ' <small class="muted">— فاکتور ' . e($invoice['invoice_no']) . '</small>' : '' ?></h2>
    <a class="btn btn-outline" href="<?= url($invoice ? 'invoices/view/' . $invoice['id'] : 'treasury/transactions') ?>">← بازگشت</a>
</div>

<?php if ($invoice): ?>
<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">مبلغ کل فاکتور</div>
        <div class="stat-value"><?= money($invoice['total']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">پرداخت‌شده</div>
        <div class="stat-value"><?= money($invoice['paid_amount']) ?></div>
        <div class="stat-hint"><?= status_label($invoice['status']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">باقیمانده</div>
        <div class="stat-value text-danger"><?= money($remaining) ?></div>
        <div class="stat-hint">در فیلد مبلغ پیش‌پر شده است</div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= url($isReceipt ? 'treasury/store-receipt' : 'treasury/store-payment') ?>">
        <?= csrf_field() ?>
        <?php if ($invoice): ?><input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div>
                <label class="form-label required">مبلغ (<?= e(setting('currency', 'تومان')) ?>)</label>
                <input class="form-input" name="amount" data-money required dir="ltr" value="<?= e($amountDefault !== '' ? $amountDefault : ($invoice ? (string) $remaining : '')) ?>" placeholder="0">
            </div>
            <div>
                <label class="form-label required">تاریخ (شمسی)</label>
                <input class="form-input" name="trx_date" required dir="ltr" value="<?= $old('trx_date', jdate(today(), 'Y/m/d')) ?>">
            </div>
        </div>

        <div class="form-row">
            <div>
                <label class="form-label">روش</label>
                <select class="form-input" name="method">
                    <?php foreach ($methods as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= (string) ($_POST['method'] ?? 'cash') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required"><?= $isReceipt ? 'صندوق / حساب مقصد' : 'صندوق / حساب مبدأ' ?></label>
                <select class="form-input" name="cashbox_id" required>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($cashboxes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['cashbox_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (<?= $c['type'] === 'bank' ? 'بانک' : 'نقد' ?> — مانده: <?= money($c['balance']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label class="form-label">مشتری</label>
                <select class="form-input" name="party_id">
                    <option value="0">— بدون انتخاب —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $partyDefault === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['full_name']) ?><?= (float) $c['balance'] > 0 ? ' (بدهی: ' . money($c['balance']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$isReceipt): ?>
            <div>
                <label class="form-label">طرف حساب دیگر (تأمین‌کننده / سایر)</label>
                <input class="form-input" name="party_text" placeholder="اگر مشتری نیست، نام طرف حساب را بنویسید" value="<?= $old('party_text') ?>">
            </div>
            <?php endif; ?>
        </div>

        <label class="form-label">شرح</label>
        <textarea class="form-input" name="description" rows="2"><?= $old('description', $invoice ? 'تسویه فاکتور ' . $invoice['invoice_no'] : '') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $isReceipt ? 'ثبت دریافت' : 'ثبت پرداخت' ?></button>
            <a class="btn btn-outline" href="<?= url($invoice ? 'invoices/view/' . $invoice['id'] : 'treasury/transactions') ?>">انصراف</a>
        </div>
    </form>
</div>
<div class="hint-box">با ثبت <?= $isReceipt ? 'دریافت' : 'پرداخت' ?>، مانده صندوق انتخابی <?= $isReceipt ? 'افزایش' : 'کاهش' ?> می‌یابد و سند حسابداری به‌صورت خودکار صادر می‌شود. اگر فاکتور انتخاب شده باشد، پرداخت فاکتور و وضعیت آن بروزرسانی می‌شود.</div>
