<?php /** فرم سند حسابداری (بدون جاوااسکریپت — ۸ ردیف ثابت) */
$isEdit = $voucher !== null;
$dateValue = e($_POST['voucher_date'] ?? ($voucher ? jdate($voucher['voucher_date'], 'Y/m/d') : jdate(today(), 'Y/m/d')));
/* قالب مبلغ برای پرکردن دوباره فرم: حذف صفرهای اعشار بدون دست زدن به رقم‌های معنادار */
$fmtMoney = function (float $v): string {
    if ($v == 0.0) return '';
    $s = number_format($v, 2, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
};
?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش سند «' . e($voucher['voucher_no']) . '»' : 'سند حسابداری جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('accounting/vouchers') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'accounting/voucher-store/' . $voucher['id'] : 'accounting/voucher-store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">تاریخ سند (شمسی)</label>
                <input class="form-input" name="voucher_date" required dir="ltr" value="<?= $dateValue ?>">
            </div>
            <div style="flex:2">
                <label class="form-label">شرح سند</label>
                <input class="form-input" name="description" value="<?= e($_POST['description'] ?? ($voucher['description'] ?? '')) ?>">
            </div>
        </div>

        <label class="form-label" style="margin-top:12px">خطوط سند — جمع بدهکار باید برابر جمع بستانکار باشد</label>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:45%">حساب (کد — نام)</th>
                        <th>بدهکار</th>
                        <th>بستانکار</th>
                        <th>شرح سطر</th>
                    </tr>
                </thead>
                <tbody>
                <?php for ($i = 0; $i < $rowCount; $i++):
                    $l = $lines[$i] ?? null;
                    $selAcc = (int) ($_POST['line_account_id'][$i] ?? ($l['account_id'] ?? 0));
                    $debit = $_POST['line_debit'][$i] ?? ($l ? $fmtMoney((float) $l['debit']) : '');
                    $credit = $_POST['line_credit'][$i] ?? ($l ? $fmtMoney((float) $l['credit']) : '');
                    $desc = $_POST['line_desc'][$i] ?? ($l['description'] ?? '');
                ?>
                    <tr>
                        <td>
                            <select class="form-input" name="line_account_id[]">
                                <option value="">— انتخاب حساب —</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= (int) $a['id'] ?>" <?= $selAcc === (int) $a['id'] ? 'selected' : '' ?>>
                                        <?= e($a['code'] . ' — ' . $a['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="form-input" name="line_debit[]" data-money dir="ltr" value="<?= e($debit) ?>" placeholder="0"></td>
                        <td><input class="form-input" name="line_credit[]" data-money dir="ltr" value="<?= e($credit) ?>" placeholder="0"></td>
                        <td><input class="form-input" name="line_desc[]" value="<?= e($desc) ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ثبت سند</button>
            <a class="btn btn-outline" href="<?= url('accounting/vouchers') ?>">انصراف</a>
        </div>
    </form>
</div>
<div class="hint-box">حداقل دو سطر با حساب معین (غیرگروهی) وارد کنید؛ در هر سطر فقط یکی از ستون‌های بدهکار یا بستانکار را پر کنید. سند ترازنشده ثبت نمی‌شود.</div>
