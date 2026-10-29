<?php /** فرم ایجاد/ویرایش صندوق یا حساب بانکی */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش «' . e($row['name']) . '»' : 'صندوق / حساب بانکی جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('treasury/cashboxes') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'treasury/cashbox-store/' . $row['id'] : 'treasury/cashbox-store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام</label>
                <input class="form-input" name="name" required placeholder="مثال: صندوق فروشگاه یا بانک ملت جاری" value="<?= $old('name') ?>">
            </div>
            <div>
                <label class="form-label">نوع</label>
                <select class="form-input" name="type">
                    <?php foreach ($boxTypes as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= (string) ($_POST['type'] ?? ($row['type'] ?? 'cash')) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">نام بانک</label>
                <input class="form-input" name="bank_name" value="<?= $old('bank_name') ?>">
            </div>
            <div>
                <label class="form-label">شماره حساب</label>
                <input class="form-input" name="account_number" dir="ltr" value="<?= $old('account_number') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">شماره شبا</label>
                <input class="form-input" name="iban" dir="ltr" placeholder="IR" value="<?= $old('iban') ?>">
            </div>
            <div>
                <label class="form-label">شماره کارت</label>
                <input class="form-input" name="card_number" dir="ltr" value="<?= $old('card_number') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">وضعیت</label>
                <label style="display:flex;align-items:center;gap:8px;margin-top:6px">
                    <input type="checkbox" name="is_active" value="1" <?= (string) ($_POST['is_active'] ?? ((int) ($row['is_active'] ?? 1) ? '1' : '')) === '1' ? 'checked' : '' ?>>
                    فعال
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('treasury/cashboxes') ?>">انصراف</a>
        </div>
    </form>
</div>
